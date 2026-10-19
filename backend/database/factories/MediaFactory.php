<?php

namespace Database\Factories;

use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

class MediaFactory extends Factory
{
    public function definition(): array
    {
        $name = Str::uuid().'.jpg';

        return [
            'uploaded_by' => null,
            'disk' => 'public',
            'path' => "media/{$name}",
            'thumbnail_path' => "media/thumbs/{$name}",
            'original_name' => fake()->word().'.jpg',
            'mime_type' => 'image/jpeg',
            'size' => fake()->numberBetween(10_000, 2_000_000),
            'width' => 1200,
            'height' => 800,
            'alt_text' => fake()->sentence(4),
        ];
    }
}
