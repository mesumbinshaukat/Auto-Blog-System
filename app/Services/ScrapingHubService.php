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
        $this->baseUrl = env('SCRAPING_HUB_BASE_URL', 'https://scraping-hub-backend-only.vercel.app');
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

        return Cache::remember('scraping_hub_health', 3600, function () {
            try {
                $response = Http::timeout(5)
                    ->withHeaders(['Authorization' => "Bearer {$this->masterKey}"])
                    ->get($this->baseUrl . '/');

                if ($response->successful()) {
                    return true;
                }

                Log::warning("ScrapingHub health check failed: {$response->status()}");
                $this->notifyHealthFailure($response->status(), $response->body());
                return false;
            } catch (\Exception $e) {
                Log::error("ScrapingHub health check exception: {$e->getMessage()}");
                $this->notifyHealthFailure('exception', $e->getMessage());
                return false;
            }
        });
    }

    /**
     * Search for topics/articles
     * 
     * @param string $query Search query
     * @param int $limit Maximum results to return
     * @return array|null Array of results or null on failure
     */
    public function search(string $query, int $limit = 20): ?array
    {
        if (!$this->isAvailable()) {
            return null;
        }

        $cacheKey = 'scraping_hub_search_' . md5($query . $limit);
        
        return Cache::remember($cacheKey, 3600, function () use ($query, $limit) {
            $response = $this->callApiWithRetry('/api/search', [
                'q' => $query,
                'limit' => $limit
            ]);

            if (!$response) {
                return null;
            }

            $data = $response->json();
            
            // Handle different possible response formats
            if (isset($data['results']) && is_array($data['results'])) {
                return $data['results'];
            }
            
            if (is_array($data) && isset($data[0])) {
                return $data;
            }

            Log::warning("ScrapingHub search returned unexpected format", ['data' => $data]);
            return null;
        });
    }

    /**
     * Scrape content from a URL
     * 
     * @param string $url URL to scrape
     * @return array|null Array with 'content', 'title', 'snippet' or null on failure
     */
    public function scrape(string $url): ?array
    {
        if (!$this->isAvailable()) {
            return null;
        }

        // Validate URL
        if (!filter_var($url, FILTER_VALIDATE_URL)) {
            Log::warning("ScrapingHub: Invalid URL provided", ['url' => $url]);
            return null;
        }

        $cacheKey = 'scraping_hub_scrape_' . md5($url);
        
        return Cache::remember($cacheKey, 3600, function () use ($url) {
            $response = $this->callApiWithRetry('/api/scrape', ['url' => $url]);

            if (!$response) {
                return null;
            }

            $data = $response->json();
            
            // Ensure we have at least content or snippet
            if (empty($data['content']) && empty($data['snippet'])) {
                Log::warning("ScrapingHub scrape returned no content", ['url' => $url]);
                return null;
            }

            // Truncate content to reasonable size (2000 chars)
            if (isset($data['content']) && strlen($data['content']) > 2000) {
                $data['content'] = substr($data['content'], 0, 2000);
            }

            // Truncate snippet to 1000 chars
            if (isset($data['snippet']) && strlen($data['snippet']) > 1000) {
                $data['snippet'] = substr($data['snippet'], 0, 1000);
            }

            return [
                'content' => $data['content'] ?? '',
                'title' => $data['title'] ?? '',
                'snippet' => $data['snippet'] ?? ''
            ];
        });
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
            $response = Http::timeout(10)
                ->withHeaders(['Authorization' => "Bearer {$this->masterKey}"])
                ->get($this->baseUrl . $endpoint, $params);

            if ($response->successful()) {
                return $response;
            }

            // Handle rate limiting with retry
            if ($response->status() === 429 && $attempt < $maxAttempts) {
                $waitTime = pow(2, $attempt); // Exponential backoff: 2, 4, 8 seconds
                Log::info("ScrapingHub rate limited, retrying in {$waitTime}s (attempt {$attempt}/{$maxAttempts})");
                sleep($waitTime);
                return $this->callApiWithRetry($endpoint, $params, $attempt + 1);
            }

            Log::warning("ScrapingHub {$endpoint} failed: {$response->status()} - {$response->body()}");
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
     * Send email notification when health check fails
     */
    protected function notifyHealthFailure($status, $message): void
    {
        // Only send email once per hour to avoid spam
        $notificationKey = 'scraping_hub_health_notification_sent';
        
        if (Cache::has($notificationKey)) {
            return;
        }

        Cache::put($notificationKey, true, 3600);

        try {
            $email = env('REPORTS_EMAIL', 'admin@example.com');
            
            Mail::raw(
                "Scraping Hub API Health Check Failed\n\n" .
                "Status: {$status}\n" .
                "Message: {$message}\n\n" .
                "The system has automatically fallen back to RSS/Guzzle/Crawler methods.\n" .
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
