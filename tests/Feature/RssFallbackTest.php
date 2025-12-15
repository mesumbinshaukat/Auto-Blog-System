<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\ScrapingService;
use Illuminate\Support\Facades\Log;
use Illuminate\Foundation\Testing\RefreshDatabase;
use GuzzleHttp\Client;
use GuzzleHttp\Handler\MockHandler;
use GuzzleHttp\HandlerStack;
use GuzzleHttp\Psr7\Response;
use GuzzleHttp\Exception\ClientException;
use GuzzleHttp\Psr7\Request;

class RssFallbackTest extends TestCase
{
    use RefreshDatabase;

    /** @test */
    public function it_uses_fallback_topics_when_all_rss_sources_fail()
    {
        // Mock Guzzle client to return 404 for all RSS requests
        $mock = new MockHandler([
            new ClientException('Not Found', new Request('GET', 'test'), new Response(404)),
            new ClientException('Not Found', new Request('GET', 'test'), new Response(404)),
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        // Create service with mocked client
        $service = new ScrapingService();
        $reflection = new \ReflectionClass($service);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($service, $client);

        // Capture logs
        Log::shouldReceive('info')->zeroOrMoreTimes();
        // Allow any usage of warning, but expect at least one about all sources failing
        Log::shouldReceive('warning')->atLeast()->times(1);

        // Fetch topics for games category
        $topics = $service->fetchTrendingTopics('games');

        // Assert fallback topics are returned
        $this->assertNotEmpty($topics);
        $this->assertGreaterThanOrEqual(5, count($topics));
        
        // Verify topics are from fallback list
        $this->assertContains('Top RPGs of the Year', $topics);
    }

    /** @test */
    public function it_logs_individual_rss_source_failures()
    {
        // Mock Guzzle client to return different errors
        $mock = new MockHandler([
            new Response(404), // First source: 404
            new Response(200, [], ''), // Second source: empty response
        ]);

        $handlerStack = HandlerStack::create($mock);
        $client = new Client(['handler' => $handlerStack]);

        $service = new ScrapingService();
        $reflection = new \ReflectionClass($service);
        $property = $reflection->getProperty('client');
        $property->setAccessible(true);
        $property->setValue($service, $client);

        // Expect specific warning logs
        Log::shouldReceive('info')->zeroOrMoreTimes();
        
        // Just verify we get warnings for the failures
        Log::shouldReceive('warning')->atLeast()->times(2);

        $topics = $service->fetchTrendingTopics('sports');

        $this->assertNotEmpty($topics);
    }

    /** @test */
    public function it_returns_expanded_fallback_topics_for_all_categories()
    {
        $service = new ScrapingService();
        $reflection = new \ReflectionClass($service);
        $method = $reflection->getMethod('fetchFallbackTopics');
        $method->setAccessible(true);

        $categories = ['technology', 'business', 'ai', 'games', 'sports', 'politics', 'science', 'health'];

        foreach ($categories as $category) {
            $fallbacks = $method->invoke($service, $category);
            
            // Assert at least 10 unique fallback topics per category
            $this->assertGreaterThanOrEqual(10, count($fallbacks), "Category $category should have at least 10 fallback topics");
            $this->assertEquals(count($fallbacks), count(array_unique($fallbacks)), "Category $category fallbacks should be unique");
        }
    }
}
