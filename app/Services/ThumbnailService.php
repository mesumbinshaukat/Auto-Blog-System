<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\ImageManager;
use Intervention\Image\Drivers\Gd\Driver;

class ThumbnailService
{
    protected $geminiKey;
    protected $imageManager;

    public function __construct()
    {
        $this->geminiKey = env('GEMINI_API_KEY');
        $this->imageManager = new ImageManager(new Driver());
    }

    /**
     * Generate thumbnail for a blog post with uniqueness validation
     */
    public function generateThumbnail(string $slug, string $title, string $content, string $category, ?int $blogId = null): ?string
    {
        $maxAttempts = 3;
        $attempt = 0;
        
        while ($attempt < $maxAttempts) {
            try {
                // Extract more content for better analysis (2000 chars instead of 500)
                $fullExcerpt = substr(strip_tags($content), 0, 2000);
                
                // Extract topic-specific elements from content
                $topicElements = $this->extractTopicElements($content);
                
                // Analyze with enhanced context
                $analysis = $this->analyzeContent($slug, $title, $fullExcerpt, $category, $topicElements);
                
                if (!$analysis) {
                    Log::warning("Analysis failed on attempt " . ($attempt + 1));
                    $attempt++;
                    continue;
                }
                
                // Generate SVG with dynamic composition
                $svgContent = $this->generateSVGThumbnail($analysis);
                
                if ($blogId) {
                    $svgContent = $this->addIdOverlay($svgContent, $blogId);
                }
                
                // Save temporarily for validation
                $tempSlug = $slug . '-temp-' . $attempt . '-' . time();
                $tempPath = $this->saveAsSvg($tempSlug, $svgContent);
                
                if (!$tempPath) {
                    $attempt++;
                    continue;
                }
                
                // Validate uniqueness
                if ($this->validateUniqueness($tempPath)) {
                    // Unique! Rename to final path
                    $finalPath = $this->saveAsSvg($slug, $svgContent);
                    Storage::disk('public')->delete($tempPath);
                    
                    Log::info("Unique thumbnail generated on attempt " . ($attempt + 1) . ": $finalPath");
                    return $finalPath;
                }
                
                // Not unique, clean up and try again
                Log::info("Thumbnail not unique on attempt " . ($attempt + 1) . ", retrying with variation...");
                Storage::disk('public')->delete($tempPath);
                $attempt++;
                
            } catch (\Exception $e) {
                Log::error("Thumbnail generation attempt " . ($attempt + 1) . " failed: " . $e->getMessage());
                $attempt++;
            }
        }
        
        // All attempts failed, use fallback
        Log::warning("All thumbnail generation attempts failed, using fallback");
        return $this->generateFallbackThumbnail($slug, $category, $blogId);
    }

    /**
     * Extract topic-specific elements from content for better visual generation
     */
    protected function extractTopicElements(string $content): array
    {
        $elements = [];
        $content = strtolower($content);
        
        // Technology & Devices
        if (preg_match('/\b(iphone|apple|ios|mac)\b/i', $content)) {
            $elements[] = 'apple device with rounded corners and sleek design';
        }
        if (preg_match('/\b(android|google|pixel)\b/i', $content)) {
            $elements[] = 'modern smartphone with angular design';
        }
        if (preg_match('/\b(train|railway|timetable|schedule|locomotive)\b/i', $content)) {
            $elements[] = 'train silhouette with clock and track elements';
        }
        if (preg_match('/\b(car|vehicle|automotive|tesla)\b/i', $content)) {
            $elements[] = 'sleek car silhouette with motion lines';
        }
        
        // AI & Ethics
        if (preg_match('/\b(ai|artificial intelligence|machine learning|neural)\b/i', $content)) {
            $elements[] = 'neural network nodes and connections';
        }
        if (preg_match('/\b(ethic|moral|responsible|bias)\b/i', $content)) {
            $elements[] = 'balanced scales with circuit patterns';
        }
        
        // Business & Finance
        if (preg_match('/\b(stock|market|trading|investment)\b/i', $content)) {
            $elements[] = 'upward trending graph with candlesticks';
        }
        if (preg_match('/\b(startup|entrepreneur|venture)\b/i', $content)) {
            $elements[] = 'rocket launch with growth trajectory';
        }
        
        // Gaming
        if (preg_match('/\b(game|gaming|console|xbox|playstation)\b/i', $content)) {
            $elements[] = 'game controller with dynamic action lines';
        }
        if (preg_match('/\b(esports|competitive|tournament)\b/i', $content)) {
            $elements[] = 'trophy with digital effects';
        }
        
        // Politics & Social
        if (preg_match('/\b(election|vote|democracy|campaign)\b/i', $content)) {
            $elements[] = 'ballot box with civic symbols';
        }
        if (preg_match('/\b(climate|environment|sustainability)\b/i', $content)) {
            $elements[] = 'earth with renewable energy symbols';
        }
        
        return array_unique($elements);
    }

    /**
     * Enhanced content analysis with Gemini
     */
    protected function analyzeContent(string $slug, string $title, string $excerpt, string $category, array $topicElements = []): ?array
    {
        if (empty($this->geminiKey)) {
            Log::warning("GEMINI_API_KEY not configured for thumbnail analysis.");
            return null;
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$this->geminiKey}";
        
        $elementsText = !empty($topicElements) ? implode(', ', $topicElements) : 'none detected';
        
        $prompt = "Analyze this blog post for a unique, topic-specific thumbnail design.

Title: $title
Category: $category
Content Excerpt (first 2000 chars): $excerpt
Detected Topic Elements: $elementsText

Generate a JSON response with these fields:
{
  \"niche\": \"specific sub-niche\",
  \"topic\": \"2-3 word topic\",
  \"visualStyle\": \"one of: realistic|illustrative|abstract|geometric|minimalist\",
  \"primaryColor\": \"hex color (avoid generic blues for tech, vary based on topic)\",
  \"secondaryColor\": \"complementary hex color\",
  \"specificElements\": [\"element1\", \"element2\", \"element3\"],
  \"composition\": \"one of: centered|asymmetric|diagonal|layered|grid\",
  \"mood\": \"one of: professional|dynamic|calm|energetic|serious\"
}

CRITICAL REQUIREMENTS:
1. specificElements MUST relate to the actual topic (e.g., for iPhone: 'rounded phone shape', 'apple logo silhouette')
2. Avoid generic tech imagery (circuits, gradients) unless truly relevant
3. Each thumbnail must be visually distinct - vary colors, composition, and elements
4. NO TEXT or LETTERS in the design
5. Use the detected elements: $elementsText to inform your visual choices";

        try {
            $response = Http::withOptions(['verify' => false])
                ->timeout(30)
                ->post($url, [
                    'contents' => [
                        ['parts' => [['text' => $prompt]]]
                    ]
                ]);

            if ($response->successful()) {
                $data = $response->json();
                $content = $data['candidates'][0]['content']['parts'][0]['text'] ?? null;
                
                if ($content) {
                    // Extract JSON from response (handle code blocks)
                    if (preg_match('/```json\s*(\{.*?\})\s*```/s', $content, $matches)) {
                        $jsonStr = $matches[1];
                    } else {
                        preg_match('/\{[^{}]*(?:\{[^{}]*\}[^{}]*)*\}/s', $content, $matches);
                        $jsonStr = $matches[0] ?? null;
                    }
                    
                    if ($jsonStr) {
                        $analysis = json_decode($jsonStr, true);
                        if ($analysis) {
                            Log::info("Content analyzed for thumbnail: " . json_encode($analysis));
                            return $analysis;
                        }
                    }
                }
            }
        } catch (\Exception $e) {
            Log::error("Gemini analysis failed: " . $e->getMessage());
        }

        return null;
    }

    /**
     * Generate SVG with dynamic composition based on analysis
     */
    protected function generateSVGThumbnail(array $analysis): string
    {
        $primaryColor = $analysis['primaryColor'] ?? '#3B82F6';
        $secondaryColor = $analysis['secondaryColor'] ?? '#1E40AF';
        $composition = $analysis['composition'] ?? 'centered';
        $elements = $analysis['specificElements'] ?? [];
        $mood = $analysis['mood'] ?? 'professional';
        
        // Generate base SVG with gradient
        $svg = $this->generateBaseSVG($primaryColor, $secondaryColor, $composition);
        
        // Add topic-specific shapes
        $svg = $this->addTopicSpecificShapes($svg, $elements, $composition, $mood);
        
        return $svg;
    }

    /**
     * Generate base SVG structure with varied composition
     */
    protected function generateBaseSVG(string $primaryColor, string $secondaryColor, string $composition): string
    {
        // Vary gradient direction based on composition
        $gradientCoords = match($composition) {
            'diagonal' => ['x1' => '0%', 'y1' => '0%', 'x2' => '100%', 'y2' => '100%'],
            'asymmetric' => ['x1' => '30%', 'y1' => '0%', 'x2' => '100%', 'y2' => '70%'],
            'layered' => ['x1' => '0%', 'y1' => '50%', 'x2' => '100%', 'y2' => '50%'],
            default => ['x1' => '0%', 'y1' => '0%', 'x2' => '100%', 'y2' => '100%'],
        };
        
        $x1 = $gradientCoords['x1'];
        $y1 = $gradientCoords['y1'];
        $x2 = $gradientCoords['x2'];
        $y2 = $gradientCoords['y2'];
        
        return <<<SVG
<svg width="1200" height="630" xmlns="http://www.w3.org/2000/svg">
  <defs>
    <linearGradient id="grad1" x1="$x1" y1="$y1" x2="$x2" y2="$y2">
      <stop offset="0%" style="stop-color:$primaryColor;stop-opacity:1" />
      <stop offset="100%" style="stop-color:$secondaryColor;stop-opacity:1" />
    </linearGradient>
    <filter id="blur">
      <feGaussianBlur in="SourceGraphic" stdDeviation="5" />
    </filter>
  </defs>
  <rect width="1200" height="630" fill="url(#grad1)"/>
</svg>
SVG;
    }

    /**
     * Add topic-specific shapes to SVG
     */
    protected function addTopicSpecificShapes(string $svg, array $elements, string $composition, string $mood): string
    {
        $shapes = '';
        
        foreach ($elements as $element) {
            $element = strtolower($element);
            
            // Phone/Device shapes
            if (str_contains($element, 'phone') || str_contains($element, 'device')) {
                $shapes .= '<rect x="500" y="180" width="200" height="350" rx="30" fill="white" opacity="0.15"/>';
                $shapes .= '<circle cx="600" cy="480" r="15" fill="white" opacity="0.2"/>';
            }
            
            // Train/Transport shapes
            if (str_contains($element, 'train') || str_contains($element, 'track')) {
                $shapes .= '<path d="M 300 400 L 900 400 L 850 300 L 350 300 Z" fill="white" opacity="0.2"/>';
                $shapes .= '<circle cx="400" cy="420" r="25" fill="white" opacity="0.25"/>';
                $shapes .= '<circle cx="800" cy="420" r="25" fill="white" opacity="0.25"/>';
            }
            
            // Clock/Time shapes
            if (str_contains($element, 'clock') || str_contains($element, 'time')) {
                $shapes .= '<circle cx="900" cy="200" r="80" stroke="white" stroke-width="4" fill="none" opacity="0.3"/>';
                $shapes .= '<line x1="900" y1="200" x2="900" y2="150" stroke="white" stroke-width="4" opacity="0.3"/>';
                $shapes .= '<line x1="900" y1="200" x2="940" y2="220" stroke="white" stroke-width="3" opacity="0.3"/>';
            }
            
            // Scales/Balance shapes (ethics, justice)
            if (str_contains($element, 'scale') || str_contains($element, 'balance')) {
                $shapes .= '<path d="M 400 300 L 800 300 M 600 200 L 600 400" stroke="white" stroke-width="6" opacity="0.25"/>';
                $shapes .= '<circle cx="450" cy="350" r="50" stroke="white" stroke-width="3" fill="none" opacity="0.2"/>';
                $shapes .= '<circle cx="750" cy="350" r="50" stroke="white" stroke-width="3" fill="none" opacity="0.2"/>';
            }
            
            // Neural/Network shapes
            if (str_contains($element, 'neural') || str_contains($element, 'network') || str_contains($element, 'node')) {
                $shapes .= '<circle cx="300" cy="250" r="20" fill="white" opacity="0.3"/>';
                $shapes .= '<circle cx="500" cy="300" r="20" fill="white" opacity="0.3"/>';
                $shapes .= '<circle cx="700" cy="200" r="20" fill="white" opacity="0.3"/>';
                $shapes .= '<line x1="300" y1="250" x2="500" y2="300" stroke="white" stroke-width="2" opacity="0.2"/>';
                $shapes .= '<line x1="500" y1="300" x2="700" y2="200" stroke="white" stroke-width="2" opacity="0.2"/>';
            }
            
            // Graph/Chart shapes
            if (str_contains($element, 'graph') || str_contains($element, 'chart') || str_contains($element, 'trend')) {
                $shapes .= '<path d="M 200 500 L 400 400 L 600 350 L 800 250 L 1000 200" stroke="white" stroke-width="5" fill="none" opacity="0.3"/>';
                $shapes .= '<circle cx="200" cy="500" r="10" fill="white" opacity="0.4"/>';
                $shapes .= '<circle cx="1000" cy="200" r="10" fill="white" opacity="0.4"/>';
            }
            
            // Rocket/Growth shapes
            if (str_contains($element, 'rocket') || str_contains($element, 'launch')) {
                $shapes .= '<path d="M 600 150 L 550 400 L 600 350 L 650 400 Z" fill="white" opacity="0.2"/>';
                $shapes .= '<path d="M 580 450 Q 600 500 620 450" stroke="white" stroke-width="3" fill="none" opacity="0.15"/>';
            }
        }
        
        // If no specific elements matched, add generic abstract shapes based on composition
        if (empty($shapes)) {
            $shapes = match($composition) {
                'asymmetric' => '<circle cx="250" cy="200" r="100" fill="white" opacity="0.12" filter="url(#blur)"/><circle cx="950" cy="450" r="130" fill="white" opacity="0.15" filter="url(#blur)"/>',
                'diagonal' => '<rect x="100" y="100" width="300" height="300" rx="20" fill="white" opacity="0.08" transform="rotate(15 250 250)"/><circle cx="900" cy="500" r="100" fill="white" opacity="0.12"/>',
                'layered' => '<rect x="200" y="150" width="800" height="100" rx="10" fill="white" opacity="0.1"/><rect x="300" y="350" width="600" height="80" rx="10" fill="white" opacity="0.15"/>',
                'grid' => '<rect x="200" y="150" width="200" height="200" rx="10" fill="white" opacity="0.1"/><rect x="500" y="150" width="200" height="200" rx="10" fill="white" opacity="0.12"/><rect x="800" y="150" width="200" height="200" rx="10" fill="white" opacity="0.08"/>',
                default => '<circle cx="300" cy="200" r="120" fill="white" opacity="0.1" filter="url(#blur)"/><circle cx="900" cy="450" r="150" fill="white" opacity="0.15" filter="url(#blur)"/><circle cx="600" cy="315" r="80" fill="white" opacity="0.2"/>',
            };
        }
        
        return str_replace('</svg>', $shapes . '</svg>', $svg);
    }

    /**
     * Validate thumbnail uniqueness against existing thumbnails
     */
    public function validateUniqueness(string $newThumbnailPath): bool
    {
        try {
            $existingThumbnails = Storage::disk('public')->files('thumbnails');
            
            if (empty($existingThumbnails)) {
                return true; // First thumbnail, automatically unique
            }
            
            // Get new thumbnail content
            $newContent = Storage::disk('public')->get($newThumbnailPath);
            $newHash = $this->calculateContentHash($newContent);
            
            // Compare with existing thumbnails
            $similarCount = 0;
            foreach ($existingThumbnails as $existing) {
                if ($existing === $newThumbnailPath) continue;
                
                $existingContent = Storage::disk('public')->get($existing);
                $existingHash = $this->calculateContentHash($existingContent);
                
                $similarity = $this->calculateSimilarity($newHash, $existingHash);
                
                if ($similarity > 0.80) { // 80% similarity threshold
                    Log::warning("Thumbnail similar to $existing (similarity: " . round($similarity * 100, 2) . "%)");
                    $similarCount++;
                }
            }
            
            // Allow if not too similar to any existing thumbnail
            return $similarCount === 0;
            
        } catch (\Exception $e) {
            Log::error("Uniqueness validation failed: " . $e->getMessage());
            return true; // On error, allow the thumbnail
        }
    }

    /**
     * Calculate content hash for similarity comparison
     */
    protected function calculateContentHash(string $svgContent): string
    {
        // Extract key visual elements for comparison
        preg_match_all('/fill="([^"]+)"/', $svgContent, $fills);
        preg_match_all('/stroke="([^"]+)"/', $svgContent, $strokes);
        preg_match_all('/<(circle|rect|path|line)/', $svgContent, $shapes);
        preg_match_all('/cx="([^"]+)"/', $svgContent, $positions);
        
        $signature = implode('|', [
            implode(',', $fills[1] ?? []),
            implode(',', $strokes[1] ?? []),
            implode(',', $shapes[1] ?? []),
            count($positions[1] ?? [])
        ]);
        
        return $signature;
    }

    /**
     * Calculate similarity between two hashes
     */
    protected function calculateSimilarity(string $hash1, string $hash2): float
    {
        similar_text($hash1, $hash2, $percent);
        return $percent / 100;
    }

    protected function addIdOverlay(string $svg, int $id): string
    {
        $overlay = <<<SVG
<text x="1180" y="610" font-family="Arial, sans-serif" font-size="24" fill="white" stroke="black" stroke-width="0.5" text-anchor="end" opacity="0.6">Blog ID: {$id}</text>
</svg>
SVG;
        return str_replace('</svg>', $overlay, $svg);
    }

    protected function saveAsSvg(string $slug, string $svgContent): ?string
    {
        try {
            $directory = storage_path('app/public/thumbnails');
            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }
            
            $filename = "{$slug}.svg";
            $path = "thumbnails/{$filename}";

            Storage::disk('public')->put($path, $svgContent);

            $size = Storage::disk('public')->size($path);
            if ($size > 200 * 1024) {
                Log::warning("Thumbnail $filename exceeds 200KB ($size bytes). Optimizing...");
                $optimizedSvg = preg_replace('/>\s+</', '><', $svgContent);
                $optimizedSvg = preg_replace('/<!--.*?-->/s', '', $optimizedSvg);
                Storage::disk('public')->put($path, $optimizedSvg);
            }

            return $path;
            
        } catch (\Exception $e) {
            Log::error("Failed to save thumbnail as SVG: " . $e->getMessage());
            return null;
        }
    }

    protected function generateFallbackThumbnail(string $slug, string $category, ?int $blogId = null): string
    {
        $categoryColors = [
            'Technology' => ['#3B82F6', '#1E40AF'],
            'Business' => ['#10B981', '#047857'],
            'AI' => ['#8B5CF6', '#6D28D9'],
            'Games' => ['#EF4444', '#B91C1C'],
            'Politics' => ['#F59E0B', '#D97706'],
            'Sports' => ['#EC4899', '#BE185D'],
        ];

        $colors = $categoryColors[$category] ?? ['#6B7280', '#374151'];
        
        $svg = <<<SVG
<svg width="1200" height="630" xmlns="http://www.w3.org/2000/svg">
  <defs>
    <linearGradient id="fallbackGrad" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" style="stop-color:{$colors[0]};stop-opacity:1" />
      <stop offset="100%" style="stop-color:{$colors[1]};stop-opacity:1" />
    </linearGradient>
  </defs>
  <rect width="1200" height="630" fill="url(#fallbackGrad)"/>
  <circle cx="600" cy="315" r="150" fill="white" opacity="0.2"/>
  <text x="600" y="340" font-family="Arial, sans-serif" font-size="48" fill="white" text-anchor="middle" opacity="0.8">$category</text>
</svg>
SVG;

        if ($blogId) {
            $svg = $this->addIdOverlay($svg, $blogId);
        }

        return $this->saveAsSvg($slug, $svg);
    }

    public function generateCategoryPlaceholders(): void
    {
        $categories = ['Technology', 'Business', 'AI', 'Games', 'Politics', 'Sports'];
        
        foreach ($categories as $category) {
            $this->generateFallbackThumbnail(strtolower($category) . '-default', $category);
            Log::info("Generated placeholder for category: $category");
        }
    }
}
