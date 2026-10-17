<?php

namespace App\Http\Requests;

use App\Enums\PageStatus;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class IndexPageRequest extends FormRequest
{
    /** Whitelist of sortable columns; a leading "-" means descending. */
    public const SORTS = [
        'title', '-title',
        'slug', '-slug',
        'created_at', '-created_at',
        'updated_at', '-updated_at',
        'published_at', '-published_at',
    ];

    public function authorize(): bool
    {
        // Authorisation is enforced by PagePolicy via the controller middleware.
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'status' => ['nullable', Rule::enum(PageStatus::class)],
            'sort' => ['nullable', Rule::in(self::SORTS)],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
