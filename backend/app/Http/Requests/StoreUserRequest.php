<?php

namespace App\Http\Requests;

use App\Enums\Role;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\Validation\Rules\Password;

class StoreUserRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorisation is enforced by UserPolicy via the controller middleware.
        return true;
    }

    protected function prepareForValidation(): void
    {
        $email = $this->input('email');

        if (is_string($email)) {
            $this->merge(['email' => Str::lower(trim($email))]);
        }
    }

    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:255'],
            'email' => [
                'required',
                'string',
                'email',
                'max:255',
                // On create route('user') is null, so nothing is ignored.
                Rule::unique('users', 'email')->ignore($this->route('user')),
            ],
            'password' => ['required', 'string', Password::min(12)->mixedCase()->numbers()],
            'role' => ['required', Rule::enum(Role::class)],
        ];
    }
}
