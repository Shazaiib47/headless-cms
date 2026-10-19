<?php

namespace App\Http\Requests;

use Illuminate\Foundation\Http\FormRequest;

class PagePublicationRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorisation is enforced by PagePolicy via the controller middleware.
        return true;
    }

    /** The timestamp the editor loaded; a stale value returns 409 Conflict. */
    public function rules(): array
    {
        return [
            'updated_at' => ['sometimes', 'nullable', 'date'],
        ];
    }
}
