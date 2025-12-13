<?php

namespace App\Services;

use GuzzleHttp\Client;
use Symfony\Component\DomCrawler\Crawler;
use Illuminate\Support\Facades\Log;

class ScrapingService
{
    protected $client;

    public function __construct()
    {
        $this->client = new Client([
            'timeout'  => 10.0,
            'verify' => false // For development/local issues, though strictly should be true
        ]);
    }

    public function fetchTrendingTopics(string $category): array
    {
        // Fallback topics in case scraping fails
        $fallbacks = [
            'technology' => ['Future of AI in 2025', 'Best Programming Languages', 'Web Assembly Guide'],
            'business' => ['Remote Work Trends', 'Startup Funding Guide', 'Leadership Skills'],
            'ai' => ['Generative AI Explanation', 'LLM Fine-tuning', 'Ethical AI'],
            'games' => ['Top RPGs of the Year', 'Indie Game Development', 'Esports Growth'],
            'politics' => ['Global Climate Policies', 'Digital Privacy Laws'],
            'sports' => ['Training for Marathon', 'Nutrition for Athletes']
        ];

        try {
            // Real implementation would try to parse Google Trends RSS or similar
            // For this durable implementation, we simulated a mix or returns static for stability
            // Real RSS: https://trends.google.com/trends/trendingsearches/daily/rss?geo=US
            
            $url = "https://trends.google.com/trends/trendingsearches/daily/rss?geo=US";
            $response = $this->client->get($url);
            $content = $response->getBody()->getContents();
            
            $crawler = new Crawler($content);
            $topics = $crawler->filter('item > title')->each(function (Crawler $node) {
                return $node->text();
            });

            return !empty($topics) ? array_slice($topics, 0, 10) : ($fallbacks[$category] ?? []);

        } catch (\Exception $e) {
            Log::error("Scraping trends failed: " . $e->getMessage());
            return $fallbacks[$category] ?? ['General Tech Trends'];
        }
    }

    public function scrapeContent(string $url): string
    {
        try {
            $response = $this->client->get($url);
            $html = $response->getBody()->getContents();
            $crawler = new Crawler($html);

            // Extract core content (h1-h3 and p tags, avoiding nav/footer)
            $text = $crawler->filter('body h1, body h2, body h3, body p')->each(function (Crawler $node) {
                return trim($node->text());
            });

            return implode("\n\n", array_slice($text, 0, 20)); // Limit to first 20 paragraphs

        } catch (\Exception $e) {
            Log::error("Scraping URL $url failed: " . $e->getMessage());
            return "";
        }
    }
}
