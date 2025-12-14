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
     * Generate thumbnail for a blog post
     */
    public function generateThumbnail(string $slug, string $title, string $content, string $category): ?string
    {
        try {
            // Step 1: Analyze content with Gemini
            $analysis = $this->analyzeContent($slug, $title, substr(strip_tags($content), 0, 500), $category);
            
            if (!$analysis) {
                Log::warning("Failed to analyze content for thumbnail. Using fallback.");
                return $this->generateFallbackThumbnail($slug, $category);
            }

            // Step 2: Generate SVG-based thumbnail
            $svgContent = $this->generateSVGThumbnail($analysis);
            
            // Step 3: Convert to WebP and save
            $thumbnailPath = $this->saveAsSvg($slug, $svgContent);
            
            if ($thumbnailPath) {
                Log::info("Thumbnail generated successfully: $thumbnailPath");
                return $thumbnailPath;
            }

        } catch (\Exception $e) {
            Log::error("Thumbnail generation failed: " . $e->getMessage());
        }

        // Fallback
        return $this->generateFallbackThumbnail($slug, $category);
    }

    /**
     * Analyze content using Gemini to extract niche, topic, and visual style
     */
    protected function analyzeContent(string $slug, string $title, string $excerpt, string $category): ?array
    {
        if (empty($this->geminiKey)) {
            Log::warning("GEMINI_API_KEY not configured for thumbnail analysis.");
            return null;
        }

        $url = "https://generativelanguage.googleapis.com/v1beta/models/gemini-1.5-flash:generateContent?key={$this->geminiKey}";
        
        $prompt = "Analyze this blog post and provide a JSON response for thumbnail generation.

Title: $title
Category: $category
Excerpt: $excerpt

Provide ONLY a JSON object with these fields:
{
  \"niche\": \"specific niche (e.g., 'AI Technology', 'Business Strategy')\",
  \"topic\": \"main topic in 2-3 words\",
  \"visualStyle\": \"visual style description (e.g., 'futuristic gradient', 'professional minimalist', 'dynamic abstract')\",
  \"primaryColor\": \"hex color for primary element\",
  \"secondaryColor\": \"hex color for secondary element\",
  \"iconSuggestion\": \"simple icon/shape suggestion (e.g., 'circuit board', 'growth arrow', 'brain network')\"
}

IMPORTANT: The visual style must be suitable for a professional blog header. 
STRICTLY NO TEXT, LETTERS, OR OVERLAYS in the visual style description. 
The design should be abstract, high quality, professional lighting, and centered.";

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
                    // Extract JSON from response
                    preg_match('/\{[^}]+\}/', $content, $matches);
                    if (isset($matches[0])) {
                        $analysis = json_decode($matches[0], true);
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
     * Generate SVG-based thumbnail
     */
    protected function generateSVGThumbnail(array $analysis): string
    {
        $primaryColor = $analysis['primaryColor'] ?? '#3B82F6';
        $secondaryColor = $analysis['secondaryColor'] ?? '#1E40AF';
        $visualStyle = $analysis['visualStyle'] ?? 'modern gradient';

        // Create gradient-based abstract design
        $svg = <<<SVG
<svg width="1200" height="630" xmlns="http://www.w3.org/2000/svg">
  <defs>
    <linearGradient id="grad1" x1="0%" y1="0%" x2="100%" y2="100%">
      <stop offset="0%" style="stop-color:$primaryColor;stop-opacity:1" />
      <stop offset="100%" style="stop-color:$secondaryColor;stop-opacity:1" />
    </linearGradient>
    <filter id="blur">
      <feGaussianBlur in="SourceGraphic" stdDeviation="5" />
    </filter>
  </defs>
  
  <!-- Background gradient -->
  <rect width="1200" height="630" fill="url(#grad1)"/>
  
  <!-- Abstract shapes -->
  <circle cx="200" cy="150" r="120" fill="white" opacity="0.1" filter="url(#blur)"/>
  <circle cx="1000" cy="480" r="150" fill="white" opacity="0.15" filter="url(#blur)"/>
  <rect x="400" y="200" width="400" height="230" rx="20" fill="white" opacity="0.08"/>
  
  <!-- Decorative elements -->
  <path d="M 100 500 Q 300 450 500 500 T 900 500" stroke="white" stroke-width="3" fill="none" opacity="0.3"/>
  <circle cx="600" cy="315" r="80" fill="white" opacity="0.2"/>
</svg>
SVG;

        return $svg;
    }

    /**
     * Save thumbnail as SVG
     */
    /**
     * Save thumbnail as SVG with optimization
     */
    protected function saveAsSvg(string $slug, string $svgContent): ?string
    {
        try {
            // Ensure directory exists
            $directory = storage_path('app/public/thumbnails');
            if (!file_exists($directory)) {
                mkdir($directory, 0755, true);
            }
            
            // Generate filename
            $filename = "{$slug}.svg";
            $path = "thumbnails/{$filename}";
            $fullPath = storage_path("app/public/{$path}");

            // Save raw SVG first
            Storage::disk('public')->put($path, $svgContent);

            // Check file size (target < 200KB)
            $size = Storage::disk('public')->size($path);
            if ($size > 200 * 1024) {
                Log::warning("Thumbnail $filename exceeds 200KB ($size bytes). Optimizing...");
                // Simple optimization: remove excessive whitespace/comments if any
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

    /**
     * Generate fallback thumbnail based on category
     */
    protected function generateFallbackThumbnail(string $slug, string $category): string
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

        return $this->saveAsSvg($slug, $svg);
    }

    /**
     * Generate default category placeholders
     */
    public function generateCategoryPlaceholders(): void
    {
        $categories = ['Technology', 'Business', 'AI', 'Games', 'Politics', 'Sports'];
        
        foreach ($categories as $category) {
            $this->generateFallbackThumbnail(strtolower($category) . '-default', $category);
            Log::info("Generated placeholder for category: $category");
        }
    }
}
