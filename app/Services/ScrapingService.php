<?php

namespace App\Services;

use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Facades\Log;

class ScrapingService
{
    protected $client;

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
            'https://www.sciencedaily.com/rss/computers_math/artificial_intelligence.xml',
            'https://feeds.arstechnica.com/arstechnica/technology-lab'
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

    public function __construct()
    {
        $this->client = new Client([
            'timeout'  => 15.0, // Increased timeout for multiple RSS fetches
            'verify' => false,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36 (KHTML, like Gecko) Chrome/91.0.4472.124 Safari/537.36'
            ]
        ]);
    }

    public function fetchTrendingTopics(string $category): array
    {
        $category = strtolower($category);
        $sources = $this->rssSources[$category] ?? [];
        
        // Add random variation to avoid stale topics
        shuffle($sources);
        $sources = array_slice($sources, 0, 2); // Check up to 2 sources per run

        $topics = [];

        foreach ($sources as $url) {
            try {
                Log::info("Fetching RSS from: $url");
                $response = $this->client->get($url);
                
                // Check for 404 specifically
                if ($response->getStatusCode() === 404) {
                    Log::warning("RSS $url returned 404, skipping");
                    continue;
                }
                
                $xmlContent = $response->getBody()->getContents();
                
                // Check for empty response
                if (empty(trim($xmlContent))) {
                    Log::warning("RSS $url returned empty content, skipping");
                    continue;
                }
                
                // Parse RSS
                $rss = simplexml_load_string($xmlContent, 'SimpleXMLElement', LIBXML_NOCDATA);
                
                if ($rss === false) {
                    Log::warning("RSS $url failed to parse XML, skipping");
                    continue;
                }

                $items = $rss->channel->item ?? $rss->entry ?? []; // Handle RSS 2.0 and Atom
                
                // Check for empty feed
                if (empty($items)) {
                    Log::warning("RSS $url returned empty feed, skipping");
                    continue;
                }
                
                $itemCount = 0;
                foreach ($items as $item) {
                    $title = (string)$item->title;
                    $pubDate = strtotime((string)($item->pubDate ?? $item->updated ?? 'now'));
                    
                    // Filter: Only recent (last 7 days)
                    if (time() - $pubDate < 604800) { 
                        $topics[] = trim($title);
                        $itemCount++;
                    }

                    if (count($topics) >= 5) break; // Limit per source
                }
                
                Log::info("RSS $url returned $itemCount recent topics");

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
        
        // 1. Wikipedia Search API (Better reliably than guessing URL)
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
                // Try web search fallback
                $webSearchResult = $this->callWebSearch($topic);
                if ($webSearchResult) {
                    $researchData[] = $webSearchResult;
                }
            }
        } catch (\Exception $e) {
            Log::warning("Wikipedia research failed: " . $e->getMessage());
            // Try web search fallback on exception
            try {
                $webSearchResult = $this->callWebSearch($topic);
                if ($webSearchResult) {
                    $researchData[] = $webSearchResult;
                }
            } catch (\Exception $webEx) {
                Log::warning("Web search fallback also failed: " . $webEx->getMessage());
            }
        }

        // 2. Add more sources here in future (e.g. Bing Search API if key available)

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

    protected function fetchFallbackTopics(string $category): array
    {
        $fallbacks = [
            'technology' => [
                'Future of AI in 2025', 
                'Best Programming Languages for Developers', 
                'Web Assembly Complete Guide',
                'Quantum Computing Breakthroughs',
                'Cybersecurity Best Practices'
            ],
            'business' => [
                'Remote Work Trends and Strategies', 
                'Startup Funding Guide', 
                'Leadership Skills for Modern Managers',
                'Digital Transformation in Business',
                'Sustainable Business Practices'
            ],
            'ai' => [
                'Generative AI Explained', 
                'LLM Fine-tuning Techniques', 
                'Ethical AI Development',
                'AI in Healthcare Applications',
                'Machine Learning Best Practices'
            ],
            'games' => [
                'Top RPGs of the Year', 
                'Indie Game Development Guide', 
                'Esports Industry Growth',
                'Game Design Principles',
                'Virtual Reality Gaming Trends'
            ],
            'politics' => [
                'Global Climate Policies', 
                'Digital Privacy Laws',
                'International Relations Updates',
                'Democratic Governance Trends',
                'Policy Making in Digital Age'
            ],
            'science' => [
                'Latest Space Exploration Discoveries',
                'Climate Change Research Updates',
                'Breakthrough Medical Research',
                'Physics and Quantum Mechanics',
                'Environmental Conservation Efforts'
            ],
            'health' => [
                'Mental Health Awareness',
                'Nutrition and Wellness Tips',
                'Exercise and Fitness Trends',
                'Medical Technology Advances',
                'Preventive Healthcare Strategies'
            ],
            'sports' => [
                'Training for Marathon Success', 
                'Nutrition for Athletes',
                'Sports Psychology Techniques',
                'Injury Prevention Strategies',
                'Professional Sports Analysis'
            ]
        ];
        return $fallbacks[strtolower($category)] ?? [
            'Current Industry Trends',
            'Expert Analysis and Insights',
            'Future Predictions and Forecasts'
        ];
    }
}
