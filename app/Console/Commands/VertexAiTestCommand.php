<?php

namespace App\Console\Commands;

use App\Services\AIService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class VertexAiTestCommand extends Command
{
    protected $signature = 'blog:vertex-test';
    protected $description = 'Verify Vertex AI integration, OAuth2, and Token Tracking';

    public function handle(AIService $ai)
    {
        $this->info("--- Google Vertex AI Integration Test ---");

        // 1. Test OAuth2 Token
        $this->info("\n1. Testing OAuth2 Token generation...");
        // Use reflection to access protected method for testing
        $reflector = new \ReflectionClass($ai);
        $method = $reflector->getMethod('getVertexAccessToken');
        $method->setAccessible(true);
        $token = $method->invoke($ai);

        if ($token) {
            $this->info("✅ Access Token: " . substr($token, 0, 15) . "...");
        } else {
            $this->error("❌ Failed to generate OAuth2 Token");
            return Command::FAILURE;
        }

        // 2. Test API Connectivity
        $this->info("\n2. Testing Vertex AI API Call (Gemini)...");
        $prompt = "Hello Vertex AI! Tell me in 5 words why you are the best.";
        
        $methodCall = $reflector->getMethod('callVertexAIWithFallback');
        $methodCall->setAccessible(true);
        $result = $methodCall->invoke($ai, $prompt);

        if ($result['success']) {
            $this->info("✅ Response: " . $result['data']);
            $this->info("✅ Model Used: " . ($result['model'] ?? 'Unknown'));
            $this->info("✅ Location: " . ($result['location'] ?? 'Unknown'));
            $this->info("✅ Usage Metrics: " . json_encode($result['usage']));
        } else {
            $this->error("❌ API Call failed");
            return Command::FAILURE;
        }

        // 3. Test Token Tracking
        $this->info("\n3. Testing Daily Token Tracking...");
        $today = now()->format('Y-m-d');
        $used = Cache::get("vertex_ai_tokens_{$today}", 0);
        $limit = env('VERTEX_AI_DAILY_TOKEN_LIMIT', 50000);
        
        $this->info("📊 Tokens used today: $used / $limit");
        
        if ($used > 0) {
            $this->info("✅ Token tracking is working");
        } else {
            $this->warn("⚠️ Token tracking seems to be 0 (Check if usageMetadata was returned)");
        }

        // 4. Test Cleanup Engine (Unicode & Keywords)
        $this->info("\n4. Testing Content Cleanup Engine...");
        $malformed = "\\u003ch1\\u003eTest\\u003c/h1\\u003e <p>Leaked keyword.Groceries</p>";
        $cleaned = $ai->cleanupAIArtifacts($malformed, "Test Topic");
        
        $this->info("Original: $malformed");
        $this->info("Cleaned:  $cleaned");

        if (str_contains($cleaned, '<h1>') && !str_contains($cleaned, 'Groceries')) {
            $this->info("✅ Cleanup logic fixed both Unicode and leaked keywords");
        } else {
            if (!str_contains($cleaned, '<h1>')) $this->error("❌ Unicode decoding failed");
            if (str_contains($cleaned, 'Groceries')) $this->error("❌ Leaked keyword removal failed");
        }

        $this->info("\n--- Test Complete ---");
        return Command::SUCCESS;
    }
}
