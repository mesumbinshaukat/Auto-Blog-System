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
    protected $linkDiscovery;

    public function __construct(
        ScrapingService $scraper, 
        AIService $ai, 
        \App\Services\ThumbnailService $thumbnailService,
        TitleSanitizerService $titleSanitizer,
        LinkDiscoveryService $linkDiscovery
    )
    {
        $this->scraper = $scraper;
        $this->ai = $ai;
        $this->thumbnailService = $thumbnailService;
        $this->titleSanitizer = $titleSanitizer;
        $this->linkDiscovery = $linkDiscovery;
    }

    public function generateBlogForCategory(Category $category, ?callable $onProgress = null, ?string $customPrompt = null)
    {
        $blog = null;
        $error = null;
        $logs = [];
        $isDuplicate = false;
        
        try {
            $onProgress && $onProgress('Scraping trending topics...', 10);
            
            // 0. Custom Prompt Handling (URL Extraction)
            $customContext = "";
            $scrapeUrl = null;
            $scrapedContent = null;
            if ($customPrompt) {
                // Capture every URL candidate (handles punctuation and multiple URLs)
                if (preg_match_all('/https?:\/\/[\w\-\.]+[\w\-\.\~:\/\?\#\[\]\@!\$&\'\(\)\*\+,;=%]+/', $customPrompt, $matches)) {
                    foreach ($matches[0] as $rawUrl) {
                        $candidate = rtrim($rawUrl, ".,;\"')>");
                        if (filter_var($candidate, FILTER_VALIDATE_URL)) {
                            $scrapeUrl = $candidate;
                            break;
                        }
                        $logs[] = "Warning: Invalid URL format detected: $rawUrl";
                    }
                }

                if ($scrapeUrl) {
                    $logs[] = "Detailed custom prompt contains valid URL: $scrapeUrl. Attempting scrape...";
                    
                    try {
                        $scrapedContent = $this->scraper->scrapeContent($scrapeUrl);
                        if ($scrapedContent) {
                            // Truncate to 3000 chars to save tokens but keep "juicy" parts
                            $truncatedContent = Str::limit($scrapedContent, 3000);
                            
                            // Enhanced Context for Tool Blogs
                            // We inject specific instructions to treat this as a Tool/Site review
                            $customContext = "\n\n[USER PROVIDED SOURCE CONTENT START]\n" . $truncatedContent . "\n[USER PROVIDED SOURCE CONTENT END]\n";
                            $customContext .= "\nIMPORTANT INSTRUCTIONS:\n";
                            $customContext .= "1. Write a comprehensive SEO blog for the tool/site at [$scrapeUrl].\n";
                            $customContext .= "2. Cover: Core Features, How It Works, Pros & Cons, and Use Cases.\n";
                            $customContext .= "3. Include a Comparison section (e.g., vs competitors like Remove.bg, ChatGPT, etc. if relevant).\n";
                            $customContext .= "4. MUST insert a dofollow link (rel='dofollow') to <a href='$scrapeUrl'>the official site</a> in the introduction or conclusion.\n";
                            
                            $logs[] = "Successfully scraped content from user URL";
                        } else {
                            $logs[] = "Warning: Unable to access site content for $scrapeUrl (empty response)";
                            $customContext = "\n\n[Unable to access site content for $scrapeUrl. Proceed using general knowledge about this tool/site.]\n";
                        }
                    } catch (\Exception $e) {
                        Log::warning("Failed to scrape user URL ($scrapeUrl): " . $e->getMessage());
                        $logs[] = "Warning: Unable to access site content for $scrapeUrl (exception caught)";
                        $customContext = "\n\n[Unable to access site content for $scrapeUrl. Proceed using general knowledge about this tool/site.]\n";
                    }
                }
            }
            
            if ($customPrompt) {
                $customContext .= $this->buildToolIntelligenceBlock($customPrompt, $scrapedContent, $scrapeUrl);
            }

            // 1. Determine Topic
            $selectedTopic = null;
            $attemptedTopics = []; // Track for email reporting
            
            if ($customPrompt) {
                 // Optimization: If extracted URL exists, use the prompt text as the topic
                 // This ensures the custom request is honored without interference from RSS/duplicates
                 $selectedTopic = $customPrompt;
                 $logs[] = "Using custom prompt as topic: " . Str::limit($selectedTopic, 50);
            } else {
                // Standard Flow: RSS with enhanced duplicate detection
                // 1. Get Topics
                $topics = $this->scraper->fetchTrendingTopics($category->slug);
                $logs[] = "Fetched " . count($topics) . " topics for category: {$category->name}";
                
                // 2. Duplicate Check with Retry Loop (max 10 attempts as per requirements)
                $attempt = 0;
                $maxAttempts = 10;
                
                while ($attempt < $maxAttempts) {
                    if (empty($topics)) {
                        $logs[] = "No topics available from RSS. Using fallbacks.";
                        $topics = $this->scraper->fetchFallbackTopics($category->slug);
                    }
                    
                    // Pick random topic
                    $candidateTopic = $topics[array_rand($topics)];
                    $attemptedTopics[] = $candidateTopic;
                    
                    // Enhanced duplicate check: LIKE + similarity
                    $isDuplicate = $this->checkTopicDuplicate($candidateTopic, $logs);
                    
                    if ($isDuplicate) {
                        $logs[] = "Attempt " . ($attempt + 1) . "/$maxAttempts: Topic '$candidateTopic' is a duplicate. Retrying...";
                        // Remove from topics to avoid picking again
                        $topics = array_diff($topics, [$candidateTopic]);
                        $attempt++;
                        continue; 
                    }
                    
                    $selectedTopic = $candidateTopic;
                    $logs[] = "Selected fresh topic: $selectedTopic (passed duplicate check)";
                    break;
                }
            }
            
            if (!$selectedTopic) {
                Log::error("All $maxAttempts topic attempts were duplicates for category: {$category->name}");
                $logs[] = "ERROR: All $maxAttempts topic attempts were duplicates";
                $logs[] = "Attempted topics: " . implode(", ", $attemptedTopics);
                $isDuplicate = true;
                $onProgress && $onProgress('All topics are duplicates, aborting...', 15);
                
                // Send email about duplicate exhaustion
                try {
                    $reportEmail = env('REPORTS_EMAIL', 'mesumbinshaukat@gmail.com');
                    \Illuminate\Support\Facades\Mail::to($reportEmail)
                        ->send(new \App\Mail\BlogGenerationReport(
                            null,
                            new \Exception("Duplicates exhausted for {$category->name}"),
                            array_merge($logs, ["Attempted topics: " . implode(", ", $attemptedTopics)]),
                            true // isDuplicate flag
                        ));
                } catch (\Exception $mailEx) {
                    Log::error("Failed to send duplicate exhaustion email: " . $mailEx->getMessage());
                }
                
                return null;
            }
            
            $topic = $selectedTopic;

            $onProgress && $onProgress("Researching topic: $topic...", 30);
            // 3. Multi-Source Research
            Log::info("Researching topic: $topic");
            $logs[] = "Researching topic: $topic";
            $researchData = $this->scraper->researchTopic($topic);
            
            // Append Custom Context if exists
            if ($customPrompt) {
                 $researchData .= "\n\nIMPORTANT: The user provided specific instructions: \"$customPrompt\"" . $customContext;
            }
            
            $onProgress && $onProgress('Generating draft with AI...', 50);
            // 4. Generate Draft with new AI service
            $logs[] = "Generating content with AI...";
            $draft = $this->ai->generateRawContent($topic, $category->name, $researchData);

            $onProgress && $onProgress('Optimizing and humanizing content...', 70);
            // 5. Optimize and Humanize (returns ['content' => string, 'toc' => array])
            $logs[] = "Optimizing and humanizing content...";
            $optimizedData = $this->ai->optimizeAndHumanize($draft);
            $finalContent = $optimizedData['content'];
            $toc = $optimizedData['toc'];
            
            // 5b. AI Artifact Cleanup
            $logs[] = "Cleaning up AI artifacts...";
            try {
                $finalContent = $this->ai->cleanupAIArtifacts($finalContent, $topic);
            } catch (\Exception $e) {
                Log::warning("Artifact cleanup failed: " . $e->getMessage());
                // Proceed with uncleaned content
            }
            
            // 6. Validate content
            $wordCount = str_word_count(strip_tags($finalContent));
            Log::info("Blog generated: $wordCount words");
            $logs[] = "Blog generated: $wordCount words";
            
            // 7. Link Management & SEO Optimization
            $onProgress && $onProgress('Optimizing Link Structure & SEO...', 75);
            
            try {
                $logs[] = "Processing SEO links...";
                $seoResult = $this->processSeoLinks($finalContent, $category);
                $finalContent = $seoResult['html'];
                
                // Log SEO actions for debugging
                if (!empty($seoResult['logs'])) {
                    Log::info("SEO Optimization Logs for '$topic': " . json_encode($seoResult['logs']));
                    $logs = array_merge($logs, array_slice($seoResult['logs'], 0, 5)); // Add first 5 SEO logs
                }
            } catch (\Exception $e) {
                Log::error("SEO Optimization failed during generation: " . $e->getMessage());
                $logs[] = "WARNING: SEO Optimization failed: " . $e->getMessage();
                // Continue with original content if SEO fails, but log it
            }

            // 8. Extract Title
            $title = $topic;
            if (preg_match('/<h1[^>]*>(.*?)<\/h1>/', $finalContent, $matches)) {
                $title = strip_tags($matches[1]);
            }
            
            // Sanitize title to remove entities
            $title = $this->titleSanitizer->sanitizeTitle($title);

            // 9. Generate slug
            $slug = Str::slug($title . '-' . now()->timestamp);

            // 10. Create Blog record first to get ID
            $onProgress && $onProgress('Saving initial record...', 85);
            $logs[] = "Creating blog record...";
            $blog = Blog::create([
                'title' => $title,
                'slug' => $slug,
                'content' => $finalContent,
                'custom_prompt' => $customPrompt, 
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
            // 11. Generate thumbnail with ID (Resilient Wrapper)
            try {
                $logs[] = "Generating thumbnail...";
                $thumbnailPath = $this->thumbnailService->generateThumbnail(
                    $slug,
                    $title,
                    $finalContent,
                    $category->name,
                    $blog->id
                );
                
                // 12. Update blog with actual thumbnail
                $blog->update(['thumbnail_path' => $thumbnailPath]);
                $logs[] = "Thumbnail generated successfully";
                
            } catch (\Exception $e) {
                Log::warning("Thumbnail generation failed for blog {$blog->id}: " . $e->getMessage());
                $logs[] = "WARNING: Thumbnail generation failed: " . $e->getMessage();
                // Continue without thumbnail (it will use placeholder or default)
            }
            
            // Double-check and fix any issues (e.g. if title logic changed post-creation)
            $this->titleSanitizer->fixBlog($blog);
            
            $onProgress && $onProgress('Done!', 100);
            $logs[] = "Blog generation completed successfully";
            
        } catch (\Exception $e) {
            $error = $e;
            Log::error("Blog generation failed: " . $e->getMessage());
            $logs[] = "ERROR: " . $e->getMessage();
        } finally {
            // Send email notification for all scenarios
            try {
                \Illuminate\Support\Facades\Mail::to(env('REPORTS_EMAIL', 'mesumbinshaukat@gmail.com'))
                    ->send(new \App\Mail\BlogGenerationReport($blog, $error, $logs, $isDuplicate));
            } catch (\Exception $mailEx) {
                Log::error("Failed to send blog generation email: " . $mailEx->getMessage());
            }
        }
        
        return $blog;
    }
    
    /**
     * Check if topic is duplicate using LIKE and similarity checking
     * 
     * @param string $candidateTopic
     * @param array &$logs Reference to logs array for detailed reporting
     * @return bool True if duplicate, false if unique
     */
    protected function checkTopicDuplicate(string $candidateTopic, array &$logs): bool
    {
        // 1. LIKE check (fast, catches exact/substring matches)
        if (Blog::where('title', 'LIKE', "%$candidateTopic%")->exists()) {
            $logs[] = "  → LIKE check: Found exact/substring match for '$candidateTopic'";
            return true;
        }
        
        // 2. Similarity check (catches similar titles with different wording)
        $existingTitles = Blog::pluck('title')->toArray();
        
        foreach ($existingTitles as $existingTitle) {
            // Calculate similarity using similar_text (Levenshtein-like)
            similar_text(strtolower($candidateTopic), strtolower($existingTitle), $percent);
            
            if ($percent > 80) {
                $logs[] = "  → Similarity check: '$candidateTopic' is " . round($percent, 1) . "% similar to existing '$existingTitle'";
                return true;
            }
        }
        
        $logs[] = "  → Uniqueness check passed for '$candidateTopic'";
        return false;
    }

    /**
     * Count existing links in HTML content
     * 
     * @param string $html
     * @return array ['internal' => int, 'external' => int, 'total' => int, 'urls' => array]
     */
    protected function countExistingLinks(string $html): array
    {
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $links = $dom->getElementsByTagName('a');
        $internal = 0;
        $external = 0;
        $urls = [];

        $siteUrl = config('app.url');

        foreach ($links as $link) {
            $href = $link->getAttribute('href');
            
            if (empty($href) || $href === '#') {
                continue;
            }

            $urls[] = $href;

            // Check if internal (starts with / or site URL)
            if (str_starts_with($href, '/') || str_starts_with($href, $siteUrl)) {
                $internal++;
            } else {
                $external++;
            }
        }

        return [
            'internal' => $internal,
            'external' => $external,
            'total' => $internal + $external,
            'urls' => $urls
        ];
    }

    public function processSeoLinks(string $content, Category $category, array $options = []): array
    {
        $linkLogs = [];
        
        // 0. Count existing links
        $linkStats = $this->countExistingLinks($content);
        $linkLogs[] = "Existing links: {$linkStats['internal']} internal, {$linkStats['external']} external, {$linkStats['total']} total";
        
        // 1. Remove excess internal links if > 4
        if ($linkStats['internal'] > 4) {
            $linkLogs[] = "Removing excess internal links ({$linkStats['internal']} -> 4)";
            $content = $this->removeExcessInternalLinks($content, 4);
            $linkStats = $this->countExistingLinks($content);
            $linkLogs[] = "After removal: {$linkStats['internal']} internal, {$linkStats['external']} external";
        }

        // 2. Remove excess external links if > 3
        if ($linkStats['external'] > 3) {
            $linkLogs[] = "Removing excess external links ({$linkStats['external']} -> 3)";
            $content = $this->removeExcessExternalLinks($content, 3);
            $linkStats = $this->countExistingLinks($content);
            $linkLogs[] = "After removal: {$linkStats['internal']} internal, {$linkStats['external']} external";
        }

        // 3. Validate & Clean existing external links
        $cleanedData = $this->validateAndCleanLinks($content);
        $content = $cleanedData['html'];
        $externalCount = $cleanedData['count'];
        $linkLogs = array_merge($linkLogs, $cleanedData['logs'] ?? []);

        // 4. Discover and add external links if needed
        if ($externalCount < 1 && !($options['skip_external'] ?? false)) {
            $linkLogs[] = "Discovering external links (current: $externalCount)";
            
            try {
                // Get topic from content (extract from first H1)
                $topic = $this->extractTopicFromContent($content);
                
                if (!$topic) {
                    $linkLogs[] = "ERROR: Could not extract topic from content";
                } else {
                    $linkLogs[] = "Topic extracted: $topic";
                    
                    // Discover candidate URLs
                    $candidateUrls = $this->linkDiscovery->discoverLinks($topic, $category->name);
                    $linkLogs[] = "Found " . count($candidateUrls) . " candidate URLs";

                    $addedLinks = 0;
                    $maxToAdd = min(3, 7 - $linkStats['total']); // Max 7 total (4 internal + 3 external)

                    foreach ($candidateUrls as $url) {
                        if ($addedLinks >= $maxToAdd) {
                            break;
                        }

                        // Skip if URL already exists
                        if (in_array($url, $linkStats['urls'])) {
                            $linkLogs[] = "Skipped duplicate: $url";
                            continue;
                        }

                        // Extract snippet
                        try {
                            $snippet = $this->linkDiscovery->extractSnippet($url);
                            
                            if (!$snippet) {
                                $linkLogs[] = "Skipped (no snippet): $url";
                                continue;
                            }

                            $linkLogs[] = "Snippet extracted (" . strlen($snippet) . " chars) from: $url";

                            // Score relevance with AI
                            try {
                                $relevance = $this->ai->scoreLinkRelevance($topic, $url, $snippet);
                                $linkLogs[] = "AI Score: {$relevance['score']} for $url - {$relevance['reason']}";
                                
                                if ($relevance['score'] >= 75) {
                                    // Insert link
                                    $anchor = $relevance['anchor'] ?: $this->linkDiscovery->extractTitle($url) ?: 'Read more';
                                    $content = $this->insertExternalLink($content, $url, $anchor);
                                    $addedLinks++;
                                    $externalCount++;
                                    $linkLogs[] = "✓ Added external link (score: {$relevance['score']}): $url";
                                } else if ($relevance['score'] === 0 && $addedLinks === 0) {
                                    // Fallback: If AI fails and we have no links yet, add anyway
                                    $anchor = $this->linkDiscovery->extractTitle($url) ?: 'Read more';
                                    $content = $this->insertExternalLink($content, $url, $anchor);
                                    $addedLinks++;
                                    $externalCount++;
                                    $linkLogs[] = "✓ Added external link (AI fallback): $url";
                                } else {
                                    $linkLogs[] = "Skipped (low score {$relevance['score']}): $url";
                                }
                            } catch (\Exception $e) {
                                $linkLogs[] = "ERROR scoring link: " . $e->getMessage();
                                // Fallback: add first link anyway if we have none
                                if ($addedLinks === 0) {
                                    $anchor = $this->linkDiscovery->extractTitle($url) ?: 'Read more';
                                    $content = $this->insertExternalLink($content, $url, $anchor);
                                    $addedLinks++;
                                    $externalCount++;
                                    $linkLogs[] = "✓ Added external link (exception fallback): $url";
                                }
                            }
                        } catch (\Exception $e) {
                            $linkLogs[] = "ERROR extracting snippet from $url: " . $e->getMessage();
                        }
                    }

                    if ($addedLinks === 0) {
                        $linkLogs[] = "WARNING: No external links added despite discovery attempt";
                    }
                }
            } catch (\Exception $e) {
                $linkLogs[] = "ERROR in external link discovery: " . $e->getMessage();
                Log::error("External link discovery failed: " . $e->getMessage());
            }
        }

        // 5. Insert Internal Links if room
        $currentStats = $this->countExistingLinks($content);
        $limitInternal = min(4 - $currentStats['internal'], 7 - $currentStats['total']);
        $internalCount = $currentStats['internal'];
        
        if ($limitInternal > 0 && !($options['skip_internal'] ?? false)) {
            $relatedBlogs = Blog::where('category_id', $category->id)
                ->where('id', '!=', $category->id)
                ->latest()
                ->take($limitInternal)
                ->get();
                
            if ($relatedBlogs->count() > 0) {
                 $internalData = $this->insertInternalLinks($content, $relatedBlogs);
                 $content = $internalData['html'];
                 $internalCount += $internalData['count'];
            }
        }
        
        return [
            'html' => $content,
            'external_count' => $externalCount,
            'internal_count' => $internalCount,
            'logs' => $linkLogs,
            'skipped' => false
        ];
    }



    /**
     * Remove excess external links, keeping only the first N
     */
    protected function removeExcessExternalLinks(string $html, int $maxLinks): string
    {
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $links = $dom->getElementsByTagName('a');
        $siteUrl = config('app.url');
        $externalCount = 0;
        $linksToRemove = [];

        foreach ($links as $link) {
            $href = $link->getAttribute('href');
            
            // Check if external
            if (!empty($href) && $href !== '#' && 
                !str_starts_with($href, '/') && !str_starts_with($href, $siteUrl)) {
                $externalCount++;
                
                // Mark for removal if exceeds limit
                if ($externalCount > $maxLinks) {
                    $linksToRemove[] = $link;
                }
            }
        }

        // Remove excess links
        foreach ($linksToRemove as $link) {
            // Replace link with just its text content
            $textNode = $dom->createTextNode($link->textContent);
            $link->parentNode->replaceChild($textNode, $link);
        }

        $result = $dom->saveHTML();
        return str_replace('<?xml encoding="utf-8" ?>', '', $result);
    }

    /**
     * Clean up duplicate links and surrounding context
     */
    protected function cleanupDuplicateLinks(string $html): string
    {
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $links = $dom->getElementsByTagName('a');
        $seenUrls = [];
        $nodesToRemove = [];

        foreach ($links as $link) {
            $href = $link->getAttribute('href');
            
            if (empty($href)) continue;

            if (isset($seenUrls[$href])) {
                // Duplicate found
                $parent = $link->parentNode;
                
                // Text to remove: "For more details, check out [Link]." or "Also read: [Link]."
                $prevSibling = $link->previousSibling;
                
                if ($prevSibling && $prevSibling->nodeType === XML_TEXT_NODE) {
                    $text = $prevSibling->textContent;
                    // Common introductory phrases to remove
                    $phrases = [
                        'For more details, check out',
                        'Check out',
                        'Also read:',
                        'Read more:',
                        'Related:',
                        'See also:'
                    ];
                    
                    foreach ($phrases as $phrase) {
                        if (stripos(trim($text), $phrase) !== false) {
                            // Clear the text content
                            $prevSibling->textContent = str_ireplace($phrase, '', $text);
                        }
                    }
                }
                
                $nodesToRemove[] = $link;
            } else {
                $seenUrls[$href] = true;
            }
        }

        foreach ($nodesToRemove as $node) {
            $node->parentNode->removeChild($node);
        }

        $result = $dom->saveHTML();
        return str_replace('<?xml encoding="utf-8" ?>', '', $result);
    }

    /**
     * Remove excess internal links, ensuring max count and uniqueness
     */
    protected function removeExcessInternalLinks(string $html, int $maxLinks): string
    {
        // First clean duplicates
        $html = $this->cleanupDuplicateLinks($html);
        
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $links = $dom->getElementsByTagName('a');
        $siteUrl = config('app.url');
        $internalCount = 0;
        $linksToRemove = [];
        $uniqueHrefs = [];

        foreach ($links as $link) {
            $href = $link->getAttribute('href');
            
            // Check if internal
            if (str_starts_with($href, '/') || str_starts_with($href, $siteUrl)) {
                
                // Check uniqueness
                if (in_array($href, $uniqueHrefs)) {
                    $linksToRemove[] = $link;
                    continue;
                }
                
                $internalCount++;
                $uniqueHrefs[] = $href;
                
                // Mark for removal if exceeds limit
                if ($internalCount > $maxLinks) {
                    $linksToRemove[] = $link;
                }
            }
        }

        // Remove excess links
        foreach ($linksToRemove as $link) {
            // Replace link with just its text content
            $textNode = $dom->createTextNode($link->textContent);
            $link->parentNode->replaceChild($textNode, $link);
        }

        $result = $dom->saveHTML();
        return str_replace('<?xml encoding="utf-8" ?>', '', $result);
    }

    /**
     * Extract topic from content (first H1)
     */
    protected function extractTopicFromContent(string $html): ?string
    {
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        $h1 = $dom->getElementsByTagName('h1');
        if ($h1->length > 0) {
            return $h1->item(0)->textContent;
        }

        return null;
    }

    /**
     * Insert external link into content
     */
    protected function insertExternalLink(string $html, string $url, string $anchor): string
    {
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();

        // Find a suitable paragraph to insert after
        $paragraphs = $dom->getElementsByTagName('p');
        
        if ($paragraphs->length > 2) {
            // Insert after 2nd or 3rd paragraph
            $targetIndex = min(2, $paragraphs->length - 1);
            $targetPara = $paragraphs->item($targetIndex);
            
            // Create link element
            $link = $dom->createElement('a', $anchor);
            $link->setAttribute('href', $url);
            $link->setAttribute('rel', 'dofollow');
            $link->setAttribute('target', '_blank');
            
            // Append to paragraph
            $targetPara->appendChild($dom->createTextNode(' '));
            $targetPara->appendChild($link);
        }

        $result = $dom->saveHTML();
        return str_replace('<?xml encoding="utf-8" ?>', '', $result);
    }

    protected function insertInternalLinks(string $html, $relatedBlogs): array
    {
        if ($relatedBlogs->isEmpty()) {
            return ['html' => $html, 'count' => 0];
        }

        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        
        // Build list of existing links to avoid duplicates
        $existingLinks = [];
        foreach ($dom->getElementsByTagName('a') as $link) {
            $href = $link->getAttribute('href');
            if ($href) $existingLinks[] = $href;
        }
        
        $paragraphs = $dom->getElementsByTagName('p');
        $pCount = $paragraphs->length;
        $injectedCount = 0;
        
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
             
             // Get next blog candidate
             $blog = $relatedBlogs[$blogIndex];
             $blogUrl = route('blog.show', $blog->slug);
             
             // Skip if this blog is already linked
             // Check strict match or partial match (if absolute/relative mix)
             $alreadyLinked = false;
             foreach ($existingLinks as $existing) {
                 if (str_contains($existing, $blog->slug)) {
                     $alreadyLinked = true;
                     break;
                 }
             }
             
             if ($alreadyLinked) {
                 $blogIndex++;
                 // Try next blog for this position if we have more
                 if ($blogIndex < $relatedBlogs->count()) {
                     // Retry this position with next blog
                     // But we need to use a loop or just simple retry logic
                     // For simplicity, just skip this position for now to avoid clustering
                     continue;
                 } else {
                     break;
                 }
             }
             
             $targetP = $paragraphs->item($index);
             if ($targetP) {
                 // Create link phrasing
                 $phrases = [
                     "For more details, check out <a href='%s' rel='dofollow'>%s</a>.",
                     "You might also like: <a href='%s' rel='dofollow'>%s</a>.",
                     "Related reading: <a href='%s' rel='dofollow'>%s</a>."
                 ];
                 $phrase = sprintf($phrases[$blogIndex % 3], $blogUrl, $blog->title);
                 
                 $newP = $dom->createElement('p');
                 // Load HTML fragment for the link
                 $frag = $dom->createDocumentFragment();
                 // Use basic replacement to ensure XML safety if title carries bad chars
                 $safePhrase = htmlspecialchars($phrase); 
                 // Actually we can't use htmlspecialchars on the whole string because it has tags.
                 // Better to construct nodes safely.
                 
                 // Re-construction for safety:
                 // Text -> Link -> Text
                 // Logic: "For more details, check out " + Link + "."
                 
                 // Fallback to appendXML for simplicity as phrases are trustworthy (internal)
                 // But handle potential errors
                 if (@$frag->appendXML("<em>$phrase</em>")) {
                     $newP->appendChild($frag);
                     $targetP->parentNode->insertBefore($newP, $targetP->nextSibling);
                     $injectedCount++;
                     $existingLinks[] = $blogUrl; // Add to deny list
                 }
                 
                 $blogIndex++;
             }
        }


        
        $fixed = $dom->saveHTML();
        return [
            'html' => str_replace('<?xml encoding="utf-8" ?>', '', $fixed),
            'count' => $injectedCount
        ];
    }

    protected function validateAndCleanLinks(string $html): array
    {
        $dom = new \DOMDocument();
        libxml_use_internal_errors(true);
        // Ensure UTF-8 is handled correctly
        $dom->loadHTML('<?xml encoding="utf-8" ?>' . $html, LIBXML_HTML_NOIMPLIED | LIBXML_HTML_NODEFDTD);
        libxml_clear_errors();
        
        $xpath = new \DOMXPath($dom);
        $links = $xpath->query('//a');
        $validLinksCount = 0;
        $maxExternal = 4;
        $logs = [];
        
        foreach ($links as $link) {
            $href = $link->getAttribute('href');
            
            // Skip internal or empty links
            if (empty($href) || str_starts_with($href, '/') || str_starts_with($href, '#') || str_contains($href, config('app.url'))) {
                continue;
            }
            
            // Validate URL (Headless check)
            $validation = $this->isValidUrl($href, true); // Pass true for detailed result
            
            if ($validation['valid']) {
                $validLinksCount++;
                // Ensure dofollow
                $link->setAttribute('rel', 'dofollow');
                $link->setAttribute('target', '_blank'); // Safety
                
                // Cap external links
                if ($validLinksCount > $maxExternal) {
                    $logs[] = "Removed (Limit): $href";
                    // Convert to plain text if over limit
                    $text = $dom->createTextNode($link->textContent);
                    $link->parentNode->replaceChild($text, $link);
                    $validLinksCount--; // Adjust count as we removed it
                } else {
                    $logs[] = "Valid: $href";
                }
            } else {
                // Remove invalid link, keep text
                $reason = $validation['reason'] ?? 'Unknown';
                $logs[] = "Removed ($reason): $href";
                Log::warning("Removing invalid link: $href. Reason: $reason");
                $text = $dom->createTextNode($link->textContent);
                $link->parentNode->replaceChild($text, $link);
            }
        }
        
        $fixed = $dom->saveHTML();
        return [
            'html' => str_replace('<?xml encoding="utf-8" ?>', '', $fixed),
            'count' => $validLinksCount,
            'logs' => $logs
        ];
    }

    protected function isValidUrl(string $url, bool $returnDetails = false)
    {
        // Simple filter var check first
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
             return $returnDetails ? ['valid' => false, 'reason' => 'Invalid Format'] : false;
        }
        
        // Perform a quick HEAD request
        try {
             // Use stream context for timeout and USER AGENT
            $context = stream_context_create([
                'http' => [
                    'method' => 'HEAD', 
                    'timeout' => 5, // Increased timeout to 5s
                    'ignore_errors' => true,
                    'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
                ]
            ]);
            
            // Suppress errors but catch them via error_get_last if needed, or just exception check
            $headers = @get_headers($url, 0, $context);
            
            if (!$headers || empty($headers)) {
                 // Fallback to GET
                 $context = stream_context_create([
                    'http' => [
                        'method' => 'GET', 
                        'timeout' => 5, 
                        'ignore_errors' => true,
                        'user_agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
                    ]
                ]);
                $headers = @get_headers($url, 0, $context);
            }

            if (!$headers || empty($headers)) {
                 return $returnDetails ? ['valid' => false, 'reason' => 'No Headers/Connection Failed'] : false;
            }

            // Check status code
            // $headers[0] e.g. "HTTP/1.1 200 OK"
            $statusLine = $headers[0];
            preg_match('/HTTP\/\S+\s(\d{3})/', $statusLine, $matches);
            $code = isset($matches[1]) ? (int)$matches[1] : 0;
            
            if ($code >= 200 && $code < 400) {
                return $returnDetails ? ['valid' => true] : true;
            }
            
            if ($code === 403 || $code === 429) {
                 // Some sites block programmatic access 100%. 
                 // If it's 403, it MIGHT be valid but we are blocked. 
                 // Users prefer functional links. If we can't check it, is it risky to keep it?
                 // Let's assume strict: if we can't verify, we remove.
                 return $returnDetails ? ['valid' => false, 'reason' => "Status $code"] : false;
            }
             
             return $returnDetails ? ['valid' => false, 'reason' => "Status $code"] : false;

        } catch (\Exception $e) {
            return $returnDetails ? ['valid' => false, 'reason' => 'Exception: ' . $e->getMessage()] : false;
        }
        
        return $returnDetails ? ['valid' => false, 'reason' => 'Unknown Failure'] : false;
    }

    /**
     * Build extra AI context for custom tool prompts so we stay on-topic.
     */
    protected function buildToolIntelligenceBlock(string $customPrompt, ?string $scrapedContent, ?string $scrapeUrl): string
    {
        $haystack = mb_strtolower($customPrompt . ' ' . ($scrapedContent ?? ''));

        $intentSignals = [
            'image_compression' => ['image compressor', 'image compression', 'compress images', 'optimize image', 'reduce image size', 'lossless compression', 'webp', 'jpeg compression'],
            'background_removal' => ['background remover', 'remove background', 'bg remover', 'clean background', 'cutout'],
            'conversion' => ['image converter', 'convert images', 'format converter', 'jpg to png', 'png to webp', 'webp to jpg'],
        ];

        $competitors = [
            'image_compression' => ['TinyPNG', 'Kraken.io', 'Optimizilla'],
            'background_removal' => ['Remove.bg', 'Cleanup.pictures', 'Adobe Express Background Remover'],
            'conversion' => ['CloudConvert', 'Convertio', 'Zamzar'],
        ];

        $seoTalkingPoints = [
            'image_compression' => 'Explain how lean images improve Core Web Vitals (LCP, CLS) and overall SEO performance.',
            'background_removal' => 'Show how clean cut-outs accelerate ad creatives, ecommerce PDPs, and social posts.',
            'conversion' => 'Highlight why multi-format delivery keeps omnichannel experiences consistent and fast.',
        ];

        $detectedIntents = [];
        foreach ($intentSignals as $intent => $needles) {
            foreach ($needles as $needle) {
                if (str_contains($haystack, $needle)) {
                    $detectedIntents[] = $intent;
                    break;
                }
            }
        }

        $block = "\n\n[TOOL INTELLIGENCE BLOCK]\n";
        $block .= "Treat this as a senior-level review for the referenced tool. Use scraped copy as factual source material and expand it with your own analysis.\n";

        if ($scrapeUrl) {
            $block .= "Primary Tool URL: {$scrapeUrl} (must be cited with at least one dofollow link in intro or conclusion).\n";
        }

        if (empty($detectedIntents)) {
            $block .= "- Identify the product category and describe why it matters for SEO, UX, and conversions.\n";
            $block .= "- Surface at least three competing tools, comparing pricing, automation depth, and unique workflows.\n";
        } else {
            foreach (array_unique($detectedIntents) as $intent) {
                $competitorList = implode(', ', $competitors[$intent]);
                $block .= "- Competitor Spotlight ({$intent}): {$competitorList}. Compare compression quality, automation, pricing tiers, and API access.\n";
                if (isset($seoTalkingPoints[$intent])) {
                    $block .= "- {$seoTalkingPoints[$intent]}\n";
                }
            }
        }

        $block .= "- Detail why this workflow is essential (tie it to rankings, conversions, or time saved).\n";
        $block .= "- Provide at least 3 actionable steps: onboarding, everyday usage, and optimization tips.\n";
        $block .= "- Include one section dedicated to \"Why this matters for SEO & performance\" and another for \"Alternative tools & when to pick them\".\n";

        $block .= "[END TOOL INTELLIGENCE BLOCK]\n";

        return $block;
    }
}
