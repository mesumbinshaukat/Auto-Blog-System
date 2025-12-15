<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Category;
use App\Services\BlogGeneratorService;
use App\Services\ScrapingService;
use App\Services\AIService;
use App\Services\ThumbnailService;
use App\Services\TitleSanitizerService;
use App\Services\LinkDiscoveryService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Mockery;

class CustomPromptTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_uses_custom_prompt_and_extracts_url()
    {
        $category = Category::factory()->create(['name' => 'Technology', 'slug' => 'technology']);
        $customPrompt = "Write about AI ethics. Check this: https://example.com/article";

        // Mock Scraper
        $scraper = Mockery::mock(ScrapingService::class);
        $scraper->shouldReceive('fetchTrendingTopics')->andReturn(['AI Ethics']);
        $scraper->shouldReceive('researchTopic')->andReturn('General research');
        // Expect scraping of the URL from prompt
        $scraper->shouldReceive('scrapeContent')
            ->with('https://example.com/article')
            ->once()
            ->andReturn('Scraped content from URL');

        // Mock AI
        $ai = Mockery::mock(AIService::class);
        $ai->shouldReceive('generateRawContent')
            ->withArgs(function ($topic, $cat, $research) {
                // Verify research contains custom prompt and scraped content
                return str_contains($research, 'Write about AI ethics') 
                    && str_contains($research, 'Scraped content from URL');
            })
            ->andReturn('<h1>AI Ethics</h1><p>Content</p>');
        $ai->shouldReceive('optimizeAndHumanize')->andReturn(['content' => '<h1>AI Ethics</h1>', 'toc' => []]);

        // Mock Other Services
        $thumbnail = Mockery::mock(ThumbnailService::class)->shouldIgnoreMissing();
        $titleSanitizer = Mockery::mock(TitleSanitizerService::class);
        $titleSanitizer->shouldReceive('sanitizeTitle')->andReturnArg(0);
        $titleSanitizer->shouldReceive('fixBlog')->andReturnArg(0);
        $linkDiscovery = Mockery::mock(LinkDiscoveryService::class)->shouldIgnoreMissing();

        $service = new BlogGeneratorService($scraper, $ai, $thumbnail, $titleSanitizer, $linkDiscovery);

        // Run generation with custom prompt
        $blog = $service->generateBlogForCategory($category, null, $customPrompt);

        $this->assertNotNull($blog);
        $this->assertEquals($customPrompt, $blog->custom_prompt);
    }

    /** @test */
    public function it_handles_failed_url_scraping_gracefully()
    {
        $category = Category::factory()->create(['name' => 'Technology', 'slug' => 'technology']);
        $customPrompt = "Check https://fail.com";

        $scraper = Mockery::mock(ScrapingService::class);
        $scraper->shouldReceive('fetchTrendingTopics')->andReturn(['Topic']);
        $scraper->shouldReceive('researchTopic')->andReturn('');
        // Expect scrape call but throw exception
        $scraper->shouldReceive('scrapeContent')
            ->andThrow(new \Exception('Scrape failed'));

        $ai = Mockery::mock(AIService::class);
        $ai->shouldReceive('generateRawContent')
            ->withArgs(function ($topic, $cat, $research) {
                return str_contains($research, 'Check https://fail.com') 
                    && str_contains($research, 'scraping failed');
            })
            ->andReturn('<h1>Content</h1>');
        $ai->shouldReceive('optimizeAndHumanize')->andReturn(['content' => '<h1>Content</h1>', 'toc' => []]);

        $thumbnail = Mockery::mock(ThumbnailService::class)->shouldIgnoreMissing();
        $titleSanitizer = Mockery::mock(TitleSanitizerService::class);
        $titleSanitizer->shouldReceive('sanitizeTitle')->andReturnArg(0);
        $titleSanitizer->shouldReceive('fixBlog')->andReturnArg(0);
        $linkDiscovery = Mockery::mock(LinkDiscoveryService::class)->shouldIgnoreMissing();

        $service = new BlogGeneratorService($scraper, $ai, $thumbnail, $titleSanitizer, $linkDiscovery);

        $service->generateBlogForCategory($category, null, $customPrompt);
    }
}
