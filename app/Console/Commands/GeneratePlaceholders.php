<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Services\ThumbnailService;

class GeneratePlaceholders extends Command
{
    protected $signature = 'thumbnails:generate-placeholders';
    protected $description = 'Generate category placeholder thumbnails (SVG)';

    public function handle(ThumbnailService $thumbnailService)
    {
        $this->info('Generating category placeholder thumbnails...');
        
        try {
            $thumbnailService->generateCategoryPlaceholders();
            $this->info('✅ Category placeholders generated successfully!');
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error("❌ Failed to generate placeholders: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
