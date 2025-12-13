<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\ScrapingService;

class ScrapingServiceTest extends TestCase
{
    /**
     * @group external
     */
    public function test_can_fetch_trending_topics_real()
    {
        $service = new ScrapingService();
        $topics = $service->fetchTrendingTopics('technology');
        
        $this->assertIsArray($topics);
        $this->assertNotEmpty($topics);
        // Ensure at least one topic is a string
        $this->assertIsString($topics[0]);
    }

    /**
     * @group external
     */
    public function test_can_scrape_content_from_known_url()
    {
        $service = new ScrapingService();
        // Use a reliable stable URL (e.g., example.com)
        $content = $service->scrapeContent('https://example.com');
        
        $this->assertStringContainsString('Example Domain', $content);
    }
}
