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
        $sources = array_slice($sources, 0, 2); // Check up to 2 sources per run

        $topics = [];

        foreach ($sources as $url) {
            try {
                Log::info("Fetching RSS from: $url");
                
                $items = null;
                
                // Try Scraping Hub RSS first
                if ($this->scrapingHub && $this->scrapingHub->isAvailable()) {
                    $results = $this->scrapingHub->rss($url);
                    if ($results && count($results) > 0) {
                        Log::info("Successfully fetched RSS via Scraping Hub: $url");
                        $items = $results;
                    }
                }

                if ($items) {
                    $itemCount = 0;
                    foreach ($items as $item) {
                        $title = $item['title'] ?? '';
                        // Filter: Only recent (last 7 days) if pubDate exists
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

                        if (count($topics) >= 5) break;
                    }
                    Log::info("RSS $url (via ScrapingHub) returned $itemCount recent topics");
                } else {
                    // Fallback to manual Guzzle
                    $response = $this->client->get($url);
                    
                    if ($response->getStatusCode() === 404) {
                        Log::warning("RSS $url returned 404, skipping");
                        continue;
                    }
                    
                    $xmlContent = $response->getBody()->getContents();
                    
                    if (empty(trim($xmlContent))) {
                        Log::warning("RSS $url returned empty content, skipping");
                        continue;
                    }
                    
                    $rss = simplexml_load_string($xmlContent, 'SimpleXMLElement', LIBXML_NOCDATA);
                    
                    if ($rss === false) {
                        Log::warning("RSS $url failed to parse XML, skipping");
                        continue;
                    }

                    $rssItems = $rss->channel->item ?? $rss->entry ?? [];
                    
                    if (empty($rssItems)) {
                        Log::warning("RSS $url returned empty feed, skipping");
                        continue;
                    }
                    
                    $itemCount = 0;
                    foreach ($rssItems as $item) {
                        $title = (string)$item->title;
                        $pubDate = strtotime((string)($item->pubDate ?? $item->updated ?? 'now'));
                        
                        if (time() - $pubDate < 604800) { 
                            $topics[] = trim($title);
                            $itemCount++;
                        }

                        if (count($topics) >= 5) break;
                    }
                    Log::info("RSS $url (via Guzzle) returned $itemCount recent topics");
                }

            } catch (\GuzzleHttp\Exception\ClientException $e) {
                if ($e->getResponse()->getStatusCode() === 404) {
                    Log::warning("RSS $url returned 404, skipping");
                } else {
                    Log::warning("RSS Fetch failed for $url: " . $e->getMessage());
                }
            } catch (\Exception $e) {
                Log::warning("RSS Fetch failed for $url: " . $e->getMessage());
            }

            if (count($topics) >= 5) break; // Stop if we have enough
        }

        // De-duplicate
        $topics = array_unique($topics);

        // Fallback to static topics if empty
        if (empty($topics)) {
            Log::warning("All RSS sources failed or returned no topics for $category, using fallback topics");
            return $this->fetchFallbackTopics($category);
        }
        
        // Ensure minimum topic count
        if (count($topics) < 5) {
            Log::info("Only " . count($topics) . " topics found for $category, supplementing with fallbacks");
            $fallbacks = $this->fetchFallbackTopics($category);
            $topics = array_merge($topics, array_slice($fallbacks, 0, 5 - count($topics)));
        }

        return $topics;
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
                
                if ($results && count($results) > 0) {
                    $scrapingHubData = [];
                    foreach ($results as $result) {
                        $title = $result['title'] ?? 'Untitled';
                        $url = $result['url'] ?? '';
                        $snippet = $result['snippet'] ?? '';
                        
                        $scrapingHubData[] = "Source: Scraping Hub ($title)\nFrom: $url\n$snippet";
                        
                        if (count($scrapingHubData) >= 3) break;
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
                'Serverless Architecture Best Practices'
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
                'Employee Retention Best Practices'
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
                'AI-Powered Automation Tools'
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
                'Gaming Community Management'
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
                'Civic Engagement in Modern Society'
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
                'Biotechnology Applications'
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
                'Healthy Aging Strategies'
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
                'Performance Enhancement Techniques'
            ]
        ];
        return $fallbacks[strtolower($category)] ?? [
            'Current Industry Trends',
            'Expert Analysis and Insights',
            'Future Predictions and Forecasts',
            'Innovation and Technology Updates',
            'Best Practices and Guidelines',
            'Market Analysis and Opportunities',
            'Professional Development Tips',
            'Case Studies and Success Stories',
            'Emerging Trends and Patterns',
            'Strategic Planning Approaches'
        ];
    }
}
