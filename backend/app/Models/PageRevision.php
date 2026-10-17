<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class PageRevision extends Model
{
    use HasFactory;

    /** Revisions are immutable: there is no updated_at column. */
    public const UPDATED_AT = null;

    protected $fillable = [
        'page_id',
        'user_id',
        'version',
        'action',
        'snapshot',
    ];

    protected function casts(): array
    {
        return [
            'snapshot' => 'array',
            'version' => 'integer',
        ];
    }

    public function page(): BelongsTo
    {
        return $this->belongsTo(Page::class);
    }

    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }
}