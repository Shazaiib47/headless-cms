<?php

namespace App\Services;

use App\Models\Media;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/** Processes an upload, saves the files under random names and records the metadata. */
class MediaStore
{
    public function __construct(private readonly ImageProcessor $images) {}

    public function store(UploadedFile $file, User $user, ?string $altText = null): Media
    {
        $processed = $this->images->process((string) $file->getRealPath());

        $disk = Storage::disk((string) config('media.disk'));
        $directory = trim((string) config('media.directory'), '/');

        // The stored name is random: the original filename never touches the filesystem.
        $name = bin2hex(random_bytes(16)).'.'.$processed['extension'];
        $path = "{$directory}/{$name}";
        $thumbnailPath = "{$directory}/thumbs/{$name}";

        $written = [];

        try {
            foreach ([$path => $processed['data'], $thumbnailPath => $processed['thumbnail']] as $target => $contents) {
                if (! $disk->put($target, $contents)) {
                    throw new \RuntimeException("Could not write {$target}.");
                }

                $written[] = $target;
            }

            return Media::create([
                'uploaded_by' => $user->id,
                'disk' => (string) config('media.disk'),
                'path' => $path,
                'thumbnail_path' => $thumbnailPath,
                'original_name' => $this->displayName($file->getClientOriginalName()),
                'mime_type' => $processed['mime'],
                'size' => strlen($processed['data']),
                'width' => $processed['width'],
                'height' => $processed['height'],
                'alt_text' => $altText,
            ]);
        } catch (\Throwable $e) {
            // Never leave files behind without a database record.
            $disk->delete($written);

            throw $e;
        }
    }

    /** Display text only: no directories, no control characters, bounded length. */
    private function displayName(string $name): string
    {
        $name = basename(str_replace('\\', '/', $name));
        $name = preg_replace('/[\x00-\x1F\x7F]/u', '', $name);

        if (! is_string($name) || trim($name) === '') {
            return 'image';
        }

        return mb_substr(trim($name), 0, 200);
    }
}
