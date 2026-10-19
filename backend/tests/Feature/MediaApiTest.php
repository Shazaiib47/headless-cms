<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Testing\TestResponse;
use Tests\TestCase;

class MediaApiTest extends TestCase
{
    use RefreshDatabase;

    private User $editor;

    protected function setUp(): void
    {
        parent::setUp();

        Storage::fake('public');
        $this->editor = User::factory()->editor()->create();
    }

    private function upload(UploadedFile $file, array $extra = [], ?User $as = null): TestResponse
    {
        return $this->actingAs($as ?? $this->editor)
            ->postJson('/api/media', ['file' => $file, ...$extra]);
    }

    private function imageBytes(string $format = 'jpeg', int $width = 40, int $height = 30): string
    {
        $image = imagecreatetruecolor($width, $height);
        imagefill($image, 0, 0, imagecolorallocate($image, 200, 40, 40));

        ob_start();
        match ($format) {
            'png' => imagepng($image),
            'webp' => imagewebp($image),
            default => imagejpeg($image),
        };

        return (string) ob_get_clean();
    }

    /** A PNG header that claims huge dimensions: detectable, but far too small to decode. */
    private function headerOnlyPng(int $width, int $height): string
    {
        $ihdr = pack('NN', $width, $height)."\x08\x02\x00\x00\x00";

        return "\x89PNG\r\n\x1a\n".pack('N', 13).'IHDR'.$ihdr.pack('N', crc32('IHDR'.$ihdr));
    }

    private function adminDeletes(int $id): TestResponse
    {
        return $this->actingAs(User::factory()->admin()->create())->deleteJson("/api/media/{$id}");
    }

    // ---- access control ----

    public function test_guests_receive_401_on_every_media_endpoint(): void
    {
        $media = Media::factory()->create();

        $this->getJson('/api/media')->assertUnauthorized();
        $this->getJson("/api/media/{$media->id}")->assertUnauthorized();
        $this->postJson('/api/media', ['file' => UploadedFile::fake()->image('a.jpg')])->assertUnauthorized();
        $this->patchJson("/api/media/{$media->id}", ['alt_text' => 'x'])->assertUnauthorized();
        $this->deleteJson("/api/media/{$media->id}")->assertUnauthorized();
    }

    public function test_viewers_can_list_and_view_media(): void
    {
        $media = Media::factory()->create();

        $this->actingAs(User::factory()->viewer()->create());

        $this->getJson('/api/media')->assertOk();
        $this->getJson("/api/media/{$media->id}")->assertOk();
    }

    public function test_viewers_cannot_upload_edit_or_delete(): void
    {
        $media = Media::factory()->create(['alt_text' => 'Original']);
        $viewer = User::factory()->viewer()->create();

        $this->upload(UploadedFile::fake()->image('a.jpg'), as: $viewer)->assertForbidden();
        $this->actingAs($viewer)->patchJson("/api/media/{$media->id}", ['alt_text' => 'Hacked'])->assertForbidden();
        $this->actingAs($viewer)->deleteJson("/api/media/{$media->id}")->assertForbidden();

        $this->assertDatabaseCount('media', 1);
        $this->assertSame('Original', $media->fresh()->alt_text);
    }

    // ---- uploading ----

    public function test_an_editor_can_upload_a_jpeg(): void
    {
        $this->upload(UploadedFile::fake()->image('Holiday Photo.jpg', 600, 400))
            ->assertCreated()
            ->assertJsonPath('data.mime_type', 'image/jpeg')
            ->assertJsonPath('data.width', 600)
            ->assertJsonPath('data.height', 400)
            ->assertJsonPath('data.original_name', 'Holiday Photo.jpg')
            ->assertJsonPath('data.uploaded_by', $this->editor->id)
            ->assertJsonStructure(['data' => ['id', 'url', 'thumbnail_url', 'size', 'alt_text', 'created_at']]);

        $media = Media::firstOrFail();

        Storage::disk('public')->assertExists($media->path);
        Storage::disk('public')->assertExists($media->thumbnail_path);
        $this->assertSame(Storage::disk('public')->size($media->path), $media->size);
        $this->assertStringEndsWith('.jpg', $media->path);
    }

    public function test_png_and_webp_images_are_accepted(): void
    {
        $this->upload(UploadedFile::fake()->image('logo.png', 50, 50))
            ->assertCreated()
            ->assertJsonPath('data.mime_type', 'image/png');

        $this->upload(UploadedFile::fake()->image('banner.webp', 50, 50))
            ->assertCreated()
            ->assertJsonPath('data.mime_type', 'image/webp');
    }

    public function test_gifs_are_rejected(): void
    {
        $this->upload(UploadedFile::fake()->image('animated.gif'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        $this->assertDatabaseCount('media', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_a_php_script_disguised_as_an_image_is_rejected(): void
    {
        $this->upload(UploadedFile::fake()->createWithContent('shell.jpg', '<?php system($_GET["c"]); ?>'))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        $this->assertDatabaseCount('media', 0);
        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_an_svg_with_a_script_is_rejected(): void
    {
        $svg = '<svg xmlns="http://www.w3.org/2000/svg"><script>alert(1)</script></svg>';

        $this->upload(UploadedFile::fake()->createWithContent('logo.png', $svg))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_a_corrupt_image_is_rejected(): void
    {
        $corrupt = "\xFF\xD8\xFF\xE0\x00\x10JFIF\x00".random_bytes(200);

        $this->upload(UploadedFile::fake()->createWithContent('broken.jpg', $corrupt))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        $this->assertDatabaseCount('media', 0);
    }

    public function test_code_hidden_in_a_valid_image_does_not_survive_re_encoding(): void
    {
        $polyglot = $this->imageBytes().'<?php system($_GET["c"]); ?>';

        $this->upload(UploadedFile::fake()->createWithContent('evil.jpg', $polyglot))->assertCreated();

        $media = Media::firstOrFail();
        $this->assertStringNotContainsString('<?php', Storage::disk('public')->get($media->path));
        $this->assertStringNotContainsString('<?php', Storage::disk('public')->get($media->thumbnail_path));
    }

    public function test_metadata_such_as_exif_is_stripped(): void
    {
        $jpeg = $this->imageBytes();
        $payload = "Exif\x00\x00SECRETMARKER";
        $tampered = substr($jpeg, 0, 2)."\xFF\xE1".pack('n', strlen($payload) + 2).$payload.substr($jpeg, 2);

        $this->assertStringContainsString('SECRETMARKER', $tampered);

        $this->upload(UploadedFile::fake()->createWithContent('photo.jpg', $tampered))->assertCreated();

        $media = Media::firstOrFail();
        $this->assertStringNotContainsString('SECRETMARKER', Storage::disk('public')->get($media->path));
    }

    public function test_the_stored_extension_comes_from_the_contents_not_the_filename(): void
    {
        $this->upload(UploadedFile::fake()->createWithContent('photo.png', $this->imageBytes('jpeg')))
            ->assertCreated()
            ->assertJsonPath('data.mime_type', 'image/jpeg');

        $this->upload(UploadedFile::fake()->createWithContent('photo.jpg', $this->imageBytes('png')))
            ->assertCreated()
            ->assertJsonPath('data.mime_type', 'image/png');

        $paths = Media::orderBy('id')->pluck('path')->all();

        $this->assertStringEndsWith('.jpg', $paths[0]);
        $this->assertStringEndsWith('.png', $paths[1]);
    }

    public function test_stored_names_are_random_and_the_original_name_is_only_display_text(): void
    {
        $this->upload(UploadedFile::fake()->createWithContent('../../etc/evil.jpg', $this->imageBytes()))
            ->assertCreated();

        $media = Media::firstOrFail();

        $this->assertSame('evil.jpg', $media->original_name);
        $this->assertMatchesRegularExpression('#^media/[0-9a-f]{32}\.jpg$#', $media->path);
        $this->assertMatchesRegularExpression('#^media/thumbs/[0-9a-f]{32}\.jpg$#', $media->thumbnail_path);
    }

    public function test_files_over_the_size_limit_are_rejected(): void
    {
        $this->upload(UploadedFile::fake()->create('big.jpg', 6000))
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');

        $this->assertDatabaseCount('media', 0);
    }

    public function test_the_pixel_limit_message_is_clear(): void
    {
        $message = $this->upload(UploadedFile::fake()->createWithContent('huge.png', $this->headerOnlyPng(5000, 5000)))
            ->assertUnprocessable()
            ->json('errors.file.0');

        $this->assertStringContainsString('too large', $message);
    }

    public function test_large_images_are_scaled_down(): void
    {
        $this->upload(UploadedFile::fake()->image('wide.jpg', 3000, 1000))
            ->assertCreated()
            ->assertJsonPath('data.width', 2560)
            ->assertJsonPath('data.height', 853);
    }

    public function test_a_thumbnail_is_created(): void
    {
        $this->upload(UploadedFile::fake()->image('big.jpg', 1200, 800))->assertCreated();

        $media = Media::firstOrFail();
        $size = getimagesizefromstring(Storage::disk('public')->get($media->thumbnail_path));

        $this->assertSame(480, $size[0]);
        $this->assertSame(320, $size[1]);
    }

    public function test_small_images_are_never_enlarged(): void
    {
        $this->upload(UploadedFile::fake()->image('small.jpg', 100, 50))->assertCreated();

        $media = Media::firstOrFail();
        $size = getimagesizefromstring(Storage::disk('public')->get($media->thumbnail_path));

        $this->assertSame([100, 50], [$size[0], $size[1]]);
    }

    public function test_png_transparency_is_preserved(): void
    {
        $image = imagecreatetruecolor(20, 20);
        imagealphablending($image, false);
        imagesavealpha($image, true);
        imagefill($image, 0, 0, imagecolorallocatealpha($image, 255, 0, 0, 127));

        ob_start();
        imagepng($image);
        $png = (string) ob_get_clean();

        $this->upload(UploadedFile::fake()->createWithContent('logo.png', $png))->assertCreated();

        $media = Media::firstOrFail();
        $copy = imagecreatefromstring(Storage::disk('public')->get($media->path));
        $alpha = (imagecolorat($copy, 5, 5) >> 24) & 0x7F;

        $this->assertGreaterThan(0, $alpha);
    }

    public function test_a_missing_file_is_a_validation_error(): void
    {
        $this->actingAs($this->editor)
            ->postJson('/api/media', [])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('file');
    }

    public function test_alt_text_can_be_set_on_upload_and_is_length_limited(): void
    {
        $this->upload(UploadedFile::fake()->image('a.jpg'), ['alt_text' => 'A red square'])
            ->assertCreated()
            ->assertJsonPath('data.alt_text', 'A red square');

        $this->upload(UploadedFile::fake()->image('b.jpg'), ['alt_text' => str_repeat('a', 201)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('alt_text');
    }

    public function test_files_are_removed_when_the_database_write_fails(): void
    {
        Media::creating(fn () => throw new \RuntimeException('database is down'));
        $this->withoutExceptionHandling();

        try {
            $this->upload(UploadedFile::fake()->image('a.jpg'));
            $this->fail('Expected the upload to fail.');
        } catch (\RuntimeException $e) {
            $this->assertSame('database is down', $e->getMessage());
        } finally {
            Media::flushEventListeners();
        }

        $this->assertSame([], Storage::disk('public')->allFiles());
    }

    public function test_uploads_are_rate_limited(): void
    {
        for ($i = 1; $i <= 30; $i++) {
            $this->upload(UploadedFile::fake()->image("p{$i}.jpg", 10, 10))->assertCreated();
        }

        $this->upload(UploadedFile::fake()->image('p31.jpg', 10, 10))->assertStatus(429);
    }

    // ---- listing and viewing ----

    public function test_the_list_is_paginated_newest_first_with_urls(): void
    {
        $ids = Media::factory()->count(3)->create()->pluck('id');

        $this->actingAs($this->editor)
            ->getJson('/api/media')
            ->assertOk()
            ->assertJsonCount(3, 'data')
            ->assertJsonPath('data.0.id', $ids->max())
            ->assertJsonStructure([
                'data' => [['id', 'original_name', 'alt_text', 'url', 'thumbnail_url', 'width', 'height']],
                'links',
                'meta',
            ]);
    }

    public function test_the_list_can_be_searched_by_name_and_alt_text(): void
    {
        Media::factory()->create(['original_name' => 'summer-banner.jpg', 'alt_text' => 'Beach']);
        Media::factory()->create(['original_name' => 'winter.jpg', 'alt_text' => 'Snowy hills']);

        $this->actingAs($this->editor);

        $this->getJson('/api/media?search=summer')->assertOk()->assertJsonCount(1, 'data');
        $this->getJson('/api/media?search=snowy')->assertOk()->assertJsonCount(1, 'data');
    }

    public function test_the_list_rejects_a_bad_page_size(): void
    {
        $this->actingAs($this->editor)
            ->getJson('/api/media?per_page=101')
            ->assertUnprocessable()
            ->assertJsonValidationErrors('per_page');
    }

    public function test_a_single_image_can_be_viewed_and_a_missing_one_is_404(): void
    {
        $media = Media::factory()->create();

        $this->actingAs($this->editor);

        $this->getJson("/api/media/{$media->id}")->assertOk()->assertJsonPath('data.id', $media->id);
        $this->getJson('/api/media/999999')->assertNotFound();
    }

    // ---- editing ----

    public function test_an_editor_can_change_and_clear_the_alt_text(): void
    {
        $media = Media::factory()->create(['alt_text' => 'Old']);

        $this->actingAs($this->editor)
            ->patchJson("/api/media/{$media->id}", ['alt_text' => 'New description'])
            ->assertOk()
            ->assertJsonPath('data.alt_text', 'New description');

        $this->patchJson("/api/media/{$media->id}", ['alt_text' => null])
            ->assertOk()
            ->assertJsonPath('data.alt_text', null);
    }

    public function test_an_over_long_alt_text_is_rejected_on_update(): void
    {
        $media = Media::factory()->create();

        $this->actingAs($this->editor)
            ->patchJson("/api/media/{$media->id}", ['alt_text' => str_repeat('a', 201)])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('alt_text');
    }

    // ---- deleting ----

    public function test_an_admin_can_delete_an_unused_image_and_its_files(): void
    {
        $id = $this->upload(UploadedFile::fake()->image('a.jpg'))->assertCreated()->json('data.id');
        $media = Media::findOrFail($id);

        $this->adminDeletes($id)->assertNoContent();

        $this->assertDatabaseMissing('media', ['id' => $id]);
        Storage::disk('public')->assertMissing($media->path);
        Storage::disk('public')->assertMissing($media->thumbnail_path);
    }

    public function test_editors_cannot_delete_images(): void
    {
        $id = $this->upload(UploadedFile::fake()->image('a.jpg'))->assertCreated()->json('data.id');

        $this->actingAs($this->editor)->deleteJson("/api/media/{$id}")->assertForbidden();

        $this->assertDatabaseHas('media', ['id' => $id]);
    }

    public function test_an_image_that_a_page_uses_cannot_be_deleted(): void
    {
        $ids = [];

        foreach (['hero', 'image', 'card', 'og'] as $kind) {
            $ids[$kind] = $this->upload(UploadedFile::fake()->image("{$kind}.jpg"))->assertCreated()->json('data.id');
        }

        Page::factory()->create([
            'title' => 'Landing page',
            'og_image_id' => $ids['og'],
            'content' => [
                ['id' => 'a', 'type' => 'hero', 'data' => ['heading' => 'Hi', 'image_id' => $ids['hero']]],
                ['id' => 'b', 'type' => 'image', 'data' => ['image_id' => $ids['image'], 'alt' => 'x']],
                ['id' => 'c', 'type' => 'card_grid', 'data' => ['cards' => [['title' => 'T', 'image_id' => $ids['card']]]]],
            ],
        ]);

        foreach ($ids as $id) {
            $this->adminDeletes($id)
                ->assertUnprocessable()
                ->assertJsonValidationErrors('media');

            $this->assertDatabaseHas('media', ['id' => $id]);
        }

        $message = $this->adminDeletes($ids['hero'])->json('errors.media.0');
        $this->assertStringContainsString('Landing page', $message);

        foreach (Media::all() as $media) {
            Storage::disk('public')->assertExists($media->path);
        }
    }

    public function test_an_image_can_be_deleted_once_no_page_uses_it(): void
    {
        $id = $this->upload(UploadedFile::fake()->image('a.jpg'))->assertCreated()->json('data.id');

        $page = Page::factory()->create([
            'content' => [['id' => 'a', 'type' => 'hero', 'data' => ['heading' => 'Hi', 'image_id' => $id]]],
        ]);

        $this->adminDeletes($id)->assertUnprocessable();

        $page->update(['content' => []]);

        $this->adminDeletes($id)->assertNoContent();
        $this->assertDatabaseMissing('media', ['id' => $id]);
    }
}
