<?php

namespace App\Services;

use App\Models\Media;

/** Resolves media ids to their public image data in a single query. */
class MediaImages
{
    /**
     * @param  list<int>  $ids
     * @return array<int, array{url: string, thumbnail_url: ?string, width: int, height: int, alt: ?string}>
     */
    public static function forIds(array $ids): array
    {
        if ($ids === []) {
            return [];
        }

        return Media::query()
            ->whereIn('id', array_values(array_unique($ids)))
            ->get()
            ->mapWithKeys(fn (Media $media) => [$media->id => $media->toPublicImage()])
            ->all();
    }
}
