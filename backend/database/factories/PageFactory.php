<?php

namespace Database\Factories;

use App\Enums\PageStatus;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class PageFactory extends Factory
{
    public function definition(): array
    {
        $title = fake()->unique()->sentence(3);
        $paragraph = fake()->paragraph();

        return [
            'title' => $title,
            'slug' => Str::slug($title),
            'status' => PageStatus::Draft,
            'content' => [
                [
                    'id' => (string) Str::uuid(),
                    'type' => 'hero',
                    'data' => [
                        'heading' => $title,
                        'subheading' => fake()->sentence(),
                        'image_id' => null,
                        'button_label' => null,
                        'button_url' => null,
                    ],
                ],
                [
                    'id' => (string) Str::uuid(),
                    'type' => 'rich_text',
                    'data' => [
                        'markdown' => $paragraph,
                        'html' => '<p>'.e($paragraph).'</p>',
                    ],
                ],
            ],
            'meta_title' => $title,
            'meta_description' => fake()->sentence(12),
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes) => [
            'status' => PageStatus::Published,
            'published_at' => now(),
        ]);
    }
}
