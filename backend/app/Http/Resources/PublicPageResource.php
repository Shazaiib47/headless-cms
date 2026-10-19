<?php

namespace App\Http\Resources;

use App\Models\Page;
use App\Support\Blocks\PublicBlocks;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * The public shape of a page. It deliberately omits id, status and the
 * audit columns, and never includes a rich-text block's Markdown source.
 *
 * @mixin Page
 */
class PublicPageResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'title' => $this->title,
            'slug' => $this->slug,
            'content' => PublicBlocks::from(is_array($this->content) ? $this->content : []),
            'meta_title' => $this->meta_title,
            'meta_description' => $this->meta_description,
            'og_image_id' => $this->og_image_id,
            'published_at' => $this->published_at?->toIso8601String(),
            'updated_at' => $this->updated_at?->toIso8601String(),
        ];
    }
}
