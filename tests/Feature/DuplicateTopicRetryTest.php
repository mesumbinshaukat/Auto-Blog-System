<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\Blog;
use App\Models\Category;
use App\Services\BlogGeneratorService;
use App\Services\ScrapingService;
use App\Services\AIService;
use App\Services\ThumbnailService;
use App\Services\TitleSanitizerService;
use App\Services\LinkDiscoveryService;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Testing\RefreshDatabase;

class DuplicateTopicRetryTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_retries_up_to_10_times_when_all_topics_are_duplicates()
    {
        // Create category
        $category = Category::factory()->create(['name' => 'Technology', 'slug' => 'technology']);

        // Create 10+ blogs with titles matching all fallback topics
        $fallbackTopics = [
            'Future of AI in 2025',
            'Best Programming Languages for Developers',
            'Web Assembly Complete Guide',
            'Quantum Computing Breakthroughs',
            'Cybersecurity Best Practices',
            'Cloud Computing Architecture Trends',
            'DevOps and CI/CD Pipeline Optimization',
            'Blockchain Technology Beyond Cryptocurrency',
            'Edge Computing and IoT Integration',
            'Low-Code Development Platforms Revolution',
            '5G Technology Impact on Industries',
            'Serverless Architecture Best Practices'
        ];

        foreach ($fallbackTopics as $topic) {
            Blog::factory()->create([
                'title' => $topic,
                'category_id' => $category->id
            ]);
        }

        // Mock services
        $scraper = \Mockery::mock(ScrapingService::class);
        $scraper->shouldReceive('fetchTrendingTopics')
            ->andReturn($fallbackTopics);

        // Expect retry logs
        Log::shouldReceive('info')->atLeast()->times(10)
            ->with(\Mockery::pattern('/Retry \d+: Topic .* is duplicate/'));
        Log::shouldReceive('error')->once()
            ->with(\Mockery::pattern('/All 10 topic attempts were duplicates/'));
        Log::shouldReceive('info')->zeroOrMoreTimes();
        Log::shouldReceive('warning')->zeroOrMoreTimes();
        Log::shouldReceive('error')->zeroOrMoreTimes();

        // Create service
        $ai = \Mockery::mock(AIService::class);
        $thumbnail = \Mockery::mock(ThumbnailService::class);
        $titleSanitizer = \Mockery::mock(TitleSanitizerService::class);
        $linkDiscovery = \Mockery::mock(LinkDiscoveryService::class);

        $service = new BlogGeneratorService($scraper, $ai, $thumbnail, $titleSanitizer, $linkDiscovery);

        // Attempt generation
        $result = $service->generateBlogForCategory($category);

        // Assert null returned
        $this->assertNull($result);
    }

    /** @test */
    public function it_succeeds_on_first_non_duplicate_topic()
    {
        $category = Category::factory()->create(['name' => 'Technology', 'slug' => 'technology']);

        // Create only 2 duplicate blogs
        Blog::factory()->create(['title' => 'Future of AI in 2025', 'category_id' => $category->id]);
        Blog::factory()->create(['title' => 'Best Programming Languages', 'category_id' => $category->id]);

        $topics = [
            'Future of AI in 2025',
            'Best Programming Languages',
            'Unique Topic That Does Not Exist' // This should be selected
        ];

        $scraper = \Mockery::mock(ScrapingService::class);
        $scraper->shouldReceive('fetchTrendingTopics')->andReturn($topics);
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

        // Expect at most 3 retry logs (for the 2 duplicates)
        Log::shouldReceive('info')->atMost()->times(3)
            ->with(\Mockery::pattern('/Retry \d+: Topic .* is duplicate/'));
        Log::shouldReceive('info')->zeroOrMoreTimes();
        Log::shouldReceive('warning')->zeroOrMoreTimes();
        Log::shouldReceive('error')->zeroOrMoreTimes();

        $service = new BlogGeneratorService($scraper, $ai, $thumbnail, $titleSanitizer, $linkDiscovery);

        $result = $service->generateBlogForCategory($category);

        // Assert blog was created
        $this->assertNotNull($result);
        $this->assertInstanceOf(Blog::class, $result);
    }
}
