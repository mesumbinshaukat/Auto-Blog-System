<?php

namespace App\Services;

use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Facades\Log;

class ScrapingService
{
    protected $client;
    protected $scrapingHub;

    // Lawful RSS Sources (Public feeds, no AI prohibition found in basic checks)
    protected $rssSources = [
        'technology' => [
            'https://www.theverge.com/rss/index.xml',
            'https://www.wired.com/feed/rss',
            'https://feeds.bbci.co.uk/news/technology/rss.xml'
        ],
        'business' => [
            'https://feeds.bbci.co.uk/news/business/rss.xml',
            'https://www.cnbc.com/id/10001147/device/rss/rss.html'
        ],
        'ai' => [
            'https://www.wired.com/feed/tag/ai/latest/rss',
            'https://techcrunch.com/tag/artificial-intelligence/feed/',
            'https://arstechnica.com/tag/artificial-intelligence/rss/'
        ],
        'games' => [
            'https://www.polygon.com/rss/index.xml',
            'https://www.ign.com/rss/articles',
            'https://kotaku.com/rss',
            'https://www.gamespot.com/feeds/mashup/'
        ],
        'sports' => [
            'https://feeds.bbci.co.uk/sport/rss.xml',
            'https://www.espn.com/espn/rss/news',
            'https://sports.yahoo.com/rss/',
            'https://www.foxsports.com/stories/rss'
        ],
        'politics' => [
            'https://rss.politico.com/congress.xml',
            'https://feeds.bbci.co.uk/news/politics/rss.xml'
        ],
        'science' => [
            'https://www.sciencedaily.com/rss/top/science.xml',
            'https://feeds.bbci.co.uk/news/science_and_environment/rss.xml'
        ],
        'health' => [
            'https://feeds.bbci.co.uk/news/health/rss.xml',
            'https://www.sciencedaily.com/rss/top/health.xml' 
        ],
        'tutorial' => [
            'https://www.freecodecamp.org/news/rss/',
            'https://developer.mozilla.org/en-US/blog/rss.xml',
            'https://www.smashingmagazine.com/feed/',
            'https://www.sitepoint.com/feed/',
            'https://css-tricks.com/feed/',
            'https://www.digitalocean.com/community/tutorials.atom',
            'https://www.thecrazyprogrammer.com/feed',
            'https://stackabuse.com/rss/',
            'https://blog.jooq.org/feed/',
            'http://feeds.hanselman.com/ScottHanselman',
            'https://tympanus.net/codrops/feed/',
            'https://blog.codepen.io/feed/',
            'https://hackr.io/programming/rss.xml',
            'https://codesignal.com/feed/',
            'https://stackoverflow.blog/feed/',
            'https://www.johndcook.com/blog/feed/',
            'https://feeds.feedburner.com/helloacm',
            'https://fueled.com/feed/',
            'https://eli.thegreenplace.net/feeds/all.atom.xml',
            'https://catonmat.net/feed',
            'https://www.tutorialsmate.com/feeds/posts/default?alt=rss',
            'https://codingnconcepts.com/index.xml',
            'https://fusion-reactor.com/feed/',
            'https://feeds.feedburner.com/Techgoeasy',
            'https://www.codingvila.com/feeds/posts/default?alt=rss',
            'https://www.vitoshacademy.com/feed/',
            'https://feeds.feedburner.com/abundantcode',
            'https://programesecure.com/feed/',
            'http://yetanothermathprogrammingconsultant.blogspot.com/feeds/posts/default',
            'http://anothercasualcoder.blogspot.com/feeds/posts/default',
            'https://www.blueboxes.co.uk/rss.xml',
            'https://www.amitmerchant.com/feed.xml',
            'https://cmsminds.com/feed/',
            'http://www.philipzucker.com/feed.xml',
            'https://blog.newtum.com/feed/'
        ]
    ];

    public function __construct(?ScrapingHubService $scrapingHub = null)
    {
        $this->client = new Client([
            'timeout'  => 15.0, // Increased timeout for multiple RSS fetches
            'verify' => false,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
            ]
        ]);
        
        // Inject ScrapingHubService (will be null if not configured)
        $this->scrapingHub = $scrapingHub ?? app(ScrapingHubService::class);
    }

    /**
     * Fetch trending topics from Mediastack News API
     * 
     * @param string $category
     * @return array|null Array of topics or null on failure
     */
    protected function fetchTrendingTopicsWithMediastack(string $category): ?array
    {
        $apiKey = env('MEDIA_STACK_KEY');
        
        if (empty($apiKey)) {
            Log::info("MEDIA_STACK_KEY not configured, skipping Mediastack");
            return null;
        }

        try {
            Log::info("Fetching trending topics from Mediastack for category: $category");
            
            $response = \Illuminate\Support\Facades\Http::timeout(15)->get('http://api.mediastack.com/v1/news', [
                'access_key' => $apiKey,
                'categories' => $category,
                'languages' => 'en',
                'limit' => 20,
                'date' => date('Y-m-d'),
            ]);

            if (!$response->successful()) {
                $status = $response->status();
                Log::warning("Mediastack API returned status $status for category: $category");
                
                if ($status === 429) {
                    Log::warning("Mediastack rate limit exceeded");
                } elseif ($status === 403 || $status === 401) {
                    Log::warning("Mediastack quota exceeded or invalid key");
                }
                
                return null;
            }

            $data = $response->json();
            
            if (empty($data['data'])) {
                Log::warning("Mediastack returned empty data for category: $category");
                return null;
            }

            $topics = [];
            foreach ($data['data'] as $article) {
                if (!empty($article['title'])) {
                    $topics[] = trim($article['title']);
                }
                
                if (count($topics) >= 10) break;
            }

            Log::info("Mediastack returned " . count($topics) . " topics for category: $category");
            return $topics;

        } catch (\Exception $e) {
            Log::error("Mediastack API error for category $category: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Research topic using Mediastack News API
     * 
     * @param string $topic
     * @return string|null Research data or null on failure
     */
    protected function researchTopicWithMediastack(string $topic): ?string
    {
        $apiKey = env('MEDIA_STACK_KEY');
        
        if (empty($apiKey)) {
            return null;
        }

        try {
            Log::info("Researching topic via Mediastack: $topic");
            
            $response = \Illuminate\Support\Facades\Http::timeout(15)->get('http://api.mediastack.com/v1/news', [
                'access_key' => $apiKey,
                'keywords' => $topic,
                'languages' => 'en',
                'limit' => 5,
                'sort' => 'published_desc',
            ]);

            if (!$response->successful()) {
                Log::warning("Mediastack research failed for topic: $topic (status: {$response->status()})");
                return null;
            }

            $data = $response->json();
            
            if (empty($data['data'])) {
                Log::warning("Mediastack research returned no data for topic: $topic");
                return null;
            }

            $researchData = [];
            foreach ($data['data'] as $article) {
                $title = $article['title'] ?? 'Untitled';
                $description = $article['description'] ?? '';
                $url = $article['url'] ?? '';
                
                // Truncate description to 1000 chars
                $description = $this->truncateSnippet($description, 1000);
                
                $researchData[] = "Source: Mediastack News ($title)\nFrom: $url\n$description";
            }

            Log::info("Mediastack research found " . count($researchData) . " articles for topic: $topic");
            return "Research findings from Mediastack:\n" . implode("\n\n", $researchData);

        } catch (\Exception $e) {
            Log::error("Mediastack research error for topic $topic: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Truncate snippet to specified length
     * 
     * @param string $text
     * @param int $maxLength
     * @return string
     */
    protected function truncateSnippet(string $text, int $maxLength = 1000): string
    {
        if (strlen($text) <= $maxLength) {
            return $text;
        }
        
        return substr($text, 0, $maxLength) . '...';
    }

    public function fetchTrendingTopics(string $category): array
    {
        $category = strtolower($category);
        
        // Try Scraping Hub API first
        if ($this->scrapingHub && $this->scrapingHub->isAvailable()) {
            try {
                Log::info("Attempting to fetch topics from Scraping Hub for category: $category");
                $query = "$category related articles 2025";
                $results = $this->scrapingHub->news($query, 20);
                
                if ($results && count($results) >= 5) {
                    $topics = [];
                    foreach ($results as $result) {
                        if (isset($result['title']) && !empty($result['title'])) {
                            $topics[] = trim($result['title']);
                        }
                    }
                    
                    $topics = array_unique($topics);
                    
                    if (count($topics) >= 5) {
                        Log::info("Using Scraping Hub topics for category: $category (" . count($topics) . " topics)");
                        return array_slice($topics, 0, 10);
                    }
                }
                
                Log::info("Scraping Hub topics failed or empty, falling back");
            } catch (\Exception $e) {
                Log::warning("ScrapingHub topics fail: {$e->getMessage()}, falling back");
            }
        }
        
        // Try Mediastack second
        $mediastackTopics = $this->fetchTrendingTopicsWithMediastack($category);
        if ($mediastackTopics && count($mediastackTopics) >= 5) {
            Log::info("Using Mediastack topics for category: $category");
            return $mediastackTopics;
        }
        
        // Fallback to RSS
        Log::info("Falling back to RSS for category: $category");
        $sources = $this->rssSources[$category] ?? [];
        
        // Add random variation to avoid stale topics
        shuffle($sources);
        $sources = array_slice($sources, 0, 3); // Check up to 3 sources per run

        $topics = [];

        foreach ($sources as $url) {
            try {
                Log::info("Fetching RSS from: $url");
                
                $items = null;
                
                // Try Scraping Hub RSS first
                if ($this->scrapingHub && $this->scrapingHub->isAvailable()) {
                    try {
                        $results = $this->scrapingHub->rss($url);
                        if ($results && count($results) > 0) {
                            Log::info("Successfully fetched RSS via Scraping Hub: $url");
                            $items = $results;
                        }
                    } catch (\Exception $shEx) {
                        Log::warning("ScrapingHub RSS fetch failed for $url: " . $shEx->getMessage());
                    }
                }

                if ($items) {
                    $itemCount = 0;
                    foreach ($items as $item) {
                        $title = $item['title'] ?? '';
                        $pubDateStr = $item['pubDate'] ?? '';
                        $isRecent = true;
                        if (!empty($pubDateStr)) {
                            $pubDate = strtotime($pubDateStr);
                            if ($pubDate && (time() - $pubDate > 604800)) {
                                $isRecent = false;
                            }
                        }
                        
                        if ($isRecent && !empty($title)) {
                            $topics[] = trim($title);
                            $itemCount++;
                        }

                        if (count($topics) >= 8) break;
                    }
                    Log::info("RSS $url (via ScrapingHub) returned $itemCount recent topics");
                } else {
                    // Fallback to manual Guzzle
                    try {
                        $response = $this->client->get($url, ['timeout' => 10]);
                        
                        if ($response->getStatusCode() !== 200) {
                            Log::warning("RSS $url returned status " . $response->getStatusCode() . ", skipping");
                            continue;
                        }
                        
                        $xmlContent = $response->getBody()->getContents();
                        if (empty(trim($xmlContent))) {
                            Log::warning("RSS $url returned empty content, skipping");
                            continue;
                        }
                        
                        // Use internal errors for cleaner handling
                        libxml_use_internal_errors(true);
                        $rss = simplexml_load_string($xmlContent, 'SimpleXMLElement', LIBXML_NOCDATA);
                        
                        if ($rss === false) {
                            $errors = libxml_get_errors();
                            Log::warning("RSS $url failed to parse XML: " . (isset($errors[0]) ? $errors[0]->message : 'Unknown error'));
                            libxml_clear_errors();
                            continue;
                        }

                        $rssItems = $rss->channel->item ?? $rss->entry ?? [];
                        
                        $itemCount = 0;
                        foreach ($rssItems as $item) {
                            $title = (string)($item->title ?? '');
                            if (empty($title)) continue;

                            $pubDateStr = (string)($item->pubDate ?? $item->updated ?? $item->published ?? 'now');
                            $pubDate = strtotime($pubDateStr);
                            
                            if (!$pubDate || (time() - $pubDate < 604800)) { 
                                $topics[] = trim($title);
                                $itemCount++;
                            }

                            if (count($topics) >= 8) break;
                        }
                        Log::info("RSS $url (via Guzzle) returned $itemCount recent topics");
                    } catch (\GuzzleHttp\Exception\GuzzleException $gEx) {
                        Log::warning("Guzzle RSS fetch failed for $url: " . $gEx->getMessage());
                    }
                }

            } catch (\Exception $e) {
                Log::warning("RSS Processing failed for $url: " . $e->getMessage());
            }

            if (count($topics) >= 10) break; 
        }

        // De-duplicate
        $topics = array_unique(array_filter($topics));

        // Fallback: If RSS empty and category is tutorial, search for topics
        if (empty($topics) && $category === 'tutorial' && $this->scrapingHub && $this->scrapingHub->isAvailable()) {
            try {
                Log::info("Tutorial RSS empty, attempting search-based topic discovery");
                $searchQuery = "latest technical tutorials programming how-to 2026";
                $searchResults = $this->scrapingHub->search($searchQuery, 10);
                
                if ($searchResults && count($searchResults) > 0) {
                    foreach ($searchResults as $res) {
                        if (!empty($res['title'])) {
                            $topics[] = $res['title'];
                        }
                    }
                    Log::info("Found " . count($topics) . " tutorial topics via search fallback");
                }
            } catch (\Exception $searchEx) {
                Log::error("Tutorial search fallback failed: " . $searchEx->getMessage());
            }
        }

        // Fallback to static topics if still empty
        if (empty($topics)) {
            Log::warning("All search and RSS sources failed for $category, using fallback topics");
            return $this->fetchFallbackTopics($category);
        }
        
        // Ensure minimum topic count
        if (count($topics) < 5) {
            Log::info("Only " . count($topics) . " topics found for $category, supplementing with fallbacks");
            $fallbacks = $this->fetchFallbackTopics($category);
            $topics = array_merge($topics, array_slice($fallbacks, 0, 5 - count($topics)));
        }

        return array_values(array_unique($topics));
    }

    public function scrapeContent(string $url): string
    {
        // Try Scraping Hub API first
        if ($this->scrapingHub && $this->scrapingHub->isAvailable()) {
            try {
                Log::info("Attempting to scrape URL via Scraping Hub: $url");
                $result = $this->scrapingHub->scrape($url);
                
                if ($result && !empty($result['content'])) {
                    Log::info("Successfully scraped URL via Scraping Hub: $url");
                    return $result['content'];
                }
                
                Log::info("Scraping Hub returned no content, falling back to Guzzle");
            } catch (\Exception $e) {
                Log::warning("Scraping Hub scrape failed: {$e->getMessage()}, falling back to Guzzle");
            }
        }
        
        // Fallback to Guzzle/Crawler
        try {
            // Simple sleep for politeness
            usleep(500000); // 0.5s

            // Use Http facade with user agent
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
            ])->timeout(15)->get($url);
            
            if (!$response->successful()) {
                Log::warning("Scraping $url returned status: " . $response->status());
                return "";
            }
            
            $html = $response->body();
            $crawler = new Crawler($html);

            // Extract Main Article Content
            // Heuristic: Identify main container by density or common tags
            $text = $crawler->filter('article p, main p, .post-content p, .article-body p, body p')->each(function (Crawler $node) {
                $t = trim($node->text());
                // Filter sidebar noise
                if (strlen($t) < 50) return null;
                return $t;
            });

            // Filter nulls
            $text = array_filter($text);

            return implode("\n\n", array_slice($text, 0, 25));

        } catch (\Exception $e) {
            Log::error("Scraping URL $url failed: " . $e->getMessage());
            return "";
        }
    }

    /**
     * Research a topic by scraping multiple sources
     */
    public function researchTopic(string $topic): string
    {
        $researchData = [];
        
        // 0. Try Scraping Hub API first
        if ($this->scrapingHub && $this->scrapingHub->isAvailable()) {
            try {
                // Check if topic contains a URL
                if (preg_match('/https?:\/\/[^\s]+/', $topic, $matches)) {
                    $url = $matches[0];
                    Log::info("Topic contains URL, scraping via Scraping Hub: $url");
                    $scraped = $this->scrapingHub->scrape($url);
                    if ($scraped && !empty($scraped['content'])) {
                        $researchData[] = "Scraped content from URL ($url):\n" . $scraped['content'];
                    }
                }

                Log::info("Attempting to research topic via Scraping Hub search: $topic");
                $results = $this->scrapingHub->search($topic, 5);
                
                // Tutorial category specialized research fallback
                $isTutorial = preg_match('/^(How to|Tutorial|Guide|Step-by-step|Installing|Setting up)/i', $topic) || str_contains(strtolower($topic), 'tutorial');
                if ($isTutorial && (empty($results) || count($results) < 3)) {
                    Log::info("Tutorial topic detected with sparse results, trying specialized query: latest $topic tutorial 2026");
                    $specialResults = $this->scrapingHub->search("latest $topic tutorial 2026", 5);
                    if ($specialResults) {
                        $results = array_merge($results ?? [], $specialResults);
                    }
                }

                if ($results && count($results) > 0) {
                    $scrapingHubData = [];
                    foreach ($results as $result) {
                        $title = $result['title'] ?? 'Untitled';
                        $url = $result['url'] ?? '';
                        $snippet = $result['snippet'] ?? '';
                        
                        $scrapingHubData[] = "Source: Scraping Hub ($title)\nFrom: $url\n$snippet";
                        
                        if (count($scrapingHubData) >= 5) break;
                    }
                    
                    if (!empty($scrapingHubData)) {
                        $researchData[] = "Research findings from Scraping Hub Search:\n" . implode("\n\n", $scrapingHubData);
                        Log::info("Scraping Hub research found " . count($scrapingHubData) . " articles for topic: $topic");
                    }
                }
            } catch (\Exception $e) {
                Log::warning("Scraping Hub research failed: {$e->getMessage()}");
            }
        }
        
        // 1. Try Mediastack News API
        $mediastackResearch = $this->researchTopicWithMediastack($topic);
        if ($mediastackResearch) {
            $researchData[] = $mediastackResearch;
        }
        
        // 2. Wikipedia Search API (Better reliably than guessing URL)
        try {
            $searchUrl = "https://en.wikipedia.org/w/api.php";
            Log::info("Searching Wikipedia API for: $topic");
            
            // Use Http facade for easier testing/mocking
            $response = \Illuminate\Support\Facades\Http::timeout(10)->get($searchUrl, [
                'action' => 'query',
                'list' => 'search',
                'srsearch' => $topic,
                'format' => 'json',
                'srlimit' => 1
            ]);
            
            $json = $response->json();
            
            if (!empty($json['query']['search'][0])) {
                $result = $json['query']['search'][0];
                $title = $result['title'];
                $snippet = strip_tags($result['snippet']);
                $pageUrl = "https://en.wikipedia.org/wiki/" . str_replace(' ', '_', $title);
                
                Log::info("Wikipedia Found: '$title'. Scraping URL: $pageUrl");
                
                $content = $this->scrapeContent($pageUrl);
                if (!empty($content)) {
                    $researchData[] = "Source: Wikipedia ($title)\nFrom: $pageUrl\n$content";
                } else {
                    // Use API snippet as fallback if scraping fails
                    $researchData[] = "Source: Wikipedia ($title - Snippet)\n$snippet...";
                }
            } else {
                Log::warning("Wikipedia search returned no results for: $topic");
            }
        } catch (\Exception $e) {
            Log::warning("Wikipedia research failed: " . $e->getMessage());
        }

        // 3. Ultimate Fallback: General Web Search if we have nothing yet (or very little)
        if (count($researchData) < 2) {
             try {
                Log::info("Research data insufficient, performing broad web search for: $topic");
                $webSearchResult = $this->callWebSearch("overview of $topic");
                if ($webSearchResult) {
                    $researchData[] = $webSearchResult;
                    
                    // Also try to scrape the first result if it contains a URL
                    if (preg_match('/https?:\/\/[^\s]+/', $webSearchResult, $urlMatches)) {
                        $topUrl = rtrim($urlMatches[0], "()");
                        Log::info("Attempting to scrape top search result: $topUrl");
                        $scraped = $this->scrapeContent($topUrl);
                        if (!empty($scraped)) {
                            // Truncate to ensure it fits research data limits
                            $researchData[] = "Cleaned overview from $topUrl:\n" . substr($scraped, 0, 2000);
                        }
                    }
                }
            } catch (\Exception $e) {
                Log::warning("Ultimate research fallback failed: " . $e->getMessage());
            }
        }

        return !empty($researchData) 
            ? "Research findings:\n" . implode("\n\n", $researchData)
            : "No external research available. Please generate content based on general knowledge about this topic.";
    }

    /**
     * Fallback web search using Serper API
     */
    protected function callWebSearch(string $query): ?string
    {
        $apiKey = env('SERPER_API_KEY');
        if (empty($apiKey)) {
            Log::info("SERPER_API_KEY not configured, skipping web search");
            return null;
        }

        try {
            Log::info("Attempting web search for: $query");
            
            $response = \Illuminate\Support\Facades\Http::withHeaders([
                'X-API-KEY' => $apiKey,
                'Content-Type' => 'application/json'
            ])->timeout(10)->post('https://google.serper.dev/search', [
                'q' => $query,
                'num' => 1
            ]);
            
            if ($response->successful()) {
                $data = $response->json();
                $snippet = $data['organic'][0]['snippet'] ?? null;
                $link = $data['organic'][0]['link'] ?? null;
                
                if ($snippet) {
                    Log::info("Web search successful for: $query");
                    $source = $link ? "Source: Web Search ($link)" : "Source: Web Search";
                    return "$source\n$snippet";
                }
            }
            
            Log::warning("Web search returned no useful results");
            return null;
        } catch (\Exception $e) {
            Log::error("Web search failed: " . $e->getMessage());
            return null;
        }
    }

    public function fetchFallbackTopics(string $category): array
    {
        $fallbacks = [
            'technology' => [
                'Future of AI in 2025', 
                'Best Programming Languages for Developers', 
                'Web Assembly Complete Guide',
                'Quantum Computing Breakthroughs',
                'Cybersecurity Best Practices',
                'Cloud Computing Architecture Trends',
                'DevOps and CI/CD Pipeline Optimization',
                'Blockchain Technology Beyond Cryptocurrency',
                'Edge Computing and IoT Integration',
                'Low-Code Development Platforms Revolution',
                '5G Technology Impact on Industries',
                'Serverless Architecture Best Practices',
                'Microservices Architecture Design Patterns',
                'Container Orchestration with Kubernetes',
                'Database Optimization Techniques',
                'API Design Best Practices',
                'Software Testing Automation Strategies',
                'Clean Code Architecture Principles',
                'Version Control and Git Workflows',
                'Frontend Framework Comparison 2025',
                'Backend Technology Stack Selection',
                'Mobile App Development Trends',
                'Progressive Web Applications Guide',
                'Docker and Containerization',
                'Infrastructure as Code',
                'Tech Career Growth Strategies'
            ],
            'business' => [
                'Remote Work Trends and Strategies', 
                'Startup Funding Guide', 
                'Leadership Skills for Modern Managers',
                'Digital Transformation in Business',
                'Sustainable Business Practices',
                'Customer Experience Optimization',
                'Data-Driven Decision Making',
                'Agile Project Management Techniques',
                'E-commerce Growth Strategies',
                'Brand Building in Digital Age',
                'Supply Chain Innovation',
                'Employee Retention Best Practices',
                'Business Model Innovation',
                'Strategic Planning Frameworks',
                'Financial Management for Startups',
                'Marketing Automation Tools',
                'Sales Funnel Optimization',
                'Corporate Social Responsibility',
                'Change Management Strategies',
                'Risk Management Techniques',
                'Performance Metrics and KPIs',
                'Negotiation Skills for Leaders',
                'Team Building and Collaboration',
                'Innovation and Creativity in Business',
                'Competitive Analysis Methods',
                'Business Ethics and Governance'
            ],
            'ai' => [
                'Generative AI Explained', 
                'LLM Fine-tuning Techniques', 
                'Ethical AI Development',
                'AI in Healthcare Applications',
                'Machine Learning Best Practices',
                'Natural Language Processing Advances',
                'Computer Vision Applications',
                'AI Model Deployment Strategies',
                'Reinforcement Learning Use Cases',
                'AI Bias Detection and Mitigation',
                'Neural Network Architecture Design',
                'AI-Powered Automation Tools',
                'Deep Learning Fundamentals',
                'Transfer Learning Techniques',
                'Prompt Engineering Best Practices',
                'AI Model Evaluation Metrics',
                'Transformer Models Explained',
                'AI in Finance and Trading',
                'Conversational AI Development',
                'AI for Predictive Analytics',
                'Federated Learning Applications',
                'AI Model Optimization Techniques',
                'Edge AI and On-Device Intelligence',
                'AI Safety and Alignment',
                'Multimodal AI Systems',
                'AI in Creative Industries'
            ],
            'games' => [
                'Top RPGs of the Year', 
                'Indie Game Development Guide', 
                'Esports Industry Growth',
                'Game Design Principles',
                'Virtual Reality Gaming Trends',
                'Mobile Gaming Market Analysis',
                'Game Monetization Strategies',
                'Cross-Platform Gaming Development',
                'Narrative Design in Video Games',
                'Game Engine Comparison Guide',
                'Multiplayer Game Architecture',
                'Gaming Community Management',
                'Level Design Best Practices',
                'Character Development in Games',
                'Game Audio and Sound Design',
                'Player Retention Strategies',
                'Game Testing and Quality Assurance',
                'Procedural Content Generation',
                'Game AI Programming',
                'Open World Game Design',
                'Battle Royale Game Mechanics',
                'Puzzle Game Design Patterns',
                'Horror Game Development',
                'Fighting Game Mechanics',
                'Strategy Game Design',
                'Cloud Gaming Technology'
            ],
            'politics' => [
                'Global Climate Policies', 
                'Digital Privacy Laws',
                'International Relations Updates',
                'Democratic Governance Trends',
                'Policy Making in Digital Age',
                'Electoral System Reforms',
                'Public Policy Analysis Methods',
                'Government Transparency Initiatives',
                'Political Campaign Strategies',
                'Legislative Process Explained',
                'Civic Engagement in Modern Society',
                'Political Communication Strategies',
                'Voting Rights and Access',
                'Public Opinion and Polling',
                'Political Lobbying Regulations',
                'Constitutional Reform Debates',
                'Political Party Organization',
                'Grassroots Political Movements',
                'Media and Political Discourse',
                'Economic Policy Frameworks',
                'Healthcare Policy Reform',
                'Education Policy Innovations',
                'Immigration Policy Analysis',
                'National Security Strategies',
                'Foreign Policy Priorities'
            ],
            'science' => [
                'Latest Space Exploration Discoveries',
                'Climate Change Research Updates',
                'Breakthrough Medical Research',
                'Physics and Quantum Mechanics',
                'Environmental Conservation Efforts',
                'Renewable Energy Technologies',
                'Genetic Engineering Advances',
                'Ocean Exploration Findings',
                'Neuroscience Research Breakthroughs',
                'Materials Science Innovations',
                'Astronomy and Cosmology Updates',
                'Biotechnology Applications',
                'Chemistry and Molecular Biology',
                'Evolutionary Biology Insights',
                'Nanotechnology Developments',
                'Particle Physics Discoveries',
                'Earth Science and Geology',
                'Atmospheric Science Research',
                'Marine Biology Exploration',
                'Microbiology and Immunology',
                'Scientific Method and Research',
                'Data Science in Scientific Research',
                'Laboratory Technology Advances',
                'Science Communication Strategies',
                'Interdisciplinary Research Approaches'
            ],
            'health' => [
                'Mental Health Awareness',
                'Nutrition and Wellness Tips',
                'Exercise and Fitness Trends',
                'Medical Technology Advances',
                'Preventive Healthcare Strategies',
                'Sleep Science and Optimization',
                'Stress Management Techniques',
                'Chronic Disease Prevention',
                'Holistic Health Approaches',
                'Telemedicine and Digital Health',
                'Immunology Research Updates',
                'Healthy Aging Strategies',
                'Mindfulness and Meditation',
                'Cardiovascular Health',
                'Diabetes Management and Prevention',
                'Cancer Research Advances',
                'Alternative Medicine Practices',
                'Women\'s Health Issues',
                'Men\'s Health Concerns',
                'Pediatric Health and Development',
                'Geriatric Care Best Practices',
                'Public Health Initiatives',
                'Healthcare Access and Equity',
                'Medical Ethics and Policy',
                'Health Insurance Navigation'
            ],
            'sports' => [
                'Training for Marathon Success', 
                'Nutrition for Athletes',
                'Sports Psychology Techniques',
                'Injury Prevention Strategies',
                'Professional Sports Analysis',
                'Strength and Conditioning Programs',
                'Sports Analytics and Data Science',
                'Youth Sports Development',
                'Recovery and Rehabilitation Methods',
                'Sports Equipment Technology',
                'Coaching Strategies and Tactics',
                'Performance Enhancement Techniques',
                'Olympic Sports Training',
                'Team Sports Strategy',
                'Individual Sports Excellence',
                'Sports Biomechanics',
                'Athletic Career Development',
                'Sports Business and Marketing',
                'Fan Engagement Strategies',
                'Sports Betting and Analytics',
                'Extreme Sports Safety',
                'Adaptive Sports Programs',
                'Sports Medicine Advances',
                'Competitive Sports Psychology',
                'Sports Technology Innovation'
            ],
            'tutorial' => [
                'How to Start Programming in 2026',
                'How to Build a Progressive Web App (PWA)',
                'How to Setup WordPress on Windows 11',
                'How to Delete Files in Ubuntu Using Terminal',
                'How to Use Git for Version Control',
                'How to Deploy a Laravel App to Vercel',
                'How to Optimize Images for Web Performance',
                'How to Secure Your API with JWT',
                'How to Learn Python for Data Science',
                'How to Create a RESTful API in Node.js',
                'How to Install Docker on Mac',
                'How to Debug JavaScript Errors',
                'How to Setup a VPN on Android',
                'How to Use VS Code Extensions Effectively',
                'How to Build a Simple Machine Learning Model',
                'How to Migrate from HTTP to HTTPS',
                'How to Use Tailwind CSS in Projects',
                'How to Handle Errors in PHP',
                'How to Optimize SQL Queries',
                'How to Create Custom WordPress Plugins',
                'How to Use React Hooks',
                'How to Setup CI/CD with GitHub Actions',
                'How to Secure Linux Servers',
                'How to Analyze Website Traffic with Google Analytics',
                'How to Build a Chatbot with AI',
                'How to Setup Laravel Development Environment',
                'How to Use Docker Compose for Multi-Container Apps',
                'How to Implement Authentication in Next.js',
                'How to Write Unit Tests in Laravel'
            ]
        ];
        
        // Return category-specific topics, or generic fallbacks if category not found
        return $fallbacks[strtolower($category)] ?? [
            'Current Industry Trends and Analysis',
            'Expert Insights and Professional Opinions',
            'Future Predictions and Forecasts',
            'Innovation and Technology Updates',
            'Best Practices and Industry Guidelines',
            'Market Analysis and Growth Opportunities',

            'Professional Development Tips',
            'Case Studies and Success Stories',
            'Emerging Trends and Patterns',
            'Strategic Planning Approaches'
        ];
    }
}
