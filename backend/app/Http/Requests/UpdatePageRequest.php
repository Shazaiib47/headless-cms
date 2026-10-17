<?php

namespace App\Http\Requests;

class UpdatePageRequest extends StorePageRequest
{
    /**
     * Same rules as creation, except title and slug become optional so
     * PATCH can send a partial update. When present they are still validated.
     */
    public function rules(): array
    {
        $rules = parent::rules();

        foreach (['title', 'slug'] as $field) {
            array_unshift($rules[$field], 'sometimes');
        }

        return $rules;
    }
}
