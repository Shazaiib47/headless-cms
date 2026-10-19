<?php

namespace App\Services;

use App\Enums\BlockType;
use App\Enums\PageStatus;
use App\Models\Page;
use App\Models\User;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/** Moves a page between draft and published, enforcing the publishing rules. */
class PagePublisher
{
    public const CONFLICT_MESSAGE = 'This page was changed by someone else after you opened it. Reload to see their changes.';

    public function publish(Page $page, User $actor, ?string $expectedUpdatedAt = null): Page
    {
        return $this->transition($page, $actor, $expectedUpdatedAt, PageStatus::Published);
    }

    public function unpublish(Page $page, User $actor, ?string $expectedUpdatedAt = null): Page
    {
        return $this->transition($page, $actor, $expectedUpdatedAt, PageStatus::Draft);
    }

    private function transition(Page $page, User $actor, ?string $expected, PageStatus $target): Page
    {
        return DB::transaction(function () use ($page, $actor, $expected, $target) {
            // Lock the row so two simultaneous requests cannot both pass the checks.
            $current = Page::query()->lockForUpdate()->findOrFail($page->id);

            // Repeating an action that already took effect is harmless, even when a
            // retry carries the now-stale timestamp from before the first attempt.
            if ($current->status === $target) {
                return $current;
            }

            if ($expected !== null && ! Carbon::parse($expected)->equalTo($current->updated_at)) {
                abort(409, self::CONFLICT_MESSAGE);
            }

            if ($target === PageStatus::Published) {
                $this->assertPublishable($current);

                $current->status = PageStatus::Published;
                $current->published_at = now();
            } else {
                $current->status = PageStatus::Draft;
                $current->published_at = null;
            }

            $current->updated_by = $actor->id;
            $current->save();

            return $current;
        });
    }

    /** Checks the saved content. Error keys match the editor's field paths. */
    private function assertPublishable(Page $page): void
    {
        $content = is_array($page->content) ? array_values($page->content) : [];
        $errors = [];

        if ($content === []) {
            $errors['content'] = ['A page needs at least one content block before it can be published.'];
        }

        foreach ($content as $index => $block) {
            $isImage = is_array($block) && ($block['type'] ?? null) === BlockType::Image->value;

            if ($isImage && empty($block['data']['image_id'] ?? null)) {
                $errors["content.{$index}.data.image_id"] = [
                    'This image block needs an image before the page can be published.',
                ];
            }
        }

        if ($errors !== []) {
            throw ValidationException::withMessages($errors);
        }
    }
}
