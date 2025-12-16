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
use App\Jobs\GenerateBlogJob;

class CustomPromptTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    /** @test */
    public function it_uses_custom_prompt_and_extracts_url()
    {
        $category = Category::create(['name' => 'Technology', 'slug' => 'technology']);
        $customPrompt = "Write about AI ethics. Check this: https://example.com/article";

        // Mock Scraper
        $scraper = Mockery::mock(ScrapingService::class);
        $scraper->shouldReceive('fetchTrendingTopics')->andReturn(['AI Ethics']);
        $scraper->shouldReceive('researchTopic')->andReturn('General research');
        $scraper->shouldReceive('scrapeContent')
            ->with('https://example.com/article')
            ->once()
            ->andReturn('Scraped content from URL');

        // Mock AI
        $ai = Mockery::mock(AIService::class)
            ->shouldAllowMockingProtectedMethods();
        $ai->shouldReceive('generateRawContent')
            ->withArgs(function ($topic, $cat, $research) {
                return str_contains($research, 'Write about AI ethics') 
                    && str_contains($research, 'Scraped content from URL')
                    && str_contains($research, 'IMPORTANT INSTRUCTIONS')
                    && str_contains($research, 'dofollow link');
            })
            ->andReturn('<h1>AI Ethics</h1><p>Content with <a href="https://example.com/article">link</a></p>');
        $ai->shouldReceive('optimizeAndHumanize')->andReturn(['content' => '<h1>AI Ethics</h1>', 'toc' => []]);
        $ai->shouldReceive('cleanupAIArtifacts')->andReturn('Cleaned Content');
        $ai->shouldReceive('generateKeywords')->andReturn(['ai', 'ethics']);

        // Bind mocks to container
        $this->app->instance(ScrapingService::class, $scraper);
        $this->app->instance(AIService::class, $ai);
        
        // Mock Other Services via container binding or letting them be real/null checks
        // For simplicity, we can mock them too if they are dependencies
        $this->app->instance(ThumbnailService::class, Mockery::mock(ThumbnailService::class)->shouldIgnoreMissing());
        $this->app->instance(LinkDiscoveryService::class, Mockery::mock(LinkDiscoveryService::class)->shouldIgnoreMissing());
        // TitleSanitizer is simple, let's mock strictly
        $ts = Mockery::mock(TitleSanitizerService::class);
        $ts->shouldReceive('sanitizeTitle')->andReturnArg(0);
        $ts->shouldReceive('fixBlog')->andReturnArg(0);
        $this->app->instance(TitleSanitizerService::class, $ts);

        // Resolve service from container
        $service = app(BlogGeneratorService::class);

        // Run generation with custom prompt
        $blog = $service->generateBlogForCategory($category, null, $customPrompt);

        $this->assertNotNull($blog);
        $this->assertEquals($customPrompt, $blog->custom_prompt);
    }

    /** @test */
    public function it_handles_failed_url_scraping_gracefully()
    {
        $category = Category::create(['name' => 'Technology', 'slug' => 'technology']);
        $customPrompt = "Check https://fail.com";

        $scraper = Mockery::mock(ScrapingService::class);
        $scraper->shouldReceive('fetchTrendingTopics')->andReturn(['Topic']);
        $scraper->shouldReceive('researchTopic')->andReturn('');
        $scraper->shouldReceive('scrapeContent')
            ->once()
            ->andThrow(new \Exception('Scrape failed'));

        $ai = Mockery::mock(AIService::class)
            ->shouldAllowMockingProtectedMethods();
        $ai->shouldReceive('generateRawContent')
            ->withArgs(function ($topic, $cat, $research) {
                return str_contains($research, 'Check https://fail.com') 
                    && str_contains($research, 'Unable to access site content for https://fail.com');
            })
            ->andReturn('<h1>Content</h1>');
        $ai->shouldReceive('optimizeAndHumanize')->andReturn(['content' => '<h1>Content</h1>', 'toc' => []]);
        $ai->shouldReceive('cleanupAIArtifacts')->andReturn('<h1>Content</h1>');
        $ai->shouldReceive('generateKeywords')->andReturn(['fail']);

        $this->app->instance(ScrapingService::class, $scraper);
        $this->app->instance(AIService::class, $ai);
        $this->app->instance(ThumbnailService::class, Mockery::mock(ThumbnailService::class)->shouldIgnoreMissing());
        $this->app->instance(LinkDiscoveryService::class, Mockery::mock(LinkDiscoveryService::class)->shouldIgnoreMissing());
        // Title Sanitizer
        $ts = Mockery::mock(TitleSanitizerService::class);
        $ts->shouldReceive('sanitizeTitle')->andReturnArg(0);
        $ts->shouldReceive('fixBlog')->andReturnArg(0);
        $this->app->instance(TitleSanitizerService::class, $ts);

        $service = app(BlogGeneratorService::class);

        $blog = $service->generateBlogForCategory($category, null, $customPrompt);
        $this->assertNotNull($blog);
    }
    
    /** @test */
    public function it_handles_quoted_urls_and_punctuation()
    {
        $category = Category::create(['name' => 'Technology', 'slug' => 'technology']);

        // Mock Scraper
        $scraperMock = Mockery::mock(ScrapingService::class);
        $scraperMock->shouldReceive('scrapeContent')
            ->once()
            ->with('https://example.com') // Expect CLEAN URL without quotes
            ->andReturn('Scraped content');
        $scraperMock->shouldReceive('researchTopic')->andReturn('Research data');

        // Mock AI
        $aiMock = Mockery::mock(AIService::class)
            ->shouldAllowMockingProtectedMethods();
        $aiMock->shouldReceive('generateRawContent')->andReturn('Content');
        $aiMock->shouldReceive('optimizeAndHumanize')->andReturn(['content' => 'Content', 'toc' => []]);
        $aiMock->shouldReceive('cleanupAIArtifacts')->andReturn('Content');
        $aiMock->shouldReceive('generateKeywords')->andReturn(['keyword']);

        // Bind via instance
        $this->app->instance(ScrapingService::class, $scraperMock);
        $this->app->instance(AIService::class, $aiMock);
        
        // Bind others
        $this->app->instance(ThumbnailService::class, Mockery::mock(ThumbnailService::class)->shouldIgnoreMissing());
        $this->app->instance(LinkDiscoveryService::class, Mockery::mock(LinkDiscoveryService::class)->shouldIgnoreMissing());
        $ts = Mockery::mock(TitleSanitizerService::class);
        $ts->shouldReceive('sanitizeTitle')->andReturnArg(0);
        $ts->shouldReceive('fixBlog')->andReturnArg(0);
        $this->app->instance(TitleSanitizerService::class, $ts);

        // Create job with prompt containing quotes
        $job = new GenerateBlogJob(
            $category->id, 
            'test-job-' . uniqid(), 
            'Check this site "https://example.com", it is good.'
        );
        
        // Resolve service which will use our bound mocks
        $service = app(BlogGeneratorService::class);
        $job->handle($service);
        
        $this->assertTrue(true);
    }
}
