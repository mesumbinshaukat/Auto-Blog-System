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
    protected $titleSanitizer;

    public function __construct(
        ScrapingService $scraper, 
        AIService $ai, 
        \App\Services\ThumbnailService $thumbnailService,
        TitleSanitizerService $titleSanitizer
    )
    {
        $this->scraper = $scraper;
        $this->ai = $ai;
        $this->thumbnailService = $thumbnailService;
        $this->titleSanitizer = $titleSanitizer;
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
        
        // 5b. Extract Keywords (Simple frequency + title words fallback if no AI extraction available easily here, but we can rely on title mainly for meta)
        // For meta optimization, we'll use the topic and generated title.
        
        // 5c. Internal Linking
        $relatedBlogs = Blog::where('category_id', $category->id)
            ->where('id', '!=', $category->id) // Safety check, though category_id != blog_id is obvious. 
            // Better: where('id', '!=', $newId) - but we don't have ID yet.
            // We can only link to EXISTING blogs.
            ->latest()
            ->take(3)
            ->get();
            
        if ($relatedBlogs->count() > 0) {
             $finalContent = $this->insertInternalLinks($finalContent, $relatedBlogs);
        }

        // 6. Extract Title
        $title = $topic;
        if (preg_match('/<h1[^>]*>(.*?)<\/h1>/', $finalContent, $matches)) {
            $title = strip_tags($matches[1]);
        }
        
        // Sanitize title to remove entities
        $title = $this->titleSanitizer->sanitizeTitle($title);

        // 7. Generate slug
        $slug = Str::slug($title . '-' . now()->timestamp);

        // 8. Create Blog record first to get ID
        $onProgress && $onProgress('Saving initial record...', 85);
        $blog = Blog::create([
            'title' => $title,
            'slug' => $slug,
            'content' => $finalContent,
            'category_id' => $category->id,
            'published_at' => now(),
             // Enhanced SEO Meta
            'meta_title' => Str::limit($title, 55) . ' - ' . config('app.name', 'AutoBlog'),
            'meta_description' => Str::limit(strip_tags($finalContent), 155),
            'tags_json' => [$category->name, 'Trending', $topic, date('Y')],
            'table_of_contents_json' => $toc,
            'thumbnail_path' => null, // Placeholder
        ]);

        $onProgress && $onProgress('Generating thumbnail...', 90);
        // 9. Generate thumbnail with ID
        $thumbnailPath = $this->thumbnailService->generateThumbnail(
            $slug,
            $title,
            $finalContent,
            $category->name,
            $blog->id // Pass the ID
        );

        // 10. Update blog with actual thumbnail
        $blog->update(['thumbnail_path' => $thumbnailPath]);
        
        // Double-check and fix any issues (e.g. if title logic changed post-creation)
        $this->titleSanitizer->fixBlog($blog);
        
        $onProgress && $onProgress('Done!', 100);
        
        return $blog;
    }

    protected function insertInternalLinks(string $html, $relatedBlogs): string
    {
        if ($relatedBlogs->isEmpty()) return $html;

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        
        $paragraphs = $dom->getElementsByTagName('p');
        $pCount = $paragraphs->length;
        
        // Distribute links: one after 1st para, one in middle, one near end (approx)
        $positions = [
            1, 
            (int)($pCount / 2), 
            $pCount - 2
        ];
        
        $blogIndex = 0;
        
        foreach ($positions as $index) {
             if ($blogIndex >= $relatedBlogs->count()) break;
             if ($index < 0 || $index >= $pCount) continue;
             
             $targetP = $paragraphs->item($index);
             if ($targetP) {
                 $blog = $relatedBlogs[$blogIndex];
                 
                 // Create link phrasing
                 $phrases = [
                     "For more details, check out <a href='%s' rel='dofollow'>%s</a>.",
                     "You might also like: <a href='%s' rel='dofollow'>%s</a>.",
                     "Related reading: <a href='%s' rel='dofollow'>%s</a>."
                 ];
                 $phrase = sprintf($phrases[$blogIndex % 3], route('blog.show', $blog->slug), $blog->title);
                 
                 // Append to paragraph end or creating new valid node
                 // Easy way: append text node + element? No, phrase has HTML.
                 // Create a span or just append to P?
                 // Safer: Create a new small paragraph after this one to avoid breaking flow? 
                 // Or append text. Let's append to P for natural flow if possible, or new P.
                 // New P is safer for structure.
                 
                 $newP = $dom->createElement('p');
                 // Load HTML fragment for the link
                 $frag = $dom->createDocumentFragment();
                 $frag->appendXML("<em>$phrase</em>");
                 $newP->appendChild($frag);
                 
                 $targetP->parentNode->insertBefore($newP, $targetP->nextSibling);
                 $blogIndex++;
             }
        }
        
        $fixed = $dom->saveHTML();
        return str_replace('<?xml encoding="utf-8" ?>', '', $fixed);
    }
}
