<?php

namespace App\Http\Requests;

class UpdateUserRequest extends StoreUserRequest
{
    /**
     * Same rules as creation, but every field is optional so PATCH can send a
     * partial update. A field that is present must still be valid, and an
     * omitted password leaves the existing one untouched.
     */
    public function rules(): array
    {
        $rules = parent::rules();

        foreach (['name', 'email', 'password', 'role'] as $field) {
            array_unshift($rules[$field], 'sometimes');
        }

        return $rules;
    }
}
