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
        $topic = $topics[array_rand($topics)]; // Pick random trending topic

        // Check duplicates
        if (Blog::where('title', 'LIKE', "%$topic%")->exists()) {
            Log::info("Skipping duplicate topic: $topic");
            return;
        }

        // 2. Research
        // For simplicity, we skip deep scraping of topic details in this step 
        // and rely on AI's knowledge base + scraper's ability if we had a specific URL.
        // In a real app, we'd search Google for the topic, get URLs, then scrape them.
        
        // 3. Generate Draft
        $prompt = "Write a comprehensive blog post about '$topic' in the category of {$category->name}. 
                   Include a title, introduction, key points, and conclusion. 
                   Make it engaging and informative.";
                   
        $draft = $this->ai->generateRawContent($prompt);

        // 4. Optimize
        $finalContent = $this->ai->optimizeAndHumanize($draft);

        // 5. Save
        // Extract Title from content or use topic
        $title = $topic; // Simplified
        if (preg_match('/<h1>(.*?)<\/h1>/', $finalContent, $matches)) {
            $title = $matches[1];
        }

        return Blog::create([
            'title' => $title,
            'slug' => Str::slug($title . '-' . now()->timestamp),
            'content' => $finalContent,
            'category_id' => $category->id,
            'published_at' => now(),
            'meta_title' => Str::limit($title, 60),
            'meta_description' => Str::limit(strip_tags($finalContent), 160),
            'tags_json' => [$category->name, 'Trending', 'AI Generated'],
        ]);
    }
}
