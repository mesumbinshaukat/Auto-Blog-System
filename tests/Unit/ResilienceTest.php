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
    public function test_gemini_backoff_on_429()
    {
        Http::fake([
            'generativelanguage.googleapis.com/*' => Http::sequence()
                ->push('{"error": {"code": 429, "message": "Rate limit"}}', 429)
                ->push('{"candidates": [{"content": {"parts": [{"text": "Success after retry"}]}}]}', 200)
        ]);

        $service = new AIService();
        
        // Inject fake key
        $reflection = new \ReflectionClass($service);
        $p1 = $reflection->getProperty('geminiKey');
        $p1->setAccessible(true);
        $p1->setValue($service, 'fake-key');
        
        // Call protected method callGeminiWithFallback directly via Reflection
        $method = $reflection->getMethod('callGeminiWithFallback');
        $method->setAccessible(true);
        $result = $method->invoke($service, "Test Prompt");
        
        // Should succeed eventually
        $this->assertTrue($result['success']);
        $this->assertEquals("Success after retry", $result['data']);
        
        // Verify 2 attempts were made
        Http::assertSentCount(2);
    }

    public function test_hf_quota_exceeded_fallback_to_svg()
    {
        // Mock HF returning 402
        Http::fake([
            'router.huggingface.co/*' => Http::response('{"error":"Payment Required"}', 402),
            'generativelanguage.googleapis.com/*' => Http::response('{"candidates": [{"content": {"parts": [{"text": "{\"primaryColor\":\"#333\"}"}]}}]}', 200)
        ]);

        // Mock ImageManager dependency
        $imageManager = new ImageManager(new Driver());
        
        $service = new ThumbnailService($imageManager);
        
        // Reflection to set HF token to ensure it tries HF
        $reflection = new \ReflectionClass($service);
        $property = $reflection->getProperty('hfToken');
        $property->setAccessible(true);
        $property->setValue($service, 'test_token');
        
        // Also set Gemini key for analysis step
        $pGem = $reflection->getProperty('geminiKey');
        $pGem->setAccessible(true);
        $pGem->setValue($service, 'fake-key');
        
        // Generate
        $path = $service->generateThumbnail("test-slug", "Test Title", "Content", "Tech", 1);
        
        // Should return a path (SVG fallback), not null, and not crash
        $this->assertNotNull($path);
    }

    public function test_wikipedia_api_search()
    {
        Http::fake([
            // Mock Search API
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
            
            // Mock The actual page scrape - Make sure content is long enough (> 50 chars)
            'en.wikipedia.org/wiki/Artificial_Intelligence' => Http::response('<html><body><article><p>Artificial Intelligence is an amazing field of study that involves creating smart machines capable of performing tasks that typically require human intelligence. This text is definitely longer than fifty characters.</p></article></body></html>', 200)
        ]);

        $service = new ScrapingService();
        $result = $service->researchTopic("AI");

        $this->assertStringContainsString("Source: Wikipedia (Artificial Intelligence)", $result);
        $this->assertStringContainsString("Artificial Intelligence is an amazing field", $result);
    }
}
