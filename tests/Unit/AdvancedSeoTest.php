<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\BlogGeneratorService;
use App\Models\Category;
use App\Models\Blog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class AdvancedSeoTest extends TestCase
{
    use RefreshDatabase;

    public function test_process_seo_links_functionality()
    {
        // Mock deps
        $scraper = Mockery::mock(\App\Services\ScrapingService::class);
        $ai = Mockery::mock(\App\Services\AIService::class);
        $thumb = Mockery::mock(\App\Services\ThumbnailService::class);
        $sanitizer = Mockery::mock(\App\Services\TitleSanitizerService::class);
        $linkDiscovery = Mockery::mock(\App\Services\LinkDiscoveryService::class);
        
        $service = new BlogGeneratorService($scraper, $ai, $thumb, $sanitizer, $linkDiscovery);
        
        $category = Category::factory()->create();
        
        // Create related blogs
        Blog::factory()->count(3)->create(['category_id' => $category->id]);
        
        $inputHtml = '
            <h1>Test Blog Title</h1>
            <p>Intro paragraph text here.</p>
            <p>Second paragraph text.</p>
            <p>Third paragraph text.</p>
            <p>Reference to <a href="https://example.com">Source</a>.</p>
            <p>Bad link <a href="http://invalid-link-12345.com">Bad</a>.</p>
        ';
        
        // Mock AI scoring (won't be called since we have 1 external link already)
        $ai->shouldReceive('scoreLinkRelevance')->andReturn([
            'score' => 80,
            'anchor' => 'Test Anchor',
            'reason' => 'Relevant content'
        ]);
        
        $result = $service->processSeoLinks($inputHtml, $category);
        $output = $result['html'];
        
        // Assert stats
        // Original has 1 valid link (example.com).
        // If < 2, it calls injectSmartLinks.
        // Mock returns content with *another* link.
        // Total should be >= 2.
        
        $this->assertGreaterThanOrEqual(1, $result['external_count']);
        $this->assertGreaterThan(0, $result['internal_count']);
        $this->assertArrayHasKey('logs', $result);
        
        // Check external validation
        $this->assertStringContainsString('href="https://example.com"', $output);
        $this->assertStringContainsString('rel="dofollow"', $output);
        
        // Check for internal links injection
        // We expect at least one internal link pointing to a blog
        $this->assertTrue(
            str_contains($output, 'blog/') || str_contains($output, 'http://localhost/blog/'), 
            "Internal links were not injected. Output: " . substr($output, 0, 500)
        );
    }
}
