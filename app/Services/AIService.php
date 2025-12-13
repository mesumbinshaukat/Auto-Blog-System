<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIService
{
    protected $hfToken;
    protected $geminiKey;

    public function __construct()
    {
        $this->hfToken = env('HUGGINGFACE_API_KEY');
        $this->geminiKey = env('GEMINI_API_KEY');
    }

    public function generateRawContent(string $prompt): string
    {
        // Primary Model: EleutherAI/gpt-neo-1.3B
        $model = 'EleutherAI/gpt-neo-1.3B';
        $result = $this->callHuggingFace($model, $prompt);

        if (!$result) {
            // Fallback
            $result = $this->callHuggingFace('gpt2-large', $prompt);
        }

        return $result ?? "Failed to generate content.";
    }

    protected function callHuggingFace(string $model, string $prompt, int $retries = 3): ?string
    {
        $url = "https://api-inference.huggingface.co/models/$model";

        for ($i = 0; $i < $retries; $i++) {
            try {
                $response = Http::withToken($this->hfToken)
                    ->timeout(60)
                    ->post($url, [
                        'inputs' => $prompt,
                        'parameters' => [
                            'max_new_tokens' => 1500,
                            'temperature' => 0.7,
                            'return_full_text' => false
                        ]
                    ]);

                if ($response->successful()) {
                    $json = $response->json();
                    return $json[0]['generated_text'] ?? null;
                }
                
                // Exponential backoff
                sleep(pow(2, $i));

            } catch (\Exception $e) {
                Log::warning("HF API Attempt $i failed: " . $e->getMessage());
            }
        }

        return null;
    }

    public function optimizeAndHumanize(string $content): string
    {
        // Use Gemini for optimization
        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$this->geminiKey}";
        
        $prompt = "Please rewrite the following blog content to be more human-like, SEO optimized, and remove usage of em dashes or robotic phrasing. Format it with proper HTML headings and paragraphs: \n\n" . substr($content, 0, 10000);

        try {
            $response = Http::post($url, [
                'contents' => [
                    ['parts' => [['text' => $prompt]]]
                ]
            ]);

            if ($response->successful()) {
                $data = $response->json();
                return $data['candidates'][0]['content']['parts'][0]['text'] ?? $content;
            }
        } catch (\Exception $e) {
            Log::error("Gemini Optimization failed: " . $e->getMessage());
        }

        return $content;
    }
}
