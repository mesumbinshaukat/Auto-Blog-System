<?php

namespace App\Services;

use App\Models\Blog;
use App\Models\Category;
use Illuminate\Support\Str;
use Illuminate\Support\Facades\Log;

class BlogGeneratorService
{
    protected $scraper;
    protected $ai;
    protected $thumbnailService;

    public function __construct(ScrapingService $scraper, AIService $ai, \App\Services\ThumbnailService $thumbnailService)
    {
        $this->scraper = $scraper;
        $this->ai = $ai;
        $this->thumbnailService = $thumbnailService;
    }

    public function generateBlogForCategory(Category $category, ?callable $onProgress = null)
    {
        $onProgress && $onProgress('Scraping trending topics...', 10);
        // 1. Get Topics
        $topics = $this->scraper->fetchTrendingTopics($category->slug);
        $topic = $topics[array_rand($topics)];

        // Check duplicates
        if (Blog::where('title', 'LIKE', "%$topic%")->exists()) {
            Log::info("Skipping duplicate topic: $topic");
            $onProgress && $onProgress('Duplicate topic found, retrying...', 15);
            return null;
        }

        $onProgress && $onProgress("Researching topic: $topic...", 30);
        // 2. Multi-Source Research
        Log::info("Researching topic: $topic");
        $researchData = $this->scraper->researchTopic($topic);
        
        $onProgress && $onProgress('Generating draft with AI...', 50);
        // 3. Generate Draft with new AI service
        $draft = $this->ai->generateRawContent($topic, $category->name, $researchData);

        $onProgress && $onProgress('Optimizing and humanizing content...', 70);
        // 4. Optimize and Humanize (returns ['content' => string, 'toc' => array])
        $optimizedData = $this->ai->optimizeAndHumanize($draft);
        $finalContent = $optimizedData['content'];
        $toc = $optimizedData['toc'];
        
        // 5. Validate content
        $wordCount = str_word_count(strip_tags($finalContent));
        Log::info("Blog generated: $wordCount words");

        // 6. Extract Title
        $title = $topic;
        if (preg_match('/<h1[^>]*>(.*?)<\/h1>/', $finalContent, $matches)) {
            $title = strip_tags($matches[1]);
        }

        // 7. Generate slug
        $slug = Str::slug($title . '-' . now()->timestamp);

        $onProgress && $onProgress('Generating thumbnail...', 85);
        // 8. Generate thumbnail
        $thumbnailPath = $this->thumbnailService->generateThumbnail(
            $slug,
            $title,
            $finalContent,
            $category->name
        );

        $onProgress && $onProgress('Saving to database...', 95);
        // 9. Save
        return Blog::create([
            'title' => $title,
            'slug' => $slug,
            'content' => $finalContent,
            'category_id' => $category->id,
            'published_at' => now(),
            'meta_title' => Str::limit($title, 60),
            'meta_description' => Str::limit(strip_tags($finalContent), 160),
            'tags_json' => [$category->name, 'Trending', 'AI Generated', $topic],
            'table_of_contents_json' => $toc, // Save TOC
            'thumbnail_path' => $thumbnailPath,
        ]);
    }
}
