<?php

namespace App\Http\Requests;

use App\Enums\BlockType;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StorePageRequest extends FormRequest
{
    /** Slugs that would collide with frontend or infrastructure routes. */
    public const RESERVED_SLUGS = ['admin', 'api', 'login', 'logout', 'preview', 'storage'];

    public function authorize(): bool
    {
        // Authorisation is enforced by PagePolicy via the controller middleware.
        return true;
    }

    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:255'],
            'slug' => [
                'required',
                'string',
                'max:255',
                'regex:/^[a-z0-9]+(?:-[a-z0-9]+)*$/',
                Rule::notIn(self::RESERVED_SLUGS),
                // On create route('page') is null, so nothing is ignored.
                Rule::unique('pages', 'slug')->ignore($this->route('page')),
            ],
            'content' => ['sometimes', 'array', 'max:50'],
            'content.*.id' => ['required', 'string', 'max:64'],
            'content.*.type' => ['required', Rule::enum(BlockType::class)],
            // Per-type payload validation arrives in Milestone 08.
            'content.*.data' => ['present', 'array'],
            'meta_title' => ['nullable', 'string', 'max:255'],
            'meta_description' => ['nullable', 'string', 'max:500'],
            'og_image_id' => ['nullable', 'integer', 'exists:media,id'],
        ];
    }
}
