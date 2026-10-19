<?php

namespace App\Services;

use GdImage;
use Illuminate\Validation\ValidationException;

/**
 * Validates an uploaded image by its contents, then decodes and re-encodes it.
 * Re-encoding strips metadata (EXIF, including GPS), discards anything hidden
 * in the file that is not pixel data, and rejects corrupt or disguised files.
 */
class ImageProcessor
{
    /** @return array{mime: string, extension: string, width: int, height: int, data: string, thumbnail: string} */
    public function process(string $path): array
    {
        $allowed = (array) config('media.allowed');

        // The type comes from the file's contents, never from its name or the browser.
        $mime = $this->detectMime($path);

        if ($mime === null || ! isset($allowed[$mime])) {
            $this->fail('The file must be a JPEG, PNG or WebP image.');
        }

        $info = @getimagesize($path);

        if ($info === false || ($info['mime'] ?? null) !== $mime) {
            $this->fail('The file is not a valid image.');
        }

        $width = (int) $info[0];
        $height = (int) $info[1];
        $maxPixels = (int) config('media.max_pixels');

        if ($width < 1 || $height < 1) {
            $this->fail('The file is not a valid image.');
        }

        // Checked from the header, before any decoding.
        if ($width * $height > $maxPixels) {
            $this->fail(sprintf('The image is too large (the maximum is %d megapixels).', intdiv($maxPixels, 1_000_000)));
        }

        $this->assertEnoughMemory($width, $height);

        $source = @imagecreatefromstring((string) file_get_contents($path));

        if (! $source instanceof GdImage) {
            $this->fail('The image could not be read. It may be corrupt.');
        }

        $image = $this->prepare($source, $mime, $path);

        return [
            'mime' => $mime,
            'extension' => $allowed[$mime],
            'width' => imagesx($image),
            'height' => imagesy($image),
            'data' => $this->encode($image, $mime),
            'thumbnail' => $this->thumbnail($image, $mime),
        ];
    }

    private function detectMime(string $path): ?string
    {
        $mime = (new \finfo(FILEINFO_MIME_TYPE))->file($path);

        return is_string($mime) ? $mime : null;
    }

    private function prepare(GdImage $image, string $mime, string $path): GdImage
    {
        if (! imageistruecolor($image)) {
            imagepalettetotruecolor($image);
        }

        if ($mime === 'image/jpeg') {
            $image = $this->orient($image, $path);
        }

        $image = $this->limitSize($image, (int) config('media.max_dimension'));
        $this->keepAlpha($image);

        return $image;
    }

    /** Phone photos store their rotation in EXIF. Apply it, because EXIF is about to be dropped. */
    private function orient(GdImage $image, string $path): GdImage
    {
        if (! function_exists('exif_read_data')) {
            return $image;
        }

        $exif = @exif_read_data($path);
        $orientation = is_array($exif) ? (int) ($exif['Orientation'] ?? 1) : 1;

        $rotated = match ($orientation) {
            3 => imagerotate($image, 180, 0),
            6 => imagerotate($image, -90, 0),
            8 => imagerotate($image, 90, 0),
            default => null,
        };

        return $rotated instanceof GdImage ? $rotated : $image;
    }

    private function limitSize(GdImage $image, int $maxDimension): GdImage
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $longest = max($width, $height);

        if ($longest <= $maxDimension) {
            return $image;
        }

        $this->keepAlpha($image);
        $scale = $maxDimension / $longest;

        return $this->scale(
            $image,
            max(1, (int) round($width * $scale)),
            max(1, (int) round($height * $scale)),
        );
    }

    private function scale(GdImage $image, int $width, int $height): GdImage
    {
        $scaled = imagescale($image, $width, $height, IMG_BICUBIC);

        if (! $scaled instanceof GdImage) {
            $this->fail('The image could not be processed.');
        }

        $this->keepAlpha($scaled);

        return $scaled;
    }

    private function keepAlpha(GdImage $image): void
    {
        imagealphablending($image, false);
        imagesavealpha($image, true);
    }

    private function encode(GdImage $image, string $mime): string
    {
        $this->keepAlpha($image);

        ob_start();
        $written = match ($mime) {
            'image/jpeg' => imagejpeg($image, null, (int) config('media.jpeg_quality')),
            'image/png' => imagepng($image, null, 6),
            'image/webp' => imagewebp($image, null, (int) config('media.webp_quality')),
            default => false,
        };
        $data = ob_get_clean();

        if (! $written || ! is_string($data) || $data === '') {
            $this->fail('The image could not be processed.');
        }

        return $data;
    }

    private function thumbnail(GdImage $image, string $mime): string
    {
        $width = imagesx($image);
        $height = imagesy($image);
        $target = (int) config('media.thumbnail_width');

        // Never enlarge: small images keep their size.
        if ($width <= $target) {
            return $this->encode($image, $mime);
        }

        $thumb = $this->scale($image, $target, max(1, (int) round($height * $target / $width)));

        return $this->encode($thumb, $mime);
    }

    /**
     * PHP cannot catch an out-of-memory fatal error, so estimate first and refuse
     * politely. GD holds about 4 bytes per pixel, plus extra copies while rotating
     * and scaling.
     */
    private function assertEnoughMemory(int $width, int $height): void
    {
        $limit = $this->memoryLimit();

        if ($limit === -1) {
            return;
        }

        $needed = (int) ($width * $height * 4 * 2.5);

        if ($needed > $limit - memory_get_usage(true)) {
            $this->fail('The image is too large for this server to process. Try a smaller one.');
        }
    }

    private function memoryLimit(): int
    {
        $value = trim((string) ini_get('memory_limit'));

        if ($value === '' || $value === '-1') {
            return -1;
        }

        $number = (int) $value;

        return match (strtolower(substr($value, -1))) {
            'g' => $number * 1024 ** 3,
            'm' => $number * 1024 ** 2,
            'k' => $number * 1024,
            default => $number,
        };
    }

    private function fail(string $message): never
    {
        throw ValidationException::withMessages(['file' => [$message]]);
    }
}
