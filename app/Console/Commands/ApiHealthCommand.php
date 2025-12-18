<?php

namespace App\Console\Commands;

use App\Services\ScrapingHubService;
use Illuminate\Console\Command;

class ApiHealthCommand extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'blog:api-health';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Check Scraping Hub API Health';

    /**
     * Execute the console command.
     */
    public function handle(ScrapingHubService $scrapingHub)
    {
        $this->info('Checking Scraping Hub API health...');
        
        if ($scrapingHub->isAvailable()) {
            $this->info('✅ Scraping Hub API is healthy!');
            
            // Resources
            $resources = $scrapingHub->resources();
            if ($resources) {
                $this->line("- Resources: " . ($resources['total_healthy'] ?? 0) . " healthy nodes");
            }
            
            // Stats
            $stats = $scrapingHub->stats('daily');
            if ($stats && isset($stats['data'])) {
                $this->line("- Daily Stats: " . ($stats['data']['total_requests'] ?? 0) . " requests processed today");
            }

            return Command::SUCCESS;
        } else {
            $this->error('❌ Scraping Hub API is down or not configured.');
            return Command::FAILURE;
        }
    }
}
