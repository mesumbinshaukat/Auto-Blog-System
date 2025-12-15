<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Str;

class AIService
{
    protected $hfToken;
    protected $geminiKey;
    
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
        $this->geminiKey = env('GEMINI_API_KEY');
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

    protected function generateKeywords(string $topic, string $category): array
    {
        // Use Gemini (preferred) or basic generation for keywords
        // Simple heuristic fallback if no AI: just split topic and add category
        
        if (empty($this->geminiKey)) {
             return [Str::slug($topic, ' '), strtolower($category), 'guide', 'tips'];
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-exp:generateContent?key={$this->geminiKey}";
        $prompt = "Suggest 1 primary focus keyword and 3 secondary long-tail keywords for a blog post about \"$topic\" in category \"$category\". Return ONLY the keywords as a comma-separated list.";
        
        try {
             $response = Http::withOptions(['verify' => false])->post($url, ['contents' => [['parts' => [['text' => $prompt]]]]]);
             if ($response->successful()) {
                 $text = $response->json()['candidates'][0]['content']['parts'][0]['text'] ?? '';
                 $keywords = array_map('trim', explode(',', $text));
                 return array_slice($keywords, 0, 4);
             }
        } catch (\Exception $e) {
            Log::warning("Keyword gen failed: " . $e->getMessage());
        }

        return [Str::title($topic)];
    }

    protected function buildSystemPrompt(): string
    {
        return "You are a professional blog writer and AISEO expert. Generate comprehensive, E-E-A-T optimized, and human-like blog posts in HTML format.

REQUIRED STRUCTURE:
- Start with <h1>Title</h1> (compelling, uses primary keyword naturally)
- Introduction paragraph with <p> tags (include primary keyword in first 100 words, set the hook).
- 4-6 main sections with <h2> headings (Use questions or natural queries as headers where appropriate for Voice Search/AI Overviews).
- Use <h3> for subsections.
- EACH SECTION must have multiple SHORT paragraphs (2-4 sentences).
- Use <strong> for emphasis, <em> for italics.
- Include <ul> or <ol> lists.
- If topic involves comparisons/data, include HTML <table class=\"comparison-table\"> with <thead> and <tbody>.

AISEO & CONTENT REQUIREMENTS:
- **E-E-A-T**: Write with authority and expertise. Use specific examples, data points, or expert consensus.
- **Keywords**: 
    - Integrate 1 Primary Focus Keyword (provided below) naturally (1-2% density).
    - Integrate 2-3 Long-tail keywords naturally.
    - Optimize for \"AI Overviews\": Answer 'what', 'how', 'why' questions directly and concisely in the first sentence of sections.
- **External Links**: 
    - Smartly include 2-4 dofollow external links to relevant, authoritative sites (e.g., Wikipedia, BBC, IEEE, Gov/Edu sites).
    - Place links naturally using descriptive anchor text (e.g., <a href=\"https://www.wikipedia.org/...\" rel=\"dofollow\" target=\"_blank\">descriptive anchor</a>). 
    - Do NOT suggest generic links like 'google.com'.
- **Internal Flow**: Suggest natural transitions between topics.
- **Tone**: Conversational, engaging, and human (use \"we\", \"you\", contractions).
- **Formatting**: Maximize readability. No walls of text.

Example Link:
\"Studies show that <a href='https://www.sleepfoundation.org/...' rel='dofollow' target='_blank'>adequate sleep improves cognition</a> significantly.\"

OUTPUT FORMAT:
Return ONLY the HTML content, no markdown code blocks.";
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

        // Try Gemini first with retry
        for ($attempt = 1; $attempt <= 2; $attempt++) {
            try {
                $response = Http::withHeaders(['Content-Type' => 'application/json'])
                    ->timeout(30)
                    ->post("https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-exp:generateContent?key={$this->geminiKey}", [
                        'contents' => [['parts' => [['text' => $prompt]]]]
                    ]);
                
                if ($response->successful()) {
                    $data = $response->json();
                     $text = $data['candidates'][0]['content']['parts'][0]['text'] ?? '';
                     // Cleanup
                    $text = str_replace('```html', '', $text);
                    $text = str_replace('```', '', $text);
                    $resultContent = trim($text) ?: $content;
                    
                    Log::info("Link injection successful via Gemini (attempt $attempt)");
                    return ['content' => $resultContent, 'error' => null];
                }
                
                Log::warning("Gemini link injection attempt $attempt failed: " . $response->status());
                
                if ($attempt < 2) {
                    sleep(2); // Wait before retry
                }
            } catch (\Exception $e) {
                Log::warning("Gemini link injection attempt $attempt exception: " . $e->getMessage());
                if ($attempt < 2) {
                    sleep(2);
                }
            }
        }
        
        // Fallback to HuggingFace
        Log::info("Falling back to HuggingFace for link injection");
        try {
            $hfPrompt = "Analyze this blog content and suggest 2-3 external links to authoritative sources (Wikipedia, major news sites, .edu/.gov). For each link, provide: 1) The exact phrase to link, 2) The full URL. Format as: PHRASE|URL (one per line).\n\nContent:\n" . substr($content, 0, 10000);
            
            $result = $this->callHuggingFaceChatCompletion(
                $this->models[0], 
                "You are an SEO expert. Suggest authoritative external links for blog content.",
                $hfPrompt,
                1
            );
            
            if ($result) {
                // Parse HF response and inject links
                $lines = explode("\n", $result);
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
        
        if (empty($this->geminiKey)) {
            Log::warning("GEMINI_API_KEY not configured. Skipping optimization.");
            return $this->validateAndFixHtml($content);
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-2.0-flash-exp:generateContent?key={$this->geminiKey}";
        
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
                    $cleaned = $this->cleanContent($optimized);
                    
                    // Regex Fallback: Force remove any remaining em dashes
                    $cleaned = preg_replace('/—/', ' - ', $cleaned);
                    $cleaned = preg_replace('/–/', '-', $cleaned); // En dash to hyphen
                    
                    return $this->validateAndFixHtml($cleaned);
                }
            }
        } catch (\Exception $e) {
            Log::error("Gemini Optimization failed: " . $e->getMessage());
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
