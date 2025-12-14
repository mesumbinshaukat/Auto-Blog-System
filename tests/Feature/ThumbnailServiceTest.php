<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Http;
use App\Services\ThumbnailService;

class ThumbnailServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Storage::fake('public');
    }

    public function test_generates_thumbnail_svg()
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([
                'candidates' => [
                    [
                        'content' => [
                            'parts' => [
                                ['text' => '{"niche": "Technology", "topic": "AI", "visualStyle": "Modern", "primaryColor": "#000000", "secondaryColor": "#ffffff"}']
                            ]
                        ]
                    ]
                ]
            ], 200),
        ]);

        $service = new ThumbnailService();
        $path = $service->generateThumbnail('test-slug', 'Test Title', 'Test Content', 'Technology');

        $this->assertNotNull($path);
        $this->assertStringEndsWith('.svg', $path);
        Storage::disk('public')->assertExists($path);
    }

    public function test_fallback_generation()
    {
        // Simulate API failure
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::response([], 500),
        ]);

        $service = new ThumbnailService();
        $path = $service->generateThumbnail('fallback-slug', 'Test Title', 'Test Content', 'Technology');

        $this->assertNotNull($path);
        $this->assertStringEndsWith('.svg', $path);
        Storage::disk('public')->assertExists($path);
    }
}
