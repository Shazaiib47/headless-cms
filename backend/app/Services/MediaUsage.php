<?php

namespace App\Services;

use App\Models\Media;
use App\Models\Page;
use App\Support\Blocks\PublicBlocks;

/** Finds the pages that still use an image. Scans page content in PHP, which suits this scale. */
class MediaUsage
{
    /** @return list<array{id: int, title: string}> */
    public function pagesUsing(Media $media, int $limit = 20): array
    {
        $found = [];

        Page::query()
            ->select(['id', 'title', 'content', 'og_image_id'])
            ->chunkById(100, function ($pages) use ($media, $limit, &$found) {
                foreach ($pages as $page) {
                    $content = is_array($page->content) ? $page->content : [];

                    if ($page->og_image_id === $media->id
                        || in_array($media->id, PublicBlocks::imageIds($content), true)) {
                        $found[] = ['id' => $page->id, 'title' => $page->title];
                    }

                    if (count($found) >= $limit) {
                        return false;
                    }
                }

                return true;
            });

        return $found;
    }
}
