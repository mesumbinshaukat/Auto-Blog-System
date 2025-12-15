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
            'https://www.wired.com/tag/artificial-intelligence/feed/rss',
            'https://www.sciencedaily.com/rss/computers_math/artificial_intelligence.xml'
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
                $xmlContent = $response->getBody()->getContents();
                
                // Parse RSS
                $rss = simplexml_load_string($xmlContent, 'SimpleXMLElement', LIBXML_NOCDATA);
                
                if ($rss === false) continue;

                $items = $rss->channel->item ?? $rss->entry ?? []; // Handle RSS 2.0 and Atom
                
                foreach ($items as $item) {
                    $title = (string)$item->title;
                    $pubDate = strtotime((string)($item->pubDate ?? $item->updated ?? 'now'));
                    
                    // Filter: Only recent (last 48 hours)
                    if (time() - $pubDate < 172800) { 
                        $topics[] = trim($title);
                    }

                    if (count($topics) >= 5) break; // Limit per source
                }

            } catch (\Exception $e) {
                Log::warning("RSS Fetch failed for $url: " . $e->getMessage());
            }

            if (count($topics) >= 5) break; // Stop if we have enough
        }

        // De-duplicate
        $topics = array_unique($topics);

        // Fallback to Google Trends / Static if empty
        if (empty($topics)) {
            Log::info("RSS returned no topics for $category, using fallback.");
            return $this->fetchFallbackTopics($category);
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
            }
        } catch (\Exception $e) {
            Log::warning("Wikipedia research failed: " . $e->getMessage());
        }

        // 2. Add more sources here in future (e.g. Bing Search API if key available)

        return !empty($researchData) 
            ? "Research findings:\n" . implode("\n\n", $researchData)
            : "No external research available. Please generate content based on general knowledge about this topic.";
    }

    protected function fetchFallbackTopics(string $category): array
    {
        $fallbacks = [
            'technology' => ['Future of AI in 2025', 'Best Programming Languages', 'Web Assembly Guide'],
            'business' => ['Remote Work Trends', 'Startup Funding Guide', 'Leadership Skills'],
            'ai' => ['Generative AI Explanation', 'LLM Fine-tuning', 'Ethical AI'],
            'games' => ['Top RPGs of the Year', 'Indie Game Development', 'Esports Growth'],
            'politics' => ['Global Climate Policies', 'Digital Privacy Laws'],
            'sports' => ['Training for Marathon', 'Nutrition for Athletes']
        ];
        return $fallbacks[strtolower($category)] ?? ['General Trends'];
    }
}
