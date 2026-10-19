<?php

namespace App\Http\Resources;

use App\Models\Page;
use App\Services\MediaImages;
use App\Support\Blocks\PublicBlocks;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The public shape of a page. It deliberately omits id, status and the
 * audit columns, never includes a rich-text block's Markdown source, and
 * exposes images only as resolved url, size and alt text (no paths).
 *
 * @mixin Page
 */
class PublicPageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        $content = is_array($this->content) ? $this->content : [];

        $ids = PublicBlocks::imageIds($content);

        if ($this->og_image_id) {
            $ids[] = (int) $this->og_image_id;
        }

        // One query for every image on the page.
        $images = MediaImages::forIds($ids);

        return [
            'title' => $this->title,
            'slug' => $this->slug,
            'content' => PublicBlocks::from($content, $images),
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'og_image_id' => $this->og_image_id,
            'og_image' => $this->og_image_id ? ($images[(int) $this->og_image_id] ?? null) : null,
            'published_at' => $this->published_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
