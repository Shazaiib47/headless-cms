<?php

namespace Database\Factories;

use App\Models\Page;
use Illuminate\Database\Eloquent\Factories\Factory;

class PageRevisionFactory extends Factory
{
    public function definition(): array
    {
        return [
            'page_id' => Page::factory(),
            'user_id' => null,
            'version' => 1,
            'action' => 'created',
            'snapshot' => [],
        ];
    }
}