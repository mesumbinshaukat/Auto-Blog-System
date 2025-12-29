<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Blog;
use App\Models\Category;
use App\Services\BlogGeneratorService;
use App\Services\AIService;
use App\Services\ThumbnailService;
use App\Services\ScrapingService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use App\Mail\BlogGenerationReport;
use App\Jobs\GenerateDailyBlogs;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class UltimateFallbackTest extends TestCase
{
    use RefreshDatabase;

    public function test_blog_generation_falls_back_to_scraped_content_on_api_failure()
    {
        Mail::fake();
        
        $category = Category::factory()->create(['name' => 'Technology', 'slug' => 'technology']);
        
        // Mock ScrapingService to return some research data
        $scraper = Mockery::mock(ScrapingService::class);
        $scraper->shouldReceive('fetchTrendingTopics')->andReturn(['Topic 1']);
        $scraper->shouldReceive('researchTopic')->andReturn("Cleaned overview from https://example.com:\nThis is some scraped content about Topic 1.");
        
        // Mock AIService to fail all AI but return scraped fallback
        $ai = Mockery::mock(AIService::class);
        $ai->shouldReceive('generateRawContent')->andReturn(''); // Simulate failure
        $ai->shouldReceive('generateScrapedFallback')->andReturn("<h1>Topic 1</h1><p>Scraped content about Topic 1.</p>");
        $ai->shouldReceive('optimizeAndHumanize')->andThrow(new \Exception("AI Optimization Failed"));
        $ai->shouldReceive('cleanupAIArtifacts')->andReturnArg(0);
        $ai->shouldReceive('scoreLinkRelevance')->andReturn(['score' => 0]); // Fallback in score
        
        // Mock ThumbnailService to return default image
        $thumbnail = Mockery::mock(ThumbnailService::class);
        $thumbnail->shouldReceive('generateThumbnail')->andReturn('images/default-thumbnail.webp');
        
        // Replace services in container
        $this->app->instance(ScrapingService::class, $scraper);
        $this->app->instance(AIService::class, $ai);
        $this->app->instance(ThumbnailService::class, $thumbnail);
        
        // Trigger generation
        $generator = app(BlogGeneratorService::class);
        $blog = $generator->generateBlogForCategory($category);
        
        $this->assertNotNull($blog);
        $this->assertStringContainsString('Scraped content about Topic 1', $blog->content);
        $this->assertEquals('images/default-thumbnail.webp', $blog->thumbnail_path);
        
        // Verify email was sent with fallback info (check logs in real scenario, here we just check if mailable was sent)
        Mail::assertSent(BlogGenerationReport::class);
    }

    public function test_daily_blog_limit_is_enforced()
    {
        Cache::flush();
        $category = Category::factory()->create(['name' => 'AI']);
        
        // Setup cache to look like 5 blogs were already sent
        Cache::put('daily_blog_date', now()->format('Y-m-d'));
        Cache::put('daily_blog_count', 5);
        
        // Run the job
        GenerateDailyBlogs::dispatchSync();
        
        // Assert that no new blogs were queued/created (count still 5)
        $this->assertEquals(5, Cache::get('daily_blog_count'));
    }
}
