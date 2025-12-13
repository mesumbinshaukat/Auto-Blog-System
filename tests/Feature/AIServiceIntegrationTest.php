<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Services\AIService;
use Illuminate\Support\Facades\Config;

class AIServiceIntegrationTest extends TestCase
{
    /**
     * Test the AI Service connection.
     * Use --group=external to run this, as it consumes API quota.
     * 
     * @group external
     */
    public function test_ai_service_can_connect_to_hugging_face()
    {
        // Skip if no API key is present in environment
        if (!env('HUGGINGFACE_API_KEY')) {
            $this->markTestSkipped('HUGGINGFACE_API_KEY not found in .env');
        }

        $service = new AIService();
        // Simple prompt to save tokens/time
        $prompt = "Write a one sentence test.";
        
        $response = $service->generateRawContent($prompt);

        $this->assertNotEmpty($response, "AI Service returned empty response.");
        $this->assertIsString($response);
        // $this->assertStringContainsString('test', strtolower($response)); 
        // Loose check as AI output varies
    }

    /**
     * @group external
     */
    public function test_ai_service_can_connect_to_gemini_if_configured()
    {
        if (!env('GEMINI_API_KEY')) {
            $this->markTestSkipped('GEMINI_API_KEY not found in .env');
        }

        $service = new AIService();
        $input = "This is a test content with bad formatting.";
        
        $response = $service->optimizeAndHumanize($input);

        $this->assertNotEmpty($response);
        $this->assertIsString($response);
    }
}
