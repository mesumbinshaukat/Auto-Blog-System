<?php

namespace App\Console\Commands;

use App\Models\Blog;
use App\Services\ThumbnailService;
use Illuminate\Console\Command;

class RegenerateThumbnails extends Command
{
    protected $signature = 'thumbnails:regenerate 
                            {--force : Force regeneration even if current thumbnail is unique}
                            {--limit= : Limit number of blogs to process}
                            {--category= : Only regenerate for specific category}';
    
    protected $description = 'Regenerate thumbnails for blogs with uniqueness validation';

    public function handle(ThumbnailService $thumbnailService)
    {
        $this->info('Starting thumbnail regeneration...');
        
        // Build query
        $query = Blog::with('category');
        
        if ($category = $this->option('category')) {
            $query->whereHas('category', function($q) use ($category) {
                $q->where('name', $category);
            });
        }
        
        if ($limit = $this->option('limit')) {
            $query->limit((int)$limit);
        }
        
        $blogs = $query->get();
        $this->info("Found {$blogs->count()} blogs to process");
        
        $regenerated = 0;
        $skipped = 0;
        $failed = 0;
        
        $progressBar = $this->output->createProgressBar($blogs->count());
        $progressBar->start();
        
        foreach ($blogs as $blog) {
            $progressBar->advance();
            
            // Check if current thumbnail is unique (unless force flag is set)
            if (!$this->option('force') && $blog->thumbnail_path) {
                try {
                    if ($thumbnailService->validateUniqueness($blog->thumbnail_path)) {
                        $skipped++;
                        continue;
                    }
                } catch (\Exception $e) {
                    // If validation fails, regenerate anyway
                }
            }
            
            // Regenerate thumbnail
            try {
                $newPath = $thumbnailService->generateThumbnail(
                    $blog->slug,
                    $blog->title,
                    $blog->content,
                    $blog->category->name,
                    $blog->id
                );
                
                if ($newPath) {
                    $blog->update(['thumbnail_path' => $newPath]);
                    $regenerated++;
                } else {
                    $failed++;
                }
            } catch (\Exception $e) {
                $this->error("\nFailed to regenerate for blog #{$blog->id}: " . $e->getMessage());
                $failed++;
            }
        }
        
        $progressBar->finish();
        $this->newLine(2);
        
        // Summary
        $this->info("Regeneration complete!");
        $this->table(
            ['Status', 'Count'],
            [
                ['Regenerated', $regenerated],
                ['Skipped (already unique)', $skipped],
                ['Failed', $failed],
                ['Total', $blogs->count()]
            ]
        );
        
        return Command::SUCCESS;
    }
}
