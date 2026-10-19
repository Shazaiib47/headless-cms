<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class UpdateMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorisation is enforced by MediaPolicy via the controller middleware.
        return true;
    }

    /** Only the alt text can be edited. The image itself is immutable. */
    public function rules(): array
    {
        return [
            'alt_text' => ['sometimes', 'nullable', 'string', 'max:200'],
        ];
    }
}
