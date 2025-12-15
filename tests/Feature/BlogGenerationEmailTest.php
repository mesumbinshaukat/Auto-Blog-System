<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Blog;
use App\Models\Category;
use App\Mail\BlogGenerationReport;
use App\Services\BlogGeneratorService;
use App\Services\ScrapingService;
use App\Services\AIService;
use App\Services\ThumbnailService;
use App\Services\TitleSanitizerService;
use App\Services\LinkDiscoveryService;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Testing\RefreshDatabase;

class BlogGenerationEmailTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_sends_success_email_when_blog_is_generated()
    {
        Mail::fake();
        Log::shouldReceive('info')->zeroOrMoreTimes();
        Log::shouldReceive('warning')->zeroOrMoreTimes();
        Log::shouldReceive('error')->zeroOrMoreTimes();

        $category = Category::factory()->create(['name' => 'Technology', 'slug' => 'technology']);

        // Mock services for successful generation
        $scraper = \Mockery::mock(ScrapingService::class);
        $scraper->shouldReceive('fetchTrendingTopics')->andReturn(['Unique Topic']);
        $scraper->shouldReceive('researchTopic')->andReturn('Research data');

        $ai = \Mockery::mock(AIService::class);
        $ai->shouldReceive('generateRawContent')->andReturn('<h1>Test Blog</h1><p>Content</p>');
        $ai->shouldReceive('optimizeAndHumanize')->andReturn(['content' => '<h1>Test Blog</h1><p>Content</p>', 'toc' => []]);

        $thumbnail = \Mockery::mock(ThumbnailService::class);
        $thumbnail->shouldReceive('generateThumbnail')->andReturn('path/to/thumbnail.jpg');

        $titleSanitizer = \Mockery::mock(TitleSanitizerService::class);
        $titleSanitizer->shouldReceive('sanitizeTitle')->andReturnUsing(function($title) { return $title; });
        $titleSanitizer->shouldReceive('fixBlog')->andReturnArg(0);

        $linkDiscovery = \Mockery::mock(LinkDiscoveryService::class);

        $service = new BlogGeneratorService($scraper, $ai, $thumbnail, $titleSanitizer, $linkDiscovery);

        // Generate blog
        $blog = $service->generateBlogForCategory($category);

        // Assert email was sent
        Mail::assertSent(BlogGenerationReport::class, function ($mail) use ($blog) {
            return $mail->blog !== null 
                && $mail->blog->id === $blog->id
                && $mail->error === null
                && $mail->status === 'Success'
                && str_contains($mail->envelope()->subject, 'Success');
        });
    }

    /** @test */
    public function it_sends_failure_email_when_generation_fails()
    {
        Mail::fake();
        Log::shouldReceive('info')->zeroOrMoreTimes();
        Log::shouldReceive('warning')->zeroOrMoreTimes();
        Log::shouldReceive('error')->zeroOrMoreTimes();

        $category = Category::factory()->create(['name' => 'Technology', 'slug' => 'technology']);

        // Mock services to throw exception
        $scraper = \Mockery::mock(ScrapingService::class);
        $scraper->shouldReceive('fetchTrendingTopics')->andReturn(['Unique Topic']);
        $scraper->shouldReceive('researchTopic')->andThrow(new \Exception('API quota exceeded'));

        $ai = \Mockery::mock(AIService::class);
        $thumbnail = \Mockery::mock(ThumbnailService::class);
        $titleSanitizer = \Mockery::mock(TitleSanitizerService::class);
        $linkDiscovery = \Mockery::mock(LinkDiscoveryService::class);

        $service = new BlogGeneratorService($scraper, $ai, $thumbnail, $titleSanitizer, $linkDiscovery);

        // Attempt generation (will fail)
        $blog = $service->generateBlogForCategory($category);

        // Assert email was sent with error
        Mail::assertSent(BlogGenerationReport::class, function ($mail) {
            return $mail->blog === null
                && $mail->error !== null
                && $mail->status === 'Failed'
                && str_contains($mail->envelope()->subject, 'Failed')
                && str_contains($mail->error->getMessage(), 'quota');
        });
    }

    /** @test */
    public function it_sends_duplicate_email_when_all_topics_are_duplicates()
    {
        Mail::fake();
        Log::shouldReceive('info')->zeroOrMoreTimes();
        Log::shouldReceive('warning')->zeroOrMoreTimes();
        Log::shouldReceive('error')->zeroOrMoreTimes();

        $category = Category::factory()->create(['name' => 'Technology', 'slug' => 'technology']);

        // Create duplicate blogs
        $topics = ['Topic 1', 'Topic 2', 'Topic 3'];
        foreach ($topics as $topic) {
            Blog::factory()->create(['title' => $topic, 'category_id' => $category->id]);
        }

        $scraper = \Mockery::mock(ScrapingService::class);
        $scraper->shouldReceive('fetchTrendingTopics')->andReturn($topics);

        $ai = \Mockery::mock(AIService::class);
        $thumbnail = \Mockery::mock(ThumbnailService::class);
        $titleSanitizer = \Mockery::mock(TitleSanitizerService::class);
        $linkDiscovery = \Mockery::mock(LinkDiscoveryService::class);

        $service = new BlogGeneratorService($scraper, $ai, $thumbnail, $titleSanitizer, $linkDiscovery);

        // Attempt generation
        $blog = $service->generateBlogForCategory($category);

        // Assert email was sent with duplicate flag
        Mail::assertSent(BlogGenerationReport::class, function ($mail) {
            return $mail->blog === null
                && $mail->isDuplicate === true
                && $mail->status === 'All Topics Duplicate'
                && count($mail->logs) > 0;
        });
    }

    /** @test */
    public function email_contains_comprehensive_logs()
    {
        Mail::fake();
        Log::shouldReceive('info')->zeroOrMoreTimes();
        Log::shouldReceive('warning')->zeroOrMoreTimes();
        Log::shouldReceive('error')->zeroOrMoreTimes();

        $category = Category::factory()->create(['name' => 'Technology', 'slug' => 'technology']);

        $scraper = \Mockery::mock(ScrapingService::class);
        $scraper->shouldReceive('fetchTrendingTopics')->andReturn(['Unique Topic']);
        $scraper->shouldReceive('researchTopic')->andReturn('Research data');

        $ai = \Mockery::mock(AIService::class);
        $ai->shouldReceive('generateRawContent')->andReturn('<h1>Test</h1><p>Content</p>');
        $ai->shouldReceive('optimizeAndHumanize')->andReturn(['content' => '<h1>Test</h1><p>Content</p>', 'toc' => []]);

        $thumbnail = \Mockery::mock(ThumbnailService::class);
        $thumbnail->shouldReceive('generateThumbnail')->andReturn('path/to/thumbnail.jpg');

        $titleSanitizer = \Mockery::mock(TitleSanitizerService::class);
        $titleSanitizer->shouldReceive('sanitizeTitle')->andReturnUsing(function($title) { return $title; });
        $titleSanitizer->shouldReceive('fixBlog')->andReturnArg(0);

        $linkDiscovery = \Mockery::mock(LinkDiscoveryService::class);

        $service = new BlogGeneratorService($scraper, $ai, $thumbnail, $titleSanitizer, $linkDiscovery);

        $blog = $service->generateBlogForCategory($category);

        // Assert email contains logs
        Mail::assertSent(BlogGenerationReport::class, function ($mail) {
            return count($mail->logs) > 0
                && collect($mail->logs)->contains(function ($log) {
                    return str_contains($log, 'Fetched') || str_contains($log, 'Selected topic');
                });
        });
    }
}
