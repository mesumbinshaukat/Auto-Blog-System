<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\ScrapingService;
use App\Services\AIService;
use App\Services\BlogGeneratorService;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Mockery;

class ServicesTest extends TestCase
{
    use RefreshDatabase;

    public function test_scraping_service_returns_topics()
    {
        $service = new ScrapingService();
        // We can't easily mock Guzzle inside the service without dependency injection of Client,
        // but for this unit test we rely on the fallback logic or we'd refactor Service to accept Client.
        // Given the code, we test the fallback or basic functionality.
        
        $topics = $service->fetchTrendingTopics('technology');
        
        $this->assertIsArray($topics);
        $this->assertNotEmpty($topics);
    }

    public function test_ai_service_fallback()
    {
        // Mocking the Http facade is easiest here
        \Illuminate\Support\Facades\Http::fake([
            'api-inference.huggingface.co/*' => \Illuminate\Support\Facades\Http::response([
                'choices' => [
                    ['message' => ['content' => 'AI Generated Content']]
                ]
            ], 200),
            'router.huggingface.co/*' => \Illuminate\Support\Facades\Http::response([
                'choices' => [
                    ['message' => ['content' => 'AI Generated Content']]
                ]
            ], 200),
        ]);

        $ai = new AIService();
        // Updated signature: topic, category, researchData
        $content = $ai->generateRawContent('Test Prompt', 'Tech', 'Research');
        
        $this->assertEquals('AI Generated Content', $content);
    }

    public function test_blog_generator_service_creates_blog()
    {
        $category = Category::create(['name' => 'Tech', 'slug' => 'tech']);

        $scraperMock = Mockery::mock(ScrapingService::class);
        $scraperMock->shouldReceive('fetchTrendingTopics')->andReturn(['Test Topic']);
        $scraperMock->shouldReceive('researchTopic') // Correct method name
            ->andReturn('Research Data');

        $aiMock = Mockery::mock(AIService::class);
        $aiMock->shouldReceive('generateRawContent')->andReturn('<h1>Test Topic</h1><p>Content</p>');
        $aiMock->shouldReceive('optimizeAndHumanize')->andReturn('<h1>Test Topic</h1><p>Content Optimized</p>');
        $aiMock->shouldReceive('setSystemPrompt'); // Add if needed

        $thumbnailMock = Mockery::mock(\App\Services\ThumbnailService::class);
        $thumbnailMock->shouldReceive('generateThumbnail')->andReturn('thumbnails/test.svg');

        $generator = new BlogGeneratorService($scraperMock, $aiMock, $thumbnailMock);
        
        $blog = $generator->generateBlogForCategory($category);

        $this->assertNotNull($blog);
        $this->assertEquals('Test Topic', $blog->title);
        $this->assertStringContainsString('Content Optimized', $blog->content);
        $this->assertEquals($category->id, $blog->category_id);
    }
}
