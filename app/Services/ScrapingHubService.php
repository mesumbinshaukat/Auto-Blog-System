<?php

namespace App\Services;

use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Mail;

class ScrapingHubService
{
    protected $baseUrl;
    protected $masterKey;
    protected $isConfigured;

    public function __construct()
    {
        $this->baseUrl = rtrim(env('SCRAPING_HUB_BASE_URL', 'https://scraping-hub-backend-only.vercel.app'), '/') . '/api';
        $this->masterKey = env('MASTER_KEY');
        $this->isConfigured = !empty($this->masterKey);
    }

    /**
     * Check if Scraping Hub API is available and healthy
     * Results are cached for 1 hour to avoid excessive health checks
     */
    public function isAvailable(): bool
    {
        if (!$this->isConfigured) {
            return false;
        }

        // Check if API is temporarily disabled due to auth/quota issues
        if (Cache::has('scraping_hub_disabled')) {
            return false;
        }

        return Cache::remember('scraping_hub_health', 300, function () {
            try {
                $response = Http::timeout(30)
                    ->withoutVerifying()
                    ->withHeaders(['Authorization' => "Bearer {$this->masterKey}"])
                    ->get($this->baseUrl . '/health');

                if ($response->successful()) {
                    return true;
                }

                $status = $response->status();
                Log::warning("ScrapingHub health check failed: {$status}");
                
                // If it's an auth or quota issue, disable for 3600s
                if (in_array($status, [401, 402, 403])) {
                    $this->disableApi($status);
                }

                $this->notifyHealthFailure($status, $response->body());
                return false;
            } catch (\Exception $e) {
                Log::error("ScrapingHub health check exception: {$e->getMessage()}");
                $this->notifyHealthFailure('exception', $e->getMessage());
                return false;
            }
        });
    }

    /**
     * Disable API temporarily due to authentication or quota issues
     */
    protected function disableApi(int $status): void
    {
        Cache::put('scraping_hub_disabled', true, 3600);
        
        $reason = match($status) {
            401 => 'Unauthorized (Invalid MASTER_KEY)',
            402 => 'Payment Required / Quota Exceeded',
            403 => 'Forbidden (Invalid token)',
            default => 'Authentication/Quota issue'
        };

        Log::error("ScrapingHub API disabled for 1 hour due to: {$reason}");

        $email = env('REPORTS_EMAIL', 'admin@example.com');
        try {
            Mail::raw(
                "ScrapingHub API has been temporarily disabled for 1 hour.\n\n" .
                "Reason: {$reason} (Status: {$status})\n" .
                "The system will automatically fallback to existing methods.\n\n" .
                "Please verify your MASTER_KEY and quota status.",
                function ($message) use ($email) {
                    $message->to($email)
                        ->subject('ScrapingHub API Auth/Quota Issue - API Disabled');
                }
            );
        } catch (\Exception $e) {
            Log::error("Failed to send ScrapingHub disable notification: {$e->getMessage()}");
        }
    }

    /**
     * Search for news (trending/topics)
     */
    public function news(string $query, int $limit = 20): ?array
    {
        if (!$this->isAvailable()) {
            return null;
        }

        $cacheKey = 'scraping_hub_news_' . md5($query . $limit);
        
        return Cache::remember($cacheKey, 3600, function () use ($query, $limit) {
            $response = $this->callApiWithRetry('/news', [
                'query' => $query
            ]);

            if (!$response) return null;

            $data = $response->json();
            
            // Explicitly prioritize 'data' key per verified terminal response
            $results = $data['data'] ?? $data['results'] ?? (is_array($data) && !isset($data['success']) ? $data : []);
            
            if (empty($results) && isset($data['message'])) {
                Log::info("ScrapingHub /news: {$data['message']}", ['query' => $query, 'response' => $data]);
            }

            return $this->normalizeSearchResults($results);
        });
    }

    /**
     * Search for general content (links)
     */
    public function search(string $query, int $limit = 10): ?array
    {
        if (!$this->isAvailable()) {
            return null;
        }

        $cacheKey = 'scraping_hub_search_' . md5($query . $limit);
        
        return Cache::remember($cacheKey, 3600, function () use ($query, $limit) {
            $response = $this->callApiWithRetry('/search', [
                'query' => $query
            ]);

            if (!$response) return null;

            $data = $response->json();
            
            $results = $data['data'] ?? $data['results'] ?? (is_array($data) && !isset($data['success']) ? $data : []);

            if (empty($results) && isset($data['message'])) {
                Log::info("ScrapingHub /search: {$data['message']}", ['query' => $query, 'response' => $data]);
            }

            return $this->normalizeSearchResults($results);
        });
    }

    /**
     * Parse RSS feeds
     */
    public function rss(string $url): ?array
    {
        if (!$this->isAvailable()) {
            return null;
        }

        $cacheKey = 'scraping_hub_rss_' . md5($url);
        
        return Cache::remember($cacheKey, 3600, function () use ($url) {
            $response = $this->callApiWithRetry('/rss', ['url' => $url]);

            if (!$response) return null;

            $data = $response->json();
            // RSS returns {success: true, data: { items: [] }}
            $items = $data['data']['items'] ?? $data['items'] ?? [];

            if (empty($items)) {
                return null;
            }

            // Normalize RSS items
            return array_map(function($item) {
                return [
                    'title' => $item['title'] ?? '',
                    'url' => $item['link'] ?? $item['url'] ?? '',
                    'snippet' => $item['contentSnippet'] ?? $item['snippet'] ?? $item['description'] ?? '',
                    'pubDate' => $item['pubDate'] ?? ''
                ];
            }, $items);
        });
    }

    /**
     * Validate URL
     */
    public function validate(string $url): bool
    {
        if (!$this->isAvailable()) {
            return false;
        }

        $response = $this->callApiWithRetry('/validate', ['url' => $url]);

        if (!$response) return false;

        $data = $response->json();
        return isset($data['data']['valid']) && $data['data']['valid'];
    }

    /**
     * Scrape content from a URL
     */
    public function scrape(string $url): ?array
    {
        if (!$this->isAvailable()) {
            return null;
        }

        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            Log::warning("ScrapingHub: Invalid URL provided", ['url' => $url]);
            return null;
        }

        $cacheKey = 'scraping_hub_scrape_' . md5($url);
        
        return Cache::remember($cacheKey, 3600, function () use ($url) {
            $response = $this->callApiWithRetry('/scrape', ['url' => $url]);

            if (!$response) return null;

            $data = $response->json();
            
            // Check for data wrapper (updated fields)
            $item = $data['data'] ?? $data;
            
            if (is_array($item)) {
                $content = $item['mainContent'] ?? $item['content'] ?? '';
                $snippet = $item['description'] ?? $item['snippet'] ?? '';

                if (!empty($content) || !empty($snippet)) {
                    return [
                        'content' => substr($content, 0, 2000),
                        'title' => $item['title'] ?? '',
                        'snippet' => substr($snippet, 0, 2000),
                        'links' => $item['links'] ?? [],
                        'image' => $item['image'] ?? null
                    ];
                }
            }

            Log::warning("ScrapingHub /scrape returned no usable content", ['response' => substr(json_encode($data), 0, 500)]);
            return null;
        });
    }

    /**
     * Search for blogs (Trending/Links)
     */
    public function blog(string $query, int $limit = 10): ?array
    {
        if (!$this->isAvailable()) {
            return null;
        }

        $cacheKey = 'scraping_hub_blog_' . md5($query . $limit);
        
        return Cache::remember($cacheKey, 3600, function () use ($query, $limit) {
            $response = $this->callApiWithRetry('/blog', [
                'query' => $query
            ]);

            if (!$response) return null;

            $data = $response->json();
            
            $results = $data['data'] ?? $data['results'] ?? (is_array($data) && !isset($data['success']) ? $data : []);

            if (empty($results) && isset($data['message'])) {
                Log::info("ScrapingHub /blog: {$data['message']}", ['query' => $query, 'response' => $data]);
            }

            return $this->normalizeSearchResults($results);
        });
    }

    /**
     * Normalize search/news/blog results to consistent format
     */
    protected function normalizeSearchResults(array $results): array
    {
        return array_map(function($item) {
            return [
                'url' => $item['url'] ?? $item['link'] ?? '',
                'title' => $item['title'] ?? '',
                'snippet' => $item['snippet'] ?? $item['description'] ?? $item['contentSnippet'] ?? '',
                'source' => $item['source'] ?? ''
            ];
        }, $results);
    }

    /**
     * Call API with retry logic for rate limits
     * 
     * @param string $endpoint API endpoint
     * @param array $params Query parameters
     * @param int $attempt Current attempt number
     * @return \Illuminate\Http\Client\Response|null
     */
    protected function callApiWithRetry(string $endpoint, array $params = [], int $attempt = 1): ?\Illuminate\Http\Client\Response
    {
        $maxAttempts = 3;

        try {
            $response = Http::timeout(180) // 3 minutes for slow scraping/search tasks
                ->withoutVerifying()
                ->withHeaders(['Authorization' => "Bearer {$this->masterKey}"])
                ->get($this->baseUrl . $endpoint, $params);

            if ($response->successful()) {
                return $response;
            }

            $status = $response->status();

            // Handle auth/quota issues
            if (in_array($status, [401, 402, 403])) {
                $this->disableApi($status);
                return null;
            }

            // Handle rate limiting with retry
            if ($status === 429 && $attempt < $maxAttempts) {
                $waitTime = pow(2, $attempt);
                Log::info("ScrapingHub rate limited, retrying in {$waitTime}s (attempt {$attempt}/{$maxAttempts})");
                sleep($waitTime);
                return $this->callApiWithRetry($endpoint, $params, $attempt + 1);
            }

            Log::warning("ScrapingHub {$endpoint} failed: {$status} - {$response->body()}");
            return null;

        } catch (\Exception $e) {
            if ($attempt < $maxAttempts) {
                $waitTime = pow(2, $attempt);
                Log::info("ScrapingHub exception, retrying in {$waitTime}s: {$e->getMessage()}");
                sleep($waitTime);
                return $this->callApiWithRetry($endpoint, $params, $attempt + 1);
            }

            Log::error("ScrapingHub {$endpoint} exception after {$maxAttempts} attempts: {$e->getMessage()}");
            return null;
        }
    }

    /**
     * Get sitemap URLs
     */
    public function sitemap(string $url): ?array
    {
        if (!$this->isAvailable()) {
            return null;
        }

        $cacheKey = 'scraping_hub_sitemap_' . md5($url);
        
        return Cache::remember($cacheKey, 3600, function () use ($url) {
            $response = $this->callApiWithRetry('/sitemap', ['url' => $url]);

            if (!$response) return null;

            $data = $response->json();
            
            // Per user hit: { urls: [ { loc: ... }, ... ] }
            if (isset($data['urls']) && is_array($data['urls'])) {
                return array_map(function($item) {
                    return is_string($item) ? $item : ($item['loc'] ?? '');
                }, $data['urls']);
            }

            return null;
        });
    }

    /**
     * Get available resources
     */
    public function resources(): ?array
    {
        if (!$this->isAvailable()) {
            return null;
        }

        return Cache::remember('scraping_hub_resources', 3600, function () {
            $response = $this->callApiWithRetry('/resources');
            return $response ? $response->json() : null;
        });
    }

    /**
     * Get usage stats
     */
    public function stats(string $period = 'daily'): ?array
    {
        if (!$this->isAvailable()) {
            return null;
        }

        return Cache::remember("scraping_hub_stats_$period", 3600, function () use ($period) {
            $response = $this->callApiWithRetry("/stats/$period");
            return $response ? $response->json() : null;
        });
    }

    /**
     * Send email notification when health check fails
     */
    protected function notifyHealthFailure($status, $message): void
    {
        $notificationKey = 'scraping_hub_health_notification_sent';
        if (Cache::has($notificationKey)) return;

        Cache::put($notificationKey, true, 3600);

        try {
            $email = env('REPORTS_EMAIL', 'admin@example.com');
            Mail::raw(
                "ScrapingHub down: {$status}/{$message}\n\n" .
                "The system has automatically fallen back to existing methods.\n" .
                "Please check the API status and MASTER_KEY configuration.",
                function ($message) use ($email) {
                    $message->to($email)
                        ->subject('Scraping Hub API Down - Fallback Active');
                }
            );
        } catch (\Exception $e) {
            Log::error("Failed to send ScrapingHub health notification email: {$e->getMessage()}");
        }
    }
}
