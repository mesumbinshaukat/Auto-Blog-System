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
        
        // Clear cache before each test
        Cache::flush();
        
        // Fake mail to prevent actual emails
        Mail::fake();
    }

    /** @test */
    public function it_returns_false_when_master_key_not_configured()
    {
        config(['app.env' => 'testing']);
        putenv('MASTER_KEY=');
        
        $service = new ScrapingHubService();
        
        $this->assertFalse($service->isAvailable());
    }

    /** @test */
    public function it_checks_health_successfully()
    {
        putenv('MASTER_KEY=test_key_123');
        putenv('SCRAPING_HUB_BASE_URL=https://scraping-hub-backend-only.vercel.app');
        
        Http::fake([
            'https://scraping-hub-backend-only.vercel.app/' => Http::response('OK', 200)
        ]);
        
        $service = new ScrapingHubService();
        
        $this->assertTrue($service->isAvailable());
        
        // Verify health check is cached
        $this->assertTrue(Cache::has('scraping_hub_health'));
    }

    /** @test */
    public function it_handles_health_check_failure()
    {
        putenv('MASTER_KEY=test_key_123');
        
        Http::fake([
            'https://scraping-hub-backend-only.vercel.app/' => Http::response('Service Unavailable', 503)
        ]);
        
        $service = new ScrapingHubService();
        
        $this->assertFalse($service->isAvailable());
        
        // Verify email notification is sent (once per hour)
        Mail::assertSent(\Illuminate\Mail\Mailable::class);
    }

    /** @test */
    public function it_searches_successfully()
    {
        putenv('MASTER_KEY=test_key_123');
        
        Http::fake([
            'https://scraping-hub-backend-only.vercel.app/' => Http::response('OK', 200),
            'https://scraping-hub-backend-only.vercel.app/api/search*' => Http::response([
                'results' => [
                    ['title' => 'AI Article 1', 'url' => 'https://example.com/1', 'snippet' => 'AI snippet 1'],
                    ['title' => 'AI Article 2', 'url' => 'https://example.com/2', 'snippet' => 'AI snippet 2'],
                ]
            ], 200)
        ]);
        
        $service = new ScrapingHubService();
        $results = $service->search('AI technology', 10);
        
        $this->assertNotNull($results);
        $this->assertCount(2, $results);
        $this->assertEquals('AI Article 1', $results[0]['title']);
        
        // Verify results are cached
        $cacheKey = 'scraping_hub_search_' . md5('AI technology' . 10);
        $this->assertTrue(Cache::has($cacheKey));
    }

    /** @test */
    public function it_returns_null_on_search_failure()
    {
        putenv('MASTER_KEY=test_key_123');
        
        Http::fake([
            'https://scraping-hub-backend-only.vercel.app/' => Http::response('OK', 200),
            'https://scraping-hub-backend-only.vercel.app/api/search*' => Http::response('Not Found', 404)
        ]);
        
        $service = new ScrapingHubService();
        $results = $service->search('test query', 10);
        
        $this->assertNull($results);
    }

    /** @test */
    public function it_scrapes_url_successfully()
    {
        putenv('MASTER_KEY=test_key_123');
        
        Http::fake([
            'https://scraping-hub-backend-only.vercel.app/' => Http::response('OK', 200),
            'https://scraping-hub-backend-only.vercel.app/api/scrape*' => Http::response([
                'content' => 'This is the scraped content from the page.',
                'title' => 'Page Title',
                'snippet' => 'This is a snippet'
            ], 200)
        ]);
        
        $service = new ScrapingHubService();
        $result = $service->scrape('https://example.com/article');
        
        $this->assertNotNull($result);
        $this->assertEquals('This is the scraped content from the page.', $result['content']);
        $this->assertEquals('Page Title', $result['title']);
        $this->assertEquals('This is a snippet', $result['snippet']);
    }

    /** @test */
    public function it_validates_url_before_scraping()
    {
        putenv('MASTER_KEY=test_key_123');
        
        Http::fake([
            'https://scraping-hub-backend-only.vercel.app/' => Http::response('OK', 200)
        ]);
        
        $service = new ScrapingHubService();
        $result = $service->scrape('not-a-valid-url');
        
        $this->assertNull($result);
    }

    /** @test */
    public function it_truncates_long_content()
    {
        putenv('MASTER_KEY=test_key_123');
        
        $longContent = str_repeat('A', 3000);
        $longSnippet = str_repeat('B', 1500);
        
        Http::fake([
            'https://scraping-hub-backend-only.vercel.app/' => Http::response('OK', 200),
            'https://scraping-hub-backend-only.vercel.app/api/scrape*' => Http::response([
                'content' => $longContent,
                'title' => 'Test',
                'snippet' => $longSnippet
            ], 200)
        ]);
        
        $service = new ScrapingHubService();
        $result = $service->scrape('https://example.com');
        
        $this->assertEquals(2000, strlen($result['content']));
        $this->assertEquals(1000, strlen($result['snippet']));
    }

    /** @test */
    public function it_retries_on_rate_limit()
    {
        putenv('MASTER_KEY=test_key_123');
        
        Http::fake([
            'https://scraping-hub-backend-only.vercel.app/' => Http::response('OK', 200),
            'https://scraping-hub-backend-only.vercel.app/api/search*' => Http::sequence()
                ->push('Rate Limited', 429)
                ->push('Rate Limited', 429)
                ->push(['results' => [['title' => 'Success']]], 200)
        ]);
        
        $service = new ScrapingHubService();
        $results = $service->search('test', 5);
        
        // Should succeed after retries
        $this->assertNotNull($results);
        $this->assertCount(1, $results);
    }

    /** @test */
    public function it_returns_null_after_max_retries()
    {
        putenv('MASTER_KEY=test_key_123');
        
        Http::fake([
            'https://scraping-hub-backend-only.vercel.app/' => Http::response('OK', 200),
            'https://scraping-hub-backend-only.vercel.app/api/search*' => Http::response('Rate Limited', 429)
        ]);
        
        $service = new ScrapingHubService();
        $results = $service->search('test', 5);
        
        // Should return null after 3 attempts
        $this->assertNull($results);
    }

    /** @test */
    public function it_handles_empty_response_gracefully()
    {
        putenv('MASTER_KEY=test_key_123');
        
        Http::fake([
            'https://scraping-hub-backend-only.vercel.app/' => Http::response('OK', 200),
            'https://scraping-hub-backend-only.vercel.app/api/scrape*' => Http::response([
                'content' => '',
                'title' => '',
                'snippet' => ''
            ], 200)
        ]);
        
        $service = new ScrapingHubService();
        $result = $service->scrape('https://example.com');
        
        $this->assertNull($result);
    }
}
