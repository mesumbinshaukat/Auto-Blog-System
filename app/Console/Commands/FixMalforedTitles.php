<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use App\Models\Blog;
use App\Services\TitleSanitizerService;
use Illuminate\Support\Facades\Log;

class FixMalforedTitles extends Command
{
    /**
     * The name and signature of the console command.
     *
     * @var string
     */
    protected $signature = 'blog:fix-titles';

    /**
     * The console command description.
     *
     * @var string
     */
    protected $description = 'Scan and fix malformed HTML entities in blog titles';

    /**
     * Execute the console command.
     */
    public function handle(TitleSanitizerService $sanitizer)
    {
        $this->info('Starting title scan...');

        // Find blogs with potential entities (containing &)
        $blogs = Blog::where('title', 'LIKE', '%&%')->get();

        $count = $blogs->count();
        $this->info("Found {$count} potential candidates.");

        if ($count === 0) {
            $this->info('No malformed titles found.');
            return;
        }

        $bar = $this->output->createProgressBar($count);
        $bar->start();

        $fixedCount = 0;

        foreach ($blogs as $blog) {
            $originalTitle = $blog->title;
            // fixBlog handles the check and save internally if needed
            $updatedBlog = $sanitizer->fixBlog($blog);

            if ($updatedBlog->title !== $originalTitle) {
                $fixedCount++;
                Log::info("Fixed blog title ID {$blog->id}: '{$originalTitle}' -> '{$updatedBlog->title}'");
            }
            
            $bar->advance();
        }

        $bar->finish();
        $this->newLine();
        $this->info("Scan complete. Fixed {$fixedCount} titles.");
    }
}
