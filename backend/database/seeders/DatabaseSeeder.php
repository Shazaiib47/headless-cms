<?php

namespace Database\Seeders;

use App\Models\Page;
use App\Models\PageRevision;
use App\Models\User;
use Illuminate\Database\Seeder;

class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        if (app()->isProduction()) {
            $this->command->error('Demo seeding is disabled in production.');

            return;
        }

        // Local demo accounts only. The default UserFactory password applies.
        User::factory()->admin()->create(['name' => 'Demo Admin', 'email' => 'admin@example.com']);
        $editor = User::factory()->editor()->create(['name' => 'Demo Editor', 'email' => 'editor@example.com']);
        User::factory()->viewer()->create(['name' => 'Demo Viewer', 'email' => 'viewer@example.com']);

        $named = [
            ['title' => 'Home', 'slug' => 'home', 'published' => true],
            ['title' => 'About us', 'slug' => 'about', 'published' => true],
            ['title' => 'Contact', 'slug' => 'contact', 'published' => false],
        ];

        foreach ($named as $item) {
            $factory = Page::factory();

            if ($item['published']) {
                $factory = $factory->published();
            }

            $page = $factory->create([
                'title' => $item['title'],
                'slug' => $item['slug'],
                'created_by' => $editor->id,
                'updated_by' => $editor->id,
            ]);

            PageRevision::create([
                'page_id' => $page->id,
                'user_id' => $editor->id,
                'version' => 1,
                'action' => 'created',
                'snapshot' => $page->only(['title', 'slug', 'meta_title', 'meta_description', 'content']),
            ]);
        }

        Page::factory()->count(8)->create(['created_by' => $editor->id, 'updated_by' => $editor->id]);
        Page::factory()->published()->count(4)->create(['created_by' => $editor->id, 'updated_by' => $editor->id]);
    }
}