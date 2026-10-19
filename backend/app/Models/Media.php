<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class Media extends Model
{
    use HasFactory;

    protected $table = 'media';

    protected $fillable = [
        'uploaded_by',
        'disk',
        'path',
        'thumbnail_path',
        'original_name',
        'mime_type',
        'size',
        'width',
        'height',
        'alt_text',
    ];

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'width' => 'integer',
            'height' => 'integer',
        ];
    }

    public function uploader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    public function url(): string
    {
        return Storage::disk($this->disk)->url($this->path);
    }

    public function thumbnailUrl(): ?string
    {
        return $this->thumbnail_path ? Storage::disk($this->disk)->url($this->thumbnail_path) : null;
    }

    /** The only image fields the public API exposes (no paths, disks or uploader). */
    public function toPublicImage(): array
    {
        return [
            'url' => $this->url(),
            'thumbnail_url' => $this->thumbnailUrl(),
            'width' => $this->width,
            'height' => $this->height,
            'alt' => $this->alt_text,
        ];
    }
}
