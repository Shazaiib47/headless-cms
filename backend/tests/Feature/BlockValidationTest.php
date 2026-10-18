<?php

namespace Tests\Feature;

use App\Models\Media;
use App\Models\Page;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Testing\TestResponse;
use PHPUnit\Framework\Attributes\DataProvider;
use Tests\TestCase;

class BlockValidationTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();

        $this->actingAs(User::factory()->editor()->create());
    }

    private function block(string $type, array $data, string $id = 'b1'): array
    {
        return ['id' => $id, 'type' => $type, 'data' => $data];
    }

    private function createPage(array $blocks): TestResponse
    {
        return $this->postJson('/api/pages', [
            'title' => 'Block test',
            'slug' => 'block-test',
            'content' => $blocks,
        ]);
    }

    public static function unsafeUrls(): array
    {
        return [
            'javascript scheme' => ['javascript:alert(1)'],
            'data scheme' => ['data:text/html;base64,PHNjcmlwdD4='],
            'protocol relative' => ['//evil.example/path'],
            'backslash path' => ['/\\evil.example'],
            'ftp scheme' => ['ftp://example.com/file'],
            'whitespace in scheme' => ["java\tscript:alert(1)"],
            'space in host' => ['https://exa mple.com'],
            'double quote' => ['https://example.com/"onmouseover="x'],
        ];
    }

    public static function safeUrls(): array
    {
        return [
            'https with query and fragment' => ['https://example.com/a?b=1#c'],
            'http' => ['http://example.com'],
            'mailto' => ['mailto:hello@example.com'],
            'root path' => ['/'],
            'relative path' => ['/about/team?x=1'],
            'anchor' => ['#contact'],
            'subdomain with port' => ['https://shop.example.com:8443/x'],
        ];
    }

    public function test_accepts_every_block_type_and_returns_normalised_data(): void
    {
        $media = Media::factory()->create();

        $response = $this->createPage([
            $this->block('hero', [
                'heading' => 'Welcome', 'subheading' => 'Sub', 'image_id' => $media->id,
                'button_label' => 'Shop', 'button_url' => '/shop',
            ], 'b1'),
            $this->block('rich_text', ['markdown' => 'Hello **world**'], 'b2'),
            $this->block('image', ['image_id' => $media->id, 'alt' => 'A cat', 'caption' => 'Cute'], 'b3'),
            $this->block('cta', [
                'heading' => 'Join', 'text' => 'Today', 'button_label' => 'Sign up',
                'button_url' => 'https://example.com/join', 'style' => 'secondary',
            ], 'b4'),
            $this->block('card_grid', [
                'heading' => 'Cards',
                'cards' => [
                    ['title' => 'One', 'text' => 'First', 'image_id' => null, 'link_label' => 'More', 'link_url' => '/one'],
                    ['title' => 'Two'],
                ],
            ], 'b5'),
        ])->assertCreated();

        $stored = $response->json('data.content');

        $this->assertSame(['hero', 'rich_text', 'image', 'cta', 'card_grid'], array_column($stored, 'type'));
        $this->assertSame($media->id, $stored[0]['data']['image_id']);
        $this->assertSame('<p>Hello <strong>world</strong></p>', $stored[1]['data']['html']);
        $this->assertSame('secondary', $stored[3]['data']['style']);
        $this->assertNull($stored[4]['data']['cards'][1]['link_url']);
    }

    public function test_drops_undeclared_fields(): void
    {
        $block = $this->block('hero', [
            'heading' => 'Hi', 'onclick' => 'steal()', 'evil' => ['nested' => 1],
        ]);
        $block['extra'] = 'x';

        $stored = $this->createPage([$block])->assertCreated()->json('data.content.0');

        $this->assertSame(['id', 'type', 'data'], array_keys($stored));
        $this->assertArrayNotHasKey('onclick', $stored['data']);
        $this->assertArrayNotHasKey('evil', $stored['data']);
    }

    public function test_ignores_client_supplied_html(): void
    {
        $data = $this->createPage([
            $this->block('rich_text', ['markdown' => 'hi', 'html' => '<script>evil()</script>']),
        ])->assertCreated()->json('data.content.0.data');

        $this->assertSame('<p>hi</p>', $data['html']);
    }

    public function test_sanitises_rich_text_markdown(): void
    {
        $markdown = "Click [here](javascript:alert(1))\n\n<script>alert(2)</script>\n\n![x](https://evil.example/p.png)";

        $html = $this->createPage([$this->block('rich_text', ['markdown' => $markdown])])
            ->assertCreated()
            ->json('data.content.0.data.html');

        foreach (['javascript:', '<script', '<img', 'evil.example'] as $needle) {
            $this->assertStringNotContainsString($needle, $html);
        }
    }

    public function test_hero_requires_a_heading_within_the_length_limit(): void
    {
        $this->createPage([$this->block('hero', [])])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('content.0.data.heading');

        $this->createPage([$this->block('hero', ['heading' => str_repeat('a', 151)])])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('content.0.data.heading');
    }

    public function test_rich_text_requires_markdown_within_the_length_limit(): void
    {
        $this->createPage([$this->block('rich_text', [])])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('content.0.data.markdown');

        $this->createPage([$this->block('rich_text', ['markdown' => str_repeat('a', 20001)])])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('content.0.data.markdown');
    }

    #[DataProvider('unsafeUrls')]
    public function test_rejects_unsafe_urls(string $url): void
    {
        $this->createPage([$this->block('cta', [
            'heading' => 'Join', 'button_label' => 'Go', 'button_url' => $url,
        ])])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('content.0.data.button_url');
    }

    #[DataProvider('safeUrls')]
    public function test_accepts_safe_urls(string $url): void
    {
        $this->createPage([$this->block('cta', [
            'heading' => 'Join', 'button_label' => 'Go', 'button_url' => $url,
        ])])->assertCreated();
    }

    public function test_button_label_and_url_must_come_together(): void
    {
        $this->createPage([$this->block('hero', ['heading' => 'Hi', 'button_label' => 'Go'])])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('content.0.data.button_url');

        $this->createPage([$this->block('hero', ['heading' => 'Hi', 'button_url' => '/go'])])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('content.0.data.button_label');
    }

    public function test_cta_requires_its_fields_and_a_valid_style(): void
    {
        $this->createPage([$this->block('cta', [])])
            ->assertUnprocessable()
            ->assertJsonValidationErrors([
                'content.0.data.heading',
                'content.0.data.button_label',
                'content.0.data.button_url',
            ]);

        $this->createPage([$this->block('cta', [
            'heading' => 'Join', 'button_label' => 'Go', 'button_url' => '/go', 'style' => 'huge',
        ])])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('content.0.data.style');
    }

    public function test_card_grid_enforces_card_limits(): void
    {
        $this->createPage([$this->block('card_grid', ['cards' => []])])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('content.0.data.cards');

        $thirteen = array_fill(0, 13, ['title' => 'Card']);
        $this->createPage([$this->block('card_grid', ['cards' => $thirteen])])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('content.0.data.cards');

        $this->createPage([$this->block('card_grid', ['cards' => [['text' => 'No title']]])])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('content.0.data.cards.0.title');
    }

    public function test_card_link_label_and_url_must_come_together(): void
    {
        $this->createPage([$this->block('card_grid', ['cards' => [['title' => 'A', 'link_label' => 'More']]])])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('content.0.data.cards.0.link_url');

        $this->createPage([$this->block('card_grid', ['cards' => [['title' => 'A', 'link_url' => '/a']]])])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('content.0.data.cards.0.link_label');
    }

    public function test_rejects_duplicate_block_ids(): void
    {
        $this->createPage([
            $this->block('rich_text', ['markdown' => 'a'], 'dup'),
            $this->block('rich_text', ['markdown' => 'b'], 'dup'),
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('content.0.id');
    }

    public function test_rejects_more_than_fifty_blocks(): void
    {
        $blocks = array_map(
            fn (int $i) => $this->block('rich_text', ['markdown' => 'x'], "b{$i}"),
            range(1, 51),
        );

        $this->createPage($blocks)
            ->assertUnprocessable()
            ->assertJsonValidationErrors('content');
    }

    public function test_rejects_content_that_is_not_a_list(): void
    {
        $this->createPage(['a' => $this->block('rich_text', ['markdown' => 'x'])])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('content');
    }

    public function test_rejects_blocks_that_are_not_objects(): void
    {
        $this->createPage(['just a string'])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('content.0');
    }

    public function test_image_ids_must_reference_existing_media(): void
    {
        $this->createPage([$this->block('hero', ['heading' => 'Hi', 'image_id' => 999999])])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('content.0.data.image_id');

        $this->createPage([$this->block('image', ['alt' => 'x', 'image_id' => 999999])])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('content.0.data.image_id');
    }

    public function test_update_validates_and_normalises_content(): void
    {
        $page = Page::factory()->create();

        $this->patchJson("/api/pages/{$page->id}", [
            'content' => [$this->block('cta', ['heading' => 'x', 'button_label' => 'Go', 'button_url' => 'javascript:x'])],
        ])
            ->assertUnprocessable()
            ->assertJsonValidationErrors('content.0.data.button_url');

        $html = $this->patchJson("/api/pages/{$page->id}", [
            'content' => [$this->block('rich_text', ['markdown' => "ok\n\n<script>x()</script>"])],
        ])
            ->assertOk()
            ->json('data.content.0.data.html');

        $this->assertSame('<p>ok</p>', $html);
    }
}
