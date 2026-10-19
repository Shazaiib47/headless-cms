<?php

namespace App\Http\Resources;

use App\Models\Media;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/** @mixin Media */
class MediaResource extends JsonResource
{
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'original_name' => $this->original_name,
            'alt_text' => $this->alt_text,
            'mime_type' => $this->mime_type,
            'size' => $this->size,
            'width' => $this->width,
            'height' => $this->height,
            'url' => $this->url(),
            'thumbnail_url' => $this->thumbnailUrl(),
            'uploaded_by' => $this->uploaded_by,
            'created_at' => $this->created_at?->toIso8601String(),
        ];
    }
}
