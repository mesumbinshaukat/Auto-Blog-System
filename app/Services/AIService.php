<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AIService
{
    protected $hfToken;
    protected $hfTokenFallback;
    protected $geminiKey;
    protected $geminiKeyFallback;
    protected $openRouterKey;
    
    // Priority list of models to try
    protected $models = [
        'allenai/Olmo-3-7B-Instruct',          // Primary
        'swiss-ai/Apertus-8B-Instruct-2509',   // Fallback 1
        'microsoft/Phi-3-mini-4k-instruct',    // Fallback 2 (Fast, good for structure)
        'Qwen/Qwen2.5-7B-Instruct',            // Fallback 3 (Robust)
    ];

    public function __construct()
    {
        $this->hfToken = env('HUGGINGFACE_API_KEY');
        $this->hfTokenFallback = env('HUGGINGFACE_API_KEY_FALLBACK');
        $this->geminiKey = env('GEMINI_API_KEY');
        $this->geminiKeyFallback = env('GEMINI_API_KEY_FALLBACK');
        $this->openRouterKey = env('OPEN_ROUTER_KEY');
    }

    public function generateRawContent(string $topic, string $category, string $researchData = ''): string
    {
        // Check if API key is configured
        if (empty($this->hfToken)) {
            Log::warning("HUGGINGFACE_API_KEY not configured.");
            return $this->generateMockContent($topic);
        }

        // 0. Pre-generation: Keyword Research
        $keywords = $this->generateKeywords($topic, $category);
        Log::info("Target keywords: " . implode(', ', $keywords));
        $keywordString = implode(', ', $keywords);

        // Build comprehensive prompt
        $systemPrompt = $this->buildSystemPrompt();
        $userPrompt = $this->buildUserPrompt($topic, $category, $researchData, $keywordString);

        $result = null;

        // Try each model in the list
        foreach ($this->models as $model) {
            Log::info("Attempting generation with model: $model");
            $result = $this->callHuggingFaceChatCompletion($model, $systemPrompt, $userPrompt);
            
            if ($result) {
                Log::info("Success with model: $model");
                break;
            }
            
            Log::warning("Model $model failed, trying next...");
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

    /**
     * Call Gemini API with automatic fallback to secondary key and HuggingFace
     * 
     * @param string $prompt The user prompt
     * @param string $model The Gemini model to use (default: gemini-2.0-flash-exp)
     * @param int $maxRetries Max retries per key (default: 2)
     * @return array ['success' => bool, 'data' => mixed, 'source' => string]
     */
    protected function callGeminiWithFallback(string $prompt, string $model = 'gemini-2.0-flash-exp', int $maxRetries = 2): array
    {
        $keys = array_filter([$this->geminiKey, $this->geminiKeyFallback]);
        
        foreach ($keys as $keyIndex => $apiKey) {
            $keyLabel = $keyIndex === 0 ? 'primary' : 'fallback';
            
            for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                try {
                    $url = "https://generativelanguage.googleapis.com/v1beta/models/{$model}:generateContent?key={$apiKey}";
                    
                    $response = Http::withHeaders(['Content-Type' => 'application/json'])
                        ->timeout(30)
                        ->post($url, [
                            'contents' => [['parts' => [['text' => $prompt]]]]
                        ]);
                    
                    if ($response->successful()) {
                        $data = $response->json();
                        $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
                        
                        if (!empty($text)) {
                            Log::info("Gemini API success ({$keyLabel} key, attempt {$attempt})");
                            return ['success' => true, 'data' => $text, 'source' => "gemini_{$keyLabel}"];
                        }
                    }
                    
                    $status = $response->status();
                    Log::warning("Gemini {$keyLabel} key attempt {$attempt} failed: HTTP {$status}");
                    
                    // Don't retry on 404 (model not found) or 400 (bad request) - unless it's a quote error
                    if (in_array($status, [400, 404])) {
                        break;
                    }
                    
                    if ($attempt < $maxRetries) {
                        $delay = pow(2, $attempt); // Exponential backoff: 2s, 4s, 8s...
                        Log::warning("Retrying in {$delay}s...");
                        sleep($delay);
                    }
                    
                } catch (\Exception $e) {
                    Log::warning("Gemini {$keyLabel} key attempt {$attempt} exception: " . $e->getMessage());
                    if ($attempt < $maxRetries) {
                        $delay = pow(2, $attempt);
                        sleep($delay);
                    }
                }
            }
        }
        
        // All Gemini keys failed
        Log::info("All Gemini keys exhausted, returning failure");
        return ['success' => false, 'data' => null, 'source' => 'none'];
    }

    /**
     * Call OpenRouter API with fallback through multiple free models
     * 
     * @param string $prompt The user prompt
     * @param int $maxRetries Max retries per model (default: 2)
     * @return array ['success' => bool, 'data' => mixed, 'source' => string]
     */
    protected function callOpenRouterWithFallback(string $prompt, int $maxRetries = 2): array
    {
        if (empty($this->openRouterKey)) {
            Log::info("OpenRouter key not configured");
            return ['success' => false, 'data' => null, 'source' => 'none'];
        }

        $models = [
            'deepseek/deepseek-chat:free',
            'mistralai/mistral-7b-instruct:free',
            'nousresearch/hermes-3-llama-3.1-8b:free'
        ];
        
        foreach ($models as $modelIndex => $model) {
            $modelLabel = ['deepseek', 'mistral', 'hermes'][$modelIndex];
            
            for ($attempt = 1; $attempt <= $maxRetries; $attempt++) {
                try {
                    Log::info("Calling OpenRouter with model: $model (attempt $attempt)");
                    
                    $response = Http::withHeaders([
                        'Authorization' => 'Bearer ' . $this->openRouterKey,
                        'Content-Type' => 'application/json',
                        'HTTP-Referer' => env('APP_URL', 'http://localhost'),
                        'X-Title' => 'Auto Blog System'
                    ])->timeout(30)->post('https://openrouter.ai/api/v1/chat/completions', [
                        'model' => $model,
                        'messages' => [
                            ['role' => 'user', 'content' => $prompt]
                        ]
                    ]);
                    
                    if ($response->successful()) {
                        $data = $response->json();
                        $text = $data['choices'][0]['message']['content'] ?? '';
                        
                        if (!empty($text)) {
                            Log::info("OpenRouter success with $modelLabel model");
                            return ['success' => true, 'data' => $text, 'source' => "openrouter_$modelLabel"];
                        }
                    }
                    
                    $status = $response->status();
                    Log::warning("OpenRouter $modelLabel attempt $attempt failed: HTTP $status");
                    
                    // Handle 429 with exponential backoff
                    if ($status === 429 && $attempt < $maxRetries) {
                        $delay = pow(2, $attempt);
                        Log::warning("OpenRouter rate limit, waiting {$delay}s");
                        sleep($delay);
                    } elseif (in_array($status, [400, 404])) {
                        break; // Don't retry on bad request or not found
                    }
                    
                } catch (\Exception $e) {
                    Log::error("OpenRouter $modelLabel attempt $attempt exception: " . $e->getMessage());
                    if ($attempt < $maxRetries) {
                        $delay = pow(2, $attempt);
                        sleep($delay);
                    }
                }
            }
            
            Log::warning("All retries exhausted for OpenRouter model: $model");
        }
        
        Log::warning("All OpenRouter models exhausted");
        return ['success' => false, 'data' => null, 'source' => 'none'];
    }

    protected function generateKeywords(string $topic, string $category): array
    {
        // Use Gemini (preferred) or basic generation for keywords
        // Simple heuristic fallback if no AI: just split topic and add category
        
        if (empty($this->geminiKey) && empty($this->geminiKeyFallback)) {
             return [Str::slug($topic, ' '), strtolower($category), 'guide', 'tips'];
        }

        $prompt = "Suggest 1 primary focus keyword and 3 secondary long-tail keywords for a blog post about \"$topic\" in category \"$category\". Return ONLY the keywords as a comma-separated list.";
        
        $result = $this->callGeminiWithFallback($prompt);
        $result = ['success' => false]; // Initialize result
        if (!empty($this->geminiKey) || !empty($this->geminiKeyFallback)) {
            $result = $this->callGeminiWithFallback($prompt);
        } else {
            Log::info("No Gemini keys configured for keyword generation.");
        }

        if ($result['success']) {
            $keywords = array_map('trim', explode(',', $result['data']));
            return array_slice($keywords, 0, 4);
        }
        
        // Try OpenRouter if Gemini failed or was not configured
        if (!empty($this->openRouterKey)) {
            $openRouterResult = $this->callOpenRouterWithFallback($prompt);
            if ($openRouterResult['success']) {
                $keywords = array_map('trim', explode(',', $openRouterResult['data']));
                return array_slice($keywords, 0, 4);
            }
        }
        
        // Final fallback: basic heuristic if all AI services failed
        Log::warning("All AI services failed for keyword generation, using heuristic");
        return [Str::slug($topic, ' '), strtolower($category), 'guide', 'tips'];
    }

    /**
     * Score link relevance using AI
     * 
     * @param string $topic Blog topic
     * @param string $url URL to score
     * @param string $snippet Content snippet from URL
     * @return array ['score' => int, 'anchor' => string, 'reason' => string]
     */
    public function scoreLinkRelevance(string $topic, string $url, string $snippet): array
    {
        $prompt = "Score the relevance of this external source to a blog post about \"$topic\" on a scale of 0-100.

URL: $url
Snippet: $snippet

Provide your response in this exact format:
SCORE: [number 0-100]
ANCHOR: [suggested anchor text if score >75, otherwise 'N/A']
REASON: [brief explanation]";

        $result = $this->callGeminiWithFallback($prompt);
        
        if ($result['success']) {
            $text = $result['data'];
            
            // Parse response
            $score = 0;
            $anchor = '';
            $reason = '';
            
            if (preg_match('/SCORE:\s*(\d+)/', $text, $matches)) {
                $score = (int)$matches[1];
            }
            
            if (preg_match('/ANCHOR:\s*(.+?)(?:\n|REASON:)/s', $text, $matches)) {
                $anchor = trim($matches[1]);
                if ($anchor === 'N/A') {
                    $anchor = '';
                }
            }
            
            if (preg_match('/REASON:\s*(.+)/s', $text, $matches)) {
                $reason = trim($matches[1]);
            }
            
            return [
                'score' => $score,
                'anchor' => $anchor,
                'reason' => $reason
            ];
        }
        
        // Fallback: Local Keyword Matching if AI fails
        // Extract significant words from topic (min 4 chars, ignore common words)
        $stopWords = ['about', 'this', 'that', 'with', 'from', 'what', 'when', 'where', 'which', 'video', 'news', 'guide', 'review', 'best', 'year', '2024', '2025'];
        $topicWords = array_filter(str_word_count(strtolower($topic), 1), function($word) use ($stopWords) {
            return strlen($word) > 3 && !in_array($word, $stopWords);
        });
        
        $snippetLower = strtolower($snippet);
        $urlLower = strtolower($url);
        
        $matches = 0;
        $matchedWords = [];
        
        foreach ($topicWords as $word) {
            if (str_contains($snippetLower, $word) || str_contains($urlLower, $word)) {
                $matches++;
                $matchedWords[] = $word;
            }
        }
        
        // Calculate basic score based on keyword density/presence
        $matchRatio = count($topicWords) > 0 ? $matches / count($topicWords) : 0;
        
        if ($matchRatio >= 0.3) { // At least 30% of topic keywords found
            return [
                'score' => 80, // High enough to pass >75 check
                'anchor' => !empty($matchedWords) ? ucwords(implode(' ', array_slice($matchedWords, 0, 3))) : 'Read more',
                'reason' => 'Local fallback: Keyword match found (' . implode(', ', $matchedWords) . ')'
            ];
        }

        return [
            'score' => 0,
            'anchor' => '',
            'reason' => 'AI scoring unavailable and local keyword check failed'
        ];
    }

    protected function buildSystemPrompt(): string
    {
        return "You are a professional blog writer and SEO expert. Generate JUICY, ENGAGING, and comprehensive blog posts that captivate readers and rank well.

CONTENT QUALITY - MAKE IT JUICY:
- **Vivid Descriptions**: Use sensory language, paint pictures with words, include real-world examples
- **Engaging Style**: Conversational tone, use 'you', 'we', contractions, rhetorical questions
- **Comprehensive Coverage**: Deep dive into topics with expert insights and practical advice
- **Real Examples**: Include case studies, statistics, expert quotes, concrete scenarios
- **Enthusiasm**: Write like you're explaining something fascinating to a curious friend

REQUIRED STRUCTURE:
- Start with \u003ch1\u003eTitle\u003c/h1\u003e (compelling, uses primary keyword naturally)
- Hook readers in first paragraph with \u003cp\u003e tags (include primary keyword in first 100 words)
- 4-6 main sections with \u003ch2\u003e headings (Use questions or natural queries for Voice Search/AI Overviews)
- Use \u003ch3\u003e for subsections
- EACH SECTION must have multiple SHORT paragraphs (2-4 sentences each)
- Use \u003cstrong\u003e for emphasis, \u003cem\u003e for italics
- Include \u003cul\u003e or \u003col\u003e lists for scannability
- If topic involves comparisons/data, include HTML \u003ctable class=\"comparison-table\"\u003e with \u003cthead\u003e and \u003ctbody\u003e

SEO & AISEO REQUIREMENTS:
- **E-E-A-T**: Write with authority and expertise. Use specific examples, data points, expert consensus
- **Keywords**: 
    - Integrate 1 Primary Focus Keyword (provided below) naturally (1-2% density)
    - Integrate 2-3 Long-tail keywords naturally
    - Optimize for \"AI Overviews\": Answer 'what', 'how', 'why' questions directly in first sentence of sections
- **External Links**: 
    - Include 2-4 dofollow external links to authoritative sites (Wikipedia, BBC, IEEE, Gov/Edu)
    - Place links naturally with descriptive anchor text
    - Example: \u003ca href='https://www.wikipedia.org/...' rel='dofollow' target='_blank'\u003edescriptive anchor\u003c/a\u003e
- **Formatting**: Maximize readability. No walls of text. Break up content visually.

TONE: Make it juicy and memorable! Be enthusiastic, informative, and engaging. Your goal is to make readers think \"Wow, this is exactly what I needed to know!\"

OUTPUT FORMAT: Return ONLY the HTML content, no markdown code blocks.";
    }

    protected function buildUserPrompt(string $topic, string $category, string $researchData, string $keywords = ''): string
    {
        $prompt = "Write a comprehensive blog post about \"$topic\" in the $category category.\n";
        if ($keywords) {
            $prompt .= "Target Keywords: $keywords\n\n";
        }
        
        if (!empty($researchData)) {
            $prompt .= "RESEARCH CONTEXT (Use as inspiration, do NOT copy):\n$researchData\n\n";
            $prompt .= "IMPORTANT: This is real news/data. IMPROVISE and REPHRASE everything in your own words. Add original insights. Do not plagiarize.\n\n";
        }

        $prompt .= "INSTRUCTIONS:
- Create an engaging, informative article.
- Use the research context but maintain originality.
- **Limit paragraphs to 2-4 sentences**. No walls of text.
- If comparison topic, use <table class='comparison-table'>.
- Natural keyword integration.
- Conversational tone.

Begin writing the blog post now:";

        return $prompt;
    }

    protected function callHuggingFaceChatCompletion(string $model, string $systemPrompt, string $userPrompt, int $retries = 3): ?string
    {
        $url = "https://router.huggingface.co/v1/chat/completions";
        
        // Try with primary key first, then fallback
        $tokens = array_filter([$this->hfToken, $this->hfTokenFallback]);
        
        foreach ($tokens as $tokenIndex => $token) {
            $tokenLabel = $tokenIndex === 0 ? 'primary' : 'fallback';
            Log::info("Trying HuggingFace API with {$tokenLabel} key for model: $model");

            for ($i = 0; $i < $retries; $i++) {
                try {
                    Log::info("Calling Hugging Face Chat API: $model ({$tokenLabel} key, attempt " . ($i + 1) . "/$retries)");
                    
                    $response = Http::withToken($token)
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
                            Log::info("Successfully generated content with {$tokenLabel} key: $wordCount words");
                            return $this->cleanContent($content);
                        }
                    }
                    
                    Log::warning("HF API ({$tokenLabel} key) returned unsuccessful response: " . $response->status());
                    Log::warning("Response body: " . substr($response->body(), 0, 500));
                    
                    if ($i < $retries - 1) {
                        $sleepTime = pow(2, $i);
                        Log::info("Waiting {$sleepTime}s before retry...");
                        sleep($sleepTime);
                    }

                } catch (\Exception $e) {
                    Log::error("HF API ({$tokenLabel} key) Attempt " . ($i + 1) . " failed: " . $e->getMessage());
                    if ($i < $retries - 1) {
                        $sleepTime = pow(2, $i);
                        sleep($sleepTime);
                    }
                }
            }
            
            Log::warning("All retries exhausted for {$tokenLabel} HuggingFace key on model: $model");
        }
        
        Log::warning("All HuggingFace API keys exhausted for model: $model");
        return null;
    }

    protected function cleanContent(string $content): string
    {
        // Remove markdown code blocks if present
        $content = preg_replace('/```html\s*/', '', $content);
        $content = preg_replace('/```\s*$/', '', $content);
        
        // Universal cleanup for em dashes as requested
        $content = preg_replace('/—/', ' - ', $content);
        $content = preg_replace('/–/', '-', $content); // En dash
        
        $content = trim($content);
        
        return $content;
    }

    public function injectSmartLinks(string $content): array
    {
        $prompt = "You are an SEO Editor. 
Task: Analyze the text and inject 2-3 external hyperlinks to authoritative sources (Wikipedia, Major News, Edu/Gov sites) for key terms.
Rules:
1.  Identify 2-3 specific, relevant proper nouns or concepts.
2.  Wrap them in <a href='URL' rel='dofollow' target='_blank'>term</a>.
3.  Use ACTUAL, valid URLs (prioritize Wikipedia).
4.  Do NOT change any other text or formatting.
5.  Return the FULL HTML with the new links.

Content:
" . substr($content, 0, 15000);

        // Try Gemini with fallback
        $result = $this->callGeminiWithFallback($prompt);
        
        if ($result['success']) {
            // Cleanup
            $text = str_replace('```html', '', $result['data']);
            $text = str_replace('```', '', $text);
            $resultContent = trim($text) ?: $content;
            
            Log::info("Link injection successful via {$result['source']}");
            return ['content' => $resultContent, 'error' => null];
        }
        
        // Fallback to HuggingFace
        Log::info("Falling back to HuggingFace for link injection");
        try {
            $hfPrompt = "Analyze this blog content and suggest 2-3 external links to authoritative sources (Wikipedia, major news sites, .edu/.gov). For each link, provide: 1) The exact phrase to link, 2) The full URL. Format as: PHRASE|URL (one per line).\n\nContent:\n" . substr($content, 0, 10000);
            
            $hfResult = $this->callHuggingFaceChatCompletion(
                $this->models[0], 
                "You are an SEO expert. Suggest authoritative external links for blog content.",
                $hfPrompt,
                1
            );
            
            if ($hfResult) {
                // Parse HF response and inject links
                $lines = explode("\n", $hfResult);
                $modifiedContent = $content;
                $linksAdded = 0;
                
                foreach ($lines as $line) {
                    if (strpos($line, '|') !== false && $linksAdded < 3) {
                        list($phrase, $url) = array_map('trim', explode('|', $line, 2));
                        
                        // Basic validation
                        if (filter_var($url, FILTER_VALIDATE_URL) && !empty($phrase)) {
                            // Replace first occurrence of phrase with link
                            $link = "<a href=\"$url\" rel=\"dofollow\" target=\"_blank\">$phrase</a>";
                            $modifiedContent = preg_replace('/' . preg_quote($phrase, '/') . '/', $link, $modifiedContent, 1);
                            $linksAdded++;
                        }
                    }
                }
                
                if ($linksAdded > 0) {
                    Log::info("Link injection successful via HuggingFace ($linksAdded links)");
                    return ['content' => $modifiedContent, 'error' => null];
                }
            }
        } catch (\Exception $e) {
            Log::error("HuggingFace fallback failed: " . $e->getMessage());
        }
        
        // Both failed, return original content with error
        return ['content' => $content, 'error' => "Both Gemini and HuggingFace failed"];
    }

    protected function expandContent(string $content, string $topic, string $systemPrompt): string
    {
        $expansionPrompt = "The following blog post about \"$topic\" is too short. Expand it by adding more detailed sections, examples, and insights. Maintain the same HTML structure and style.\n\nCurrent content:\n$content\n\nExpanded version:";
        
        $expanded = $this->callHuggingFaceChatCompletion($this->models[0], $systemPrompt, $expansionPrompt, 2);
        
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

    public function optimizeAndHumanize(string $content): array
    {
        // Return array: ['content' => string, 'toc' => array]
        
        if (empty($this->geminiKey) && empty($this->geminiKeyFallback)) {
            Log::warning("GEMINI_API_KEY not configured. Skipping optimization.");
            return $this->validateAndFixHtml($content);
        }

        $prompt = "Optimize and humanize this blog content for superior AISEO and User Experience.
Requirements:
1. **Split long paragraphs**: If a paragraph has >3 sentences, split it. 
2. Ensure <p> tags are used correctly.
3. **STRICTLY REMOVE EM DASHES (—)**: Replace with commas/parentheses.
4. **Humanize**: Vary sentence structure, use contractions, ensuring a conversational, expert tone.
5. **Links Check**: 
   - Ensure specific keywords are linked to authoritative external sources if missing (target 2-4 authoritative links total). 
   - Ensure external links allow 'dofollow'.
6. **Meta Optimization**: Ensure the content flows well for the provided keywords.
7. Maintain all headings and structure.
8. Return ONLY the HTML.

Content:
" . substr($content, 0, 15000);

        Log::info("Calling Gemini API for optimization...");
        
        $result = $this->callGeminiWithFallback($prompt);
        
        if ($result['success']) {
            $cleaned = $this->cleanContent($result['data']);
            
            // Regex Fallback: Force remove any remaining em dashes
            $cleaned = preg_replace('/—/', ' - ', $cleaned);
            $cleaned = preg_replace('/–/', '-', $cleaned); // En dash to hyphen
            
            return $this->validateAndFixHtml($cleaned);
        }

        return $this->validateAndFixHtml($content);
    }

    public function validateAndFixHtml(string $html): array
    {
        Log::info("Validating and fixing HTML structure...");
        
        $dom = new \DOMDocument();
        // Suppress warnings for malformed HTML, handle utf-8
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $xpath = new \DOMXPath($dom);

        // 1. Split long paragraphs
        $paragraphs = $dom->getElementsByTagName('p');
        // Convert to array to avoid modification issues during iteration
        $pArray = iterator_to_array($paragraphs);
        
        foreach ($pArray as $p) {
            $text = $p->textContent;
            $words = str_word_count($text);
            
            if ($words > 80) { // Threshold for splitting (approx 4-5 sentences)
                Log::info("Splitting long paragraph ($words words)");
                
                // Simple sentence splitting (imperfect but better than wall of text)
                $sentences = preg_split('/(?<=[.?!])\s+/', $text, -1, PREG_SPLIT_NO_EMPTY);
                $chunk = "";
                $newParams = [];
                
                foreach ($sentences as $sentence) {
                    $chunk .= $sentence . " ";
                    if (str_word_count($chunk) > 40) {
                        $newP = $dom->createElement('p', trim($chunk));
                        $p->parentNode->insertBefore($newP, $p);
                        $chunk = "";
                    }
                }
                if (!empty($chunk)) {
                    $newP = $dom->createElement('p', trim($chunk));
                    $p->parentNode->insertBefore($newP, $p);
                }
                
                $p->parentNode->removeChild($p);
            }
        }

        // 2. Fix Tables
        $tables = $dom->getElementsByTagName('table');
        foreach ($tables as $table) {
            $class = $table->getAttribute('class');
            if (strpos($class, 'comparison-table') === false) {
                $table->setAttribute('class', trim($class . ' comparison-table'));
            }
        }

        // 3. Add IDs to headings for TOC
        $toc = [];
        $headings = $xpath->query('//h2|//h3');
        
        foreach ($headings as $heading) {
            $text = $heading->textContent;
            $slug = \Illuminate\Support\Str::slug($text);
            
            // Generate unique ID
            $originalSlug = $slug;
            $count = 1;
            while ($xpath->query("//*[@id='$slug']")->length > 0) {
                $slug = $originalSlug . '-' . $count++;
            }
            
            $heading->setAttribute('id', $slug);
            
            $toc[] = [
                'level' => $heading->nodeName == 'h2' ? 2 : 3,
                'title' => $text,
                'id' => $slug
            ];
        }

        $fixedHtml = $dom->saveHTML();
        
        // Remove the xml encoding tag added for loading
        $fixedHtml = str_replace('<?xml encoding="utf-8" ?>', '', $fixedHtml);

        return [
            'content' => trim($fixedHtml),
            'toc' => $toc
        ];
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
