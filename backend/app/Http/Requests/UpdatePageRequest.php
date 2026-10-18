<?php

namespace App\Http\Requests;

class UpdatePageRequest extends StorePageRequest
{
    /**
     * Same rules as creation, except title and slug become optional so PATCH
     * can send a partial update. When present they are still validated.
     *
     * updated_at is the timestamp the editor loaded the page with. When it is
     * sent and no longer matches, the controller answers 409 Conflict.
     */
    public function rules(): array
    {
        $rules = parent::rules();

        foreach (['title', 'slug'] as $field) {
            array_unshift($rules[$field], 'sometimes');
        }

        $rules['updated_at'] = ['sometimes', 'date'];

        return $rules;
    }
}
