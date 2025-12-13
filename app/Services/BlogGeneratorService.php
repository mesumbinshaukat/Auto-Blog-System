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

    public function __construct(ScrapingService $scraper, AIService $ai)
    {
        $this->scraper = $scraper;
        $this->ai = $ai;
    }

    public function generateBlogForCategory(Category $category)
    {
        // 1. Get Topics
        $topics = $this->scraper->fetchTrendingTopics($category->slug);
        $topic = $topics[array_rand($topics)];

        // Check duplicates
        if (Blog::where('title', 'LIKE', "%$topic%")->exists()) {
            Log::info("Skipping duplicate topic: $topic");
            return null;
        }

        // 2. Multi-Source Research
        Log::info("Researching topic: $topic");
        $researchData = $this->scraper->researchTopic($topic);
        
        // 3. Generate Draft with new AI service
        $draft = $this->ai->generateRawContent($topic, $category->name, $researchData);

        // 4. Optimize and Humanize
        $finalContent = $this->ai->optimizeAndHumanize($draft);
        
        // 5. Validate content
        $wordCount = str_word_count(strip_tags($finalContent));
        Log::info("Blog generated: $wordCount words");

        // 6. Extract Title
        $title = $topic;
        if (preg_match('/<h1[^>]*>(.*?)<\/h1>/', $finalContent, $matches)) {
            $title = strip_tags($matches[1]);
        }

        // 7. Save
        return Blog::create([
            'title' => $title,
            'slug' => Str::slug($title . '-' . now()->timestamp),
            'content' => $finalContent,
            'category_id' => $category->id,
            'published_at' => now(),
            'meta_title' => Str::limit($title, 60),
            'meta_description' => Str::limit(strip_tags($finalContent), 160),
            'tags_json' => [$category->name, 'Trending', 'AI Generated', $topic],
        ]);
    }
}
