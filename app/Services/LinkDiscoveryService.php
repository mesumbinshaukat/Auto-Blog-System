<?php

namespace App\Services;

use GuzzleHttp\Client;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Symfony\Component\DomCrawler\Crawler;

class LinkDiscoveryService
{
    protected $serperKey;
    protected $client;

    public function __construct()
    {
        $this->serperKey = env('SERPER_API_KEY');
        $this->client = new Client([
            'timeout' => 5,
            'verify' => false,
            'headers' => [
                'User-Agent' => 'Mozilla/5.0 (Windows NT 10.0; Win64; x64) AppleWebKit/537.36'
            ]
        ]);
    }

    /**
     * Discover external links for a topic
     * 
     * @param string $topic
     * @param string $category
     * @return array URLs
     */
    public function discoverLinks(string $topic, string $category): array
    {
        $cacheKey = 'link_discovery_' . md5($topic . $category);
        
        // Check 24h cache
        if (Cache::has($cacheKey)) {
            Log::info("Using cached link discovery results for: $topic");
            return Cache::get($cacheKey);
        }

        $query = "$topic $category related articles 2025";
        $urls = [];

        // Try Serper API first
        if (!empty($this->serperKey)) {
            $urls = $this->searchSerper($query);
        }

        // Fallback to DuckDuckGo
        if (empty($urls)) {
            Log::info("Falling back to DuckDuckGo for link discovery");
            $urls = $this->searchDuckDuckGo($query);
        }

        // Cache for 24 hours
        if (!empty($urls)) {
            Cache::put($cacheKey, $urls, now()->addHours(24));
        }

        return $urls;
    }

    /**
     * Search using Serper API
     * 
     * @param string $query
     * @return array URLs
     */
    protected function searchSerper(string $query): array
    {
        try {
            Log::info("Searching Serper API: $query");
            
            $response = Http::withHeaders([
                'X-API-KEY' => $this->serperKey,
                'Content-Type' => 'application/json'
            ])->post('https://google.serper.dev/search', [
                'q' => $query,
                'num' => 10
            ]);

            if ($response->successful()) {
                $data = $response->json();
                $urls = [];

                foreach ($data['organic'] ?? [] as $result) {
                    if (isset($result['link'])) {
                        $urls[] = $result['link'];
                    }
                }

                Log::info("Serper API returned " . count($urls) . " URLs");
                return array_slice($urls, 0, 10);
            }

            Log::warning("Serper API failed: " . $response->status());
        } catch (\Exception $e) {
            Log::error("Serper API exception: " . $e->getMessage());
        }

        return [];
    }

    /**
     * Search using DuckDuckGo scraping (legal alternative)
     * 
     * @param string $query
     * @return array URLs
     */
    protected function searchDuckDuckGo(string $query): array
    {
        try {
            Log::info("Scraping DuckDuckGo: $query");
            
            $url = 'https://html.duckduckgo.com/html/?q=' . urlencode($query);
            $response = $this->client->get($url);
            $html = $response->getBody()->getContents();

            $crawler = new Crawler($html);
            $urls = [];

            // Extract result links
            $crawler->filter('.result__a')->each(function (Crawler $node) use (&$urls) {
                $href = $node->attr('href');
                
                // DuckDuckGo uses redirect URLs, extract actual URL
                if (preg_match('/uddg=([^&]+)/', $href, $matches)) {
                    $actualUrl = urldecode($matches[1]);
                    if (filter_var($actualUrl, FILTER_VALIDATE_URL)) {
                        $urls[] = $actualUrl;
                    }
                }
            });

            Log::info("DuckDuckGo returned " . count($urls) . " URLs");
            return array_slice($urls, 0, 10);

        } catch (\Exception $e) {
            Log::error("DuckDuckGo scraping failed: " . $e->getMessage());
        }

        return [];
    }

    /**
     * Extract snippet from URL
     * 
     * @param string $url
     * @return string|null
     */
    public function extractSnippet(string $url): ?string
    {
        try {
            $response = $this->client->get($url);
            $html = $response->getBody()->getContents();

            $crawler = new Crawler($html);
            
            // Try to find article content
            $selectors = ['article', '.article-body', '.content', 'main', 'p'];
            $text = '';

            foreach ($selectors as $selector) {
                try {
                    $crawler->filter($selector)->each(function (Crawler $node) use (&$text) {
                        if (empty($text)) {
                            $text = $node->text();
                        }
                    });
                    
                    if (!empty($text)) {
                        break;
                    }
                } catch (\Exception $e) {
                    continue;
                }
            }

            // Return first 500 chars
            return substr(trim($text), 0, 500);

        } catch (\Exception $e) {
            Log::warning("Failed to extract snippet from $url: " . $e->getMessage());
            return null;
        }
    }

    /**
     * Get title from URL
     * 
     * @param string $url
     * @return string|null
     */
    public function extractTitle(string $url): ?string
    {
        try {
            $response = $this->client->get($url);
            $html = $response->getBody()->getContents();

            $crawler = new Crawler($html);
            $title = $crawler->filter('title')->text();

            return trim($title);

        } catch (\Exception $e) {
            return null;
        }
    }
}
