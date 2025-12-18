<?php

namespace App\Console\Commands;

use App\Services\ScrapingHubService;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Cache;

class ScrapingHubTestCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'blog:scrapinghub-test';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Test all Scraping Hub API endpoints with diagnostic output';

    /**
     * Execute the console command.
     */
    public function handle(ScrapingHubService $scrapingHub)
    {
        $this->info('Starting Scraping Hub API Test Suite...');

        // 0. Check for disabled status
        if (Cache::has('scraping_hub_disabled')) {
            $this->warn('⚠️ Scraping Hub API is currently TEMPORARILY DISABLED due to previous auth/quota issues.');
            if ($this->confirm('Do you want to clear the disabled status for this test?', false)) {
                Cache::forget('scraping_hub_disabled');
                $this->info('✅ Disabled status cleared.');
            }
        }

        // 1. Health
        $this->info("\n--- 1. Health Check ---");
        if ($scrapingHub->isAvailable()) {
            $this->info('✅ Healthy');
        } else {
            $this->error('❌ Unhealthy or Disabled');
        }

        // 2. Resources
        $this->info("\n--- 2. Resources API ---");
        $resources = $scrapingHub->resources();
        if ($resources) {
            $this->info('✅ Found ' . ($resources['total_healthy'] ?? 'unknown') . ' healthy resources');
        } else {
            $this->warn('⚠️ Resources failed or returned no data');
        }

        // 3. Stats
        $this->info("\n--- 3. Stats API ---");
        $stats = $scrapingHub->stats('daily');
        if ($stats) {
            $this->info('✅ Stats retrieved');
            $this->line("- Total Requests: " . ($stats['data']['total_requests'] ?? 0));
        } else {
            $this->warn('⚠️ Stats failed');
        }

        // 4. Sitemap
        $this->info("\n--- 4. Sitemap API ---");
        $this->info('Parsing https://blogs.worldoftech.company/sitemap.xml...');
        $sitemap = $scrapingHub->sitemap('https://blogs.worldoftech.company/sitemap.xml');
        if ($sitemap) {
            $this->info('✅ Found ' . count($sitemap) . ' URLs');
        } else {
            $this->warn('⚠️ Sitemap failed');
        }

        // 5. Search
        $this->info("\n--- 5. Search API (Links) ---");
        $this->info('Searching for "technology" (this may take a minute)...');
        $links = $scrapingHub->search('technology', 5);
        $this->displayResults($links, 'Search');

        // 6. News
        $this->info("\n--- 6. News API (Topics) ---");
        $this->info('Searching for "tech" (this may take a minute)...');
        $topics = $scrapingHub->news('tech', 5);
        $this->displayResults($topics, 'News');

        // 7. Blog
        $this->info("\n--- 7. Blog API ---");
        $this->info('Searching for "science" (this may take a minute)...');
        $blogs = $scrapingHub->blog('science', 5);
        $this->displayResults($blogs, 'Blog');

        // 8. Scrape
        $this->info("\n--- 8. Scrape API ---");
        $url = 'https://example.com';
        $this->info("Scraping $url...");
        $scraped = $scrapingHub->scrape($url);
        if ($scraped) {
            $this->info('✅ Successfully scraped: ' . ($scraped['title'] ?? 'No title'));
            $this->line("Content Length: " . strlen($scraped['content']));
        } else {
            $this->warn('⚠️ Scrape failed');
        }

        // 9. RSS
        $this->info("\n--- 9. RSS API ---");
        $this->info('Parsing https://news.google.com/rss...');
        $rss = $scrapingHub->rss('https://news.google.com/rss');
        if ($rss) {
            $this->info('✅ Found ' . count($rss) . ' RSS items');
            if (!empty($rss)) {
                $this->line("- First Item: " . $rss[0]['title']);
            }
        } else {
            $this->warn('⚠️ RSS failed or returned no items');
        }

        $this->info("\n--- Test Suite Complete ---");
        return Command::SUCCESS;
    }

    protected function displayResults(?array $data, string $label): void
    {
        if ($data === null) {
            $this->error("❌ $label API failed (Null)");
        } elseif (empty($data)) {
            $this->warn("⚠️ $label API returned empty array (No results)");
        } else {
            $this->info("✅ Found " . count($data) . " $label results");
            foreach (array_slice($data, 0, 2) as $item) {
                $this->line("- " . ($item['title'] ?? $item['url'] ?? 'No title'));
            }
        }
    }
}
