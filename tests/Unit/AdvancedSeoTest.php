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
        
        $service = new BlogGeneratorService($scraper, $ai, $thumb, $sanitizer);
        
        $category = Category::factory()->create();
        
        // Create related blogs
        Blog::factory()->count(3)->create(['category_id' => $category->id]);
        
        $inputHtml = '
            <p>Intro paragraph text here.</p>
            <p>Second paragraph text.</p>
            <p>Third paragraph text.</p>
            <p>Reference to <a href="https://example.com">Source</a>.</p>
            <p>Bad link <a href="http://invalid-link-12345.com">Bad</a>.</p>
        ';
        
        // Note: Mocking isValidUrl involves protected method or network.
        // Since we can't easily mock protected method without reflection or subclass, 
        // we will rely on the real network check or HEADLESS check in the service.
        // example.com should pass. invalid-link-12345.com should fail.
        
        $output = $service->processSeoLinks($inputHtml, $category);
        
        // Check external validation
        $this->assertStringContainsString('href="https://example.com"', $output);
        $this->assertStringContainsString('rel="dofollow"', $output);
        
        // Check internal injection
        // We have 3 paragraphs + 2 others = 5 p. Internal links should inject.
        // Route naming in factory might differ from real app, but service uses `route('blog.show')`
        // Ensure route exists (it does from previous steps).
        
        // Check for internal links injection
        // We expect at least one internal link pointing to a blog
        $this->assertTrue(
            str_contains($output, 'blog/') || str_contains($output, 'http://localhost/blog/'), 
            "Internal links were not injected. Output: " . substr($output, 0, 500)
        );
    }
}
