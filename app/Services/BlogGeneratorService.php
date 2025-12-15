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
        
        // 5c. Link Management & SEO Optimization
        $onProgress && $onProgress('Optimizing Link Structure & SEO...', 75);
        
        try {
            $seoResult = $this->processSeoLinks($finalContent, $category);
            $finalContent = $seoResult['html'];
            
            // Log SEO actions for debugging
            if (!empty($seoResult['logs'])) {
                Log::info("SEO Optimization Logs for '$topic': " . json_encode($seoResult['logs']));
            }
        } catch (\Exception $e) {
            Log::error("SEO Optimization failed during generation: " . $e->getMessage());
            // Continue with original content if SEO fails, but log it
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
}
