<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\ScrapingHubService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Illuminate\Foundation\Testing\RefreshDatabase;

class ScrapingHubServiceTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        Mail::fake();
        putenv('MASTER_KEY=test_key_123');
        putenv('SCRAPING_HUB_BASE_URL=https://scraping-hub-backend-only.vercel.app');
    }

    /** @test */
    public function it_returns_false_when_master_key_not_configured()
    {
        // Force env to null/empty for this test
        $_ENV['MASTER_KEY'] = '';
        putenv('MASTER_KEY=');
        
        $service = new ScrapingHubService();
        $this->assertFalse($service->isAvailable());
    }

    /** @test */
    public function it_checks_health_successfully()
    {
        Http::fake([
            'https://scraping-hub-backend-only.vercel.app/api/health' => Http::response(['status' => 'ok'], 200)
        ]);
        
        $service = new ScrapingHubService();
        $this->assertTrue($service->isAvailable());
        $this->assertTrue(Cache::has('scraping_hub_health'));
    }

    /** @test */
    public function it_disables_api_on_auth_failure()
    {
        Http::fake([
            'https://scraping-hub-backend-only.vercel.app/api/health' => Http::response('Unauthorized', 401)
        ]);
        
        $service = new ScrapingHubService();
        $this->assertFalse($service->isAvailable());
        $this->assertTrue(Cache::has('scraping_hub_disabled'));
        
        // Should not even call API again
        Http::assertSentCount(1);
        $this->assertFalse($service->isAvailable());
    }

    /** @test */
    public function it_fetches_news_successfully()
    {
        Http::fake([
            'https://scraping-hub-backend-only.vercel.app/api/health' => Http::response(['status' => 'ok'], 200),
            'https://scraping-hub-backend-only.vercel.app/api/news*' => Http::response([
                'success' => true,
                'data' => [
                    ['title' => 'AI News 1', 'url' => 'https://example.com/1'],
                    ['title' => 'AI News 2', 'url' => 'https://example.com/2'],
                ]
            ], 200)
        ]);
        
        $service = new ScrapingHubService();
        $results = $service->news('AI', 10);
        
        $this->assertNotNull($results);
        $this->assertCount(2, $results);
        $this->assertEquals('AI News 1', $results[0]['title']);
    }

    /** @test */
    public function it_scrapes_url_successfully()
    {
        Http::fake([
            'https://scraping-hub-backend-only.vercel.app/api/health' => Http::response(['status' => 'ok'], 200),
            'https://scraping-hub-backend-only.vercel.app/api/scrape*' => Http::response([
                'success' => true,
                'data' => [
                    'mainContent' => 'Full article content here...',
                    'title' => 'Article Title',
                    'description' => 'Short snippet...',
                    'links' => ['https://google.com'],
                    'image' => 'https://example.com/img.jpg'
                ]
            ], 200)
        ]);
        
        $service = new ScrapingHubService();
        $result = $service->scrape('https://example.com/article');
        
        $this->assertNotNull($result);
        $this->assertEquals('Article Title', $result['title']);
        $this->assertEquals('Full article content here...', $result['content']);
        $this->assertEquals('Short snippet...', $result['snippet']);
        $this->assertCount(1, $result['links']);
    }

    /** @test */
    public function it_retries_on_rate_limit()
    {
        Http::fake([
            'https://scraping-hub-backend-only.vercel.app/api/health' => Http::response(['status' => 'ok'], 200),
            'https://scraping-hub-backend-only.vercel.app/api/news*' => Http::sequence()
                ->push('Rate Limited', 429)
                ->push(['success' => true, 'data' => [['title' => 'Success']]], 200)
        ]);
        
        $service = new ScrapingHubService();
        $results = $service->news('test', 5);
        
        $this->assertNotNull($results);
        $this->assertEquals('Success', $results[0]['title']);
    }

    /** @test */
    public function it_handles_rss_parsing()
    {
        Http::fake([
            'https://scraping-hub-backend-only.vercel.app/api/health' => Http::response(['status' => 'ok'], 200),
            'https://scraping-hub-backend-only.vercel.app/api/rss*' => Http::response([
                'success' => true,
                'data' => [
                    'items' => [
                        ['title' => 'Feed Item 1', 'link' => 'https://feed.com/item1', 'contentSnippet' => 'Snippet text']
                    ]
                ]
            ], 200)
        ]);
        
        $service = new ScrapingHubService();
        $items = $service->rss('https://news.google.com/rss');
        
        $this->assertCount(1, $items);
        $this->assertEquals('Feed Item 1', $items[0]['title']);
        $this->assertEquals('https://feed.com/item1', $items[0]['url']);
        $this->assertEquals('Snippet text', $items[0]['snippet']);
    }
}
