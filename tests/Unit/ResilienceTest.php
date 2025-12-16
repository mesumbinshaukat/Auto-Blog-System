<?php

namespace Tests\Unit;

use Tests\TestCase;
use App\Services\AIService;
use App\Services\ThumbnailService;
use App\Services\ScrapingService;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Cache;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class ResilienceTest extends TestCase
{
    public function test_gemini_array_keys_rotation_on_429()
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push('{"error": {"code": 429, "message": "Rate limit"}}', 429)
                ->push('{"error": {"code": 429, "message": "Rate limit"}}', 429)
                ->push('{"candidates": [{"content": {"parts": [{"text": "Success with key 2"}]}}]}', 200)
        ]);

        $service = new AIService();
        
        // Inject fake keys array
        $reflection = new \ReflectionClass($service);
        $p1 = $reflection->getProperty('geminiKeys');
        $p1->setAccessible(true);
        $p1->setValue($service, ['fake-key-1', 'fake-key-2']);
        
        // Call protected method callGeminiWithFallback
        $method = $reflection->getMethod('callGeminiWithFallback');
        $method->setAccessible(true);
        $result = $method->invoke($service, "Test Prompt");
        
        // Should succeed with second key after first key exhausts retries
        $this->assertTrue($result['success']);
        $this->assertEquals("Success with key 2", $result['data']);
        
        // Verify 3 attempts were made (2 with key 1, 1 with key 2)
        Http::assertSentCount(3);
    }

    public function test_gemini_quota_exceeded_skips_to_next_key()
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push('{"error": {"code": 402, "message": "Quota exceeded"}}', 402)
                ->push('{"candidates": [{"content": {"parts": [{"text": "Success with key 2"}]}}]}', 200)
        ]);

        $service = new AIService();
        
        $reflection = new \ReflectionClass($service);
        $p1 = $reflection->getProperty('geminiKeys');
        $p1->setAccessible(true);
        $p1->setValue($service, ['fake-key-1', 'fake-key-2']);
        
        $method = $reflection->getMethod('callGeminiWithFallback');
        $method->setAccessible(true);
        $result = $method->invoke($service, "Test Prompt");
        
        // Should skip to key 2 immediately on 402
        $this->assertTrue($result['success']);
        
        // Check quota tracking
        $quotaMethod = $reflection->getMethod('getQuotaExceeded');
        $quotaMethod->setAccessible(true);
        $quotaExceeded = $quotaMethod->invoke($service);
        
        $this->assertContains('Gemini key_1', $quotaExceeded);
    }

    public function test_hf_array_keys_quota_exceeded_fallback()
    {
        // Mock HF returning 402 for first key, success for second
        Http::fake([
            'router.huggingface.co/*' => Http::sequence()
                ->push('{"error":"Payment Required"}', 402)
                ->push('{"choices": [{"message": {"content": "Success with HF key 2"}}]}', 200),
            'generativelanguage.googleapis.com/*' => Http::response('{"candidates": [{"content": {"parts": [{"text": "{\"primaryColor\":\"#333\"}"}]}}]}', 200)
        ]);

        $imageManager = new ImageManager(new Driver());
        $service = new ThumbnailService($imageManager);
        
        // Inject HF keys array
        $reflection = new \ReflectionClass($service);
        $property = $reflection->getProperty('hfKeys');
        $property->setAccessible(true);
        $property->setValue($service, ['test_token_1', 'test_token_2']);
        
        // Set Gemini key for analysis
        $pGem = $reflection->getProperty('geminiKeys');
        $pGem->setAccessible(true);
        $pGem->setValue($service, ['fake-key']);
        
        // Generate
        $path = $service->generateThumbnail("test-slug", "Test Title", "Content", "Tech", 1);
        
        // Should return a path (fallback to SVG if needed)
        $this->assertNotNull($path);
    }

    public function test_mediastack_api_integration()
    {
        // Set Mediastack key
        putenv('MEDIA_STACK_KEY=test_key');
        
        Http::fake([
            'api.mediastack.com/v1/news*' => Http::response(json_encode([
                'data' => [
                    ['title' => 'Breaking Tech News 1'],
                    ['title' => 'Breaking Tech News 2'],
                    ['title' => 'Breaking Tech News 3']
                ]
            ]), 200)
        ]);

        $service = new ScrapingService();
        $topics = $service->fetchTrendingTopics('technology');

        // Should get topics (either from Mediastack or RSS fallback)
        $this->assertNotEmpty($topics);
        $this->assertIsArray($topics);
        
        // Clean up
        putenv('MEDIA_STACK_KEY');
    }

    public function test_mediastack_fallback_to_rss_on_quota()
    {
        Http::fake([
            'api.mediastack.com/*' => Http::response('{"error": "Quota exceeded"}', 403),
            '*' => Http::response('<rss><channel><item><title>RSS Fallback Topic</title></item></channel></rss>', 200)
        ]);

        putenv('MEDIA_STACK_KEY=test_key');

        $service = new ScrapingService();
        $topics = $service->fetchTrendingTopics('technology');

        // Should fallback to RSS
        $this->assertNotEmpty($topics);
        
        putenv('MEDIA_STACK_KEY');
    }

    public function test_wikipedia_api_search()
    {
        Http::fake([
            'en.wikipedia.org/w/api.php*' => Http::response(json_encode([
                'query' => [
                    'search' => [
                        [
                            'title' => 'Artificial Intelligence',
                            'snippet' => 'AI is smart.'
                        ]
                    ]
                ]
            ]), 200),
            'en.wikipedia.org/wiki/Artificial_Intelligence' => Http::response('<html><body><article><p>Artificial Intelligence is an amazing field of study that involves creating smart machines capable of performing tasks that typically require human intelligence. This text is definitely longer than fifty characters.</p></article></body></html>', 200)
        ]);

        $service = new ScrapingService();
        $result = $service->researchTopic("AI");

        $this->assertStringContainsString("Source: Wikipedia (Artificial Intelligence)", $result);
        $this->assertStringContainsString("Artificial Intelligence is an amazing field", $result);
    }
}
