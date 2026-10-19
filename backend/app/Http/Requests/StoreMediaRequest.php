<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class StoreMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorisation is enforced by MediaPolicy via the controller middleware.
        return true;
    }

    /**
     * Only the size is checked here. The file's type is decided by its contents
     * in ImageProcessor, not by its name or the browser's claim.
     */
    public function rules(): array
    {
        return [
            'file' => ['required', 'file', 'max:'.config('media.max_kilobytes')],
            'alt_text' => ['nullable', 'string', 'max:200'],
        ];
    }
}
