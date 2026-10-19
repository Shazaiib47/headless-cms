<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class IndexMediaRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorisation is enforced by MediaPolicy via the controller middleware.
        return true;
    }

    public function rules(): array
    {
        return [
            'search' => ['nullable', 'string', 'max:100'],
            'per_page' => ['nullable', 'integer', 'between:1,100'],
            'page' => ['nullable', 'integer', 'min:1'],
        ];
    }
}
