<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;

class AIService
{
    protected $hfToken;
    protected $geminiKey;
    protected $primaryModel = 'allenai/Olmo-3-7B-Instruct';
    protected $fallbackModel = 'swiss-ai/Apertus-8B-Instruct-2509';

    public function __construct()
    {
        $this->hfToken = env('HUGGINGFACE_API_KEY');
        $this->geminiKey = env('GEMINI_API_KEY');
    }

    public function generateRawContent(string $topic, string $category, string $researchData = ''): string
    {
        // Check if API key is configured
        if (empty($this->hfToken)) {
            Log::warning("HUGGINGFACE_API_KEY not configured.");
            return $this->generateMockContent($topic);
        }

        // Build comprehensive prompt
        $systemPrompt = $this->buildSystemPrompt();
        $userPrompt = $this->buildUserPrompt($topic, $category, $researchData);

        // Try primary model
        Log::info("Generating content for topic: $topic");
        $result = $this->callHuggingFaceChatCompletion($this->primaryModel, $systemPrompt, $userPrompt);

        if (!$result) {
            Log::info("Primary model failed, trying fallback...");
            $result = $this->callHuggingFaceChatCompletion($this->fallbackModel, $systemPrompt, $userPrompt);
        }

        if (!$result) {
            Log::error("All AI models failed. Using mock content.");
            return $this->generateMockContent($topic);
        }

        // Enforce minimum word count
        $wordCount = str_word_count(strip_tags($result));
        if ($wordCount < 500) {
            Log::warning("Content too short ($wordCount words). Expanding...");
            $result = $this->expandContent($result, $topic, $systemPrompt);
        }

        // Enforce maximum word count
        if ($wordCount > 5000) {
            Log::warning("Content too long ($wordCount words). Truncating...");
            $result = $this->truncateContent($result, 5000);
        }

        return $result;
    }

    protected function buildSystemPrompt(): string
    {
        return "You are a professional blog writer and SEO expert. Generate comprehensive, well-structured blog posts in HTML format.

REQUIRED STRUCTURE:
- Start with <h1>Title</h1> (compelling, SEO-optimized)
- Introduction paragraph with <p> tags
- 4-6 main sections with <h2> headings
- Use <h3> for subsections where appropriate
- Each section should have 2-4 paragraphs in <p> tags
- Use <strong> for emphasis, <em> for italics
- Include <ul> or <ol> lists where relevant
- If topic involves comparisons/data, include HTML <table> with <thead> and <tbody>

CONTENT REQUIREMENTS:
- Length: 800-2000 words for standard topics, up to 3000+ for comprehensive guides
- Write naturally and conversationally (use contractions: don't, it's, we'll)
- Avoid robotic phrases, em dashes (—), and repetitive patterns
- Vary sentence structure and length
- Include specific examples and actionable insights
- Ensure keyword appears naturally 3-5 times (1-2% density)

SEO OPTIMIZATION:
- Use semantic keywords related to the topic
- Write compelling, benefit-focused content
- Include internal topic references where natural
- Ensure readability (short paragraphs, clear headings)

OUTPUT FORMAT:
Return ONLY the HTML content, no markdown code blocks or extra formatting.";
    }

    protected function buildUserPrompt(string $topic, string $category, string $researchData): string
    {
        $prompt = "Write a comprehensive blog post about \"$topic\" in the $category category.\n\n";
        
        if (!empty($researchData)) {
            $prompt .= "RESEARCH CONTEXT:\n$researchData\n\n";
        }

        $prompt .= "INSTRUCTIONS:
- Create an engaging, informative article that provides real value
- Use the research context to ensure accuracy
- Adapt length based on topic complexity (aim for 1000-1800 words)
- Include practical examples and actionable advice
- If the topic involves comparisons (e.g., 'Best X', 'Top Y'), include an HTML comparison table
- Ensure the content is SEO-optimized with natural keyword integration
- Write in a human, conversational tone

Begin writing the blog post now:";

        return $prompt;
    }

    protected function callHuggingFaceChatCompletion(string $model, string $systemPrompt, string $userPrompt, int $retries = 3): ?string
    {
        $url = "https://router.huggingface.co/v1/chat/completions";

        for ($i = 0; $i < $retries; $i++) {
            try {
                Log::info("Calling Hugging Face Chat API: $model (attempt " . ($i + 1) . "/$retries)");
                
                $response = Http::withToken($this->hfToken)
                    ->withOptions(['verify' => false])
                    ->timeout(120)
                    ->post($url, [
                        'model' => $model,
                        'messages' => [
                            ['role' => 'system', 'content' => $systemPrompt],
                            ['role' => 'user', 'content' => $userPrompt]
                        ],
                        'max_tokens' => 4000,
                        'temperature' => 0.7,
                        'stream' => false
                    ]);

                if ($response->successful()) {
                    $json = $response->json();
                    $content = $json['choices'][0]['message']['content'] ?? null;
                    
                    if ($content) {
                        $wordCount = str_word_count(strip_tags($content));
                        Log::info("Successfully generated content: $wordCount words");
                        return $this->cleanContent($content);
                    }
                }
                
                Log::warning("HF API returned unsuccessful response: " . $response->status());
                Log::warning("Response body: " . substr($response->body(), 0, 500));
                
                if ($i < $retries - 1) {
                    $sleepTime = pow(2, $i);
                    Log::info("Waiting {$sleepTime}s before retry...");
                    sleep($sleepTime);
                }

            } catch (\Exception $e) {
                Log::error("HF API Attempt " . ($i + 1) . " failed: " . $e->getMessage());
            }
        }

        return null;
    }

    protected function cleanContent(string $content): string
    {
        // Remove markdown code blocks if present
        $content = preg_replace('/```html\s*/', '', $content);
        $content = preg_replace('/```\s*$/', '', $content);
        $content = trim($content);
        
        return $content;
    }

    protected function expandContent(string $content, string $topic, string $systemPrompt): string
    {
        $expansionPrompt = "The following blog post about \"$topic\" is too short. Expand it by adding more detailed sections, examples, and insights. Maintain the same HTML structure and style.\n\nCurrent content:\n$content\n\nExpanded version:";
        
        $expanded = $this->callHuggingFaceChatCompletion($this->primaryModel, $systemPrompt, $expansionPrompt, 2);
        
        return $expanded ?: $content;
    }

    protected function truncateContent(string $content, int $maxWords): string
    {
        $words = str_word_count($content, 1);
        if (count($words) <= $maxWords) {
            return $content;
        }
        
        $truncated = implode(' ', array_slice($words, 0, $maxWords));
        return $truncated . '...</p>';
    }

    public function optimizeAndHumanize(string $content): string
    {
        // Skip if mock content
        if (strpos($content, 'This is a mock blog post') !== false) {
            return $content;
        }

        if (empty($this->geminiKey)) {
            Log::warning("GEMINI_API_KEY not configured. Skipping optimization.");
            return $content;
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$this->geminiKey}";
        
        $prompt = "Optimize and humanize the following blog content. Requirements:

1. Remove any robotic patterns, em dashes (—), or repetitive phrases
2. Ensure natural, conversational flow with varied sentence structure
3. Add contractions where appropriate (don't, it's, we'll)
4. Verify proper HTML structure (headings, paragraphs, lists, tables)
5. Ensure keyword density is 1-2% (natural integration)
6. Improve readability and engagement
7. Maintain all existing HTML tags and structure
8. Do NOT change the core message or facts

Return ONLY the optimized HTML content, no explanations.

Content to optimize:
" . substr($content, 0, 15000);

        try {
            Log::info("Calling Gemini API for optimization...");
            
            $response = Http::withOptions(['verify' => false])
                ->timeout(90)
                ->post($url, [
                    'contents' => [
                        ['parts' => [['text' => $prompt]]]
                    ]
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $optimized = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
                
                if ($optimized) {
                    Log::info("Successfully optimized content");
                    return $this->cleanContent($optimized);
                }
            }
            
            Log::warning("Gemini API returned unsuccessful response: " . $response->status());
            
        } catch (\Exception $e) {
            Log::error("Gemini Optimization failed: " . $e->getMessage());
        }

        return $content;
    }

    protected function generateMockContent(string $topic): string
    {
        return "<h1>$topic: A Comprehensive Guide</h1>

<p>This is a mock blog post generated because API keys are not configured or API calls failed. To generate real content, please add your HUGGINGFACE_API_KEY and GEMINI_API_KEY to the .env file.</p>

<h2>Introduction</h2>
<p>$topic is an important subject that deserves our attention. In this comprehensive guide, we'll explore the key aspects, benefits, and future implications of this topic. Understanding $topic is crucial for anyone looking to stay ahead in today's rapidly evolving landscape.</p>

<h2>Understanding $topic</h2>
<p>At its core, $topic represents a significant development in modern technology and business practices. It's essential to understand the fundamental concepts before diving deeper into the specifics.</p>

<p>The evolution of $topic has been remarkable over the past few years. What started as a niche concept has now become mainstream, with applications across various industries and sectors.</p>

<h3>Key Components</h3>
<p>There are several critical components that make up $topic. Each plays a vital role in the overall ecosystem and contributes to its effectiveness and adoption.</p>

<ul>
<li><strong>Component 1:</strong> Foundation elements that enable basic functionality</li>
<li><strong>Component 2:</strong> Advanced features that enhance capabilities</li>
<li><strong>Component 3:</strong> Integration points with existing systems</li>
</ul>

<h2>Benefits and Applications</h2>
<p>The practical applications of $topic are numerous and span across various industries. From improving efficiency to enabling new capabilities, the impact is substantial.</p>

<p>Organizations that have adopted $topic report significant improvements in productivity, cost savings, and customer satisfaction. The return on investment often exceeds initial expectations.</p>

<h3>Industry Impact</h3>
<p>Different sectors are experiencing transformation through the adoption of $topic. Healthcare, finance, education, and manufacturing are just a few examples of industries benefiting from these advancements.</p>

<table>
<thead>
<tr>
<th>Industry</th>
<th>Primary Benefit</th>
<th>Adoption Rate</th>
</tr>
</thead>
<tbody>
<tr>
<td>Healthcare</td>
<td>Improved patient outcomes</td>
<td>High</td>
</tr>
<tr>
<td>Finance</td>
<td>Enhanced security</td>
<td>Very High</td>
</tr>
<tr>
<td>Education</td>
<td>Personalized learning</td>
<td>Medium</td>
</tr>
</tbody>
</table>

<h2>Best Practices</h2>
<p>When implementing $topic, it's important to follow established best practices to ensure success. Here are some key recommendations:</p>

<ol>
<li>Start with a clear strategy and defined objectives</li>
<li>Invest in proper training and education for your team</li>
<li>Begin with pilot projects before full-scale deployment</li>
<li>Monitor metrics and adjust your approach based on results</li>
<li>Stay updated with the latest developments and trends</li>
</ol>

<h2>Future Outlook</h2>
<p>Looking ahead, $topic is poised for continued growth and evolution. Emerging trends suggest that we'll see even more innovative applications and improvements in the coming years.</p>

<p>Experts predict that the market for $topic will expand significantly, with new use cases emerging regularly. Organizations that embrace this technology early will have a competitive advantage.</p>

<h2>Conclusion</h2>
<p>In conclusion, $topic represents a significant opportunity for businesses and individuals alike. By understanding its principles and applications, we can better prepare for the future and leverage its potential.</p>

<p>Whether you're just getting started or looking to deepen your expertise, now is the perfect time to invest in learning more about $topic. The benefits are clear, and the opportunities are vast.</p>

<p><strong>Note:</strong> This is placeholder content. Configure your API keys to generate real, AI-powered blog posts with current information and insights.</p>";
    }
}
