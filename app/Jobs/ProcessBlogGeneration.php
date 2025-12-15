<?php

namespace App\Jobs;

use App\Services\BlogGeneratorService;
use App\Models\Category;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Log;

class ProcessBlogGeneration implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    protected $categoryId;

    public function __construct(int $categoryId)
    {
        $this->categoryId = $categoryId;
    }

    public function handle(BlogGeneratorService $generator): void
    {
        $blog = null;
        $error = null;
        $logs = [];
        $category = null;
        
        try {
            $category = Category::find($this->categoryId);
            if (!$category) {
                Log::error("Category ID {$this->categoryId} not found for blog generation job.");
                $logs[] = "ERROR: Category ID {$this->categoryId} not found";
                return;
            }

            Log::info("Starting blog generation for category: {$category->name}");
            $logs[] = "Starting blog generation for category: {$category->name}";
            
            $blog = $generator->generateBlogForCategory($category);
            
            if ($blog) {
                Log::info("Blog generated successfully: {$blog->title}");
                $logs[] = "Blog generated successfully: {$blog->title}";
            } else {
                Log::warning("Blog generation returned null for category: {$category->name}");
                $logs[] = "WARNING: Blog generation returned null (likely duplicate)";
            }

        } catch (\Exception $e) {
            $error = $e;
            Log::error("Blog generation job failed: " . $e->getMessage());
            $logs[] = "ERROR: " . $e->getMessage();
        } finally {
            // Send unified email notification
            try {
                \Illuminate\Support\Facades\Mail::to(env('REPORTS_EMAIL', 'mesumbinshaukat@gmail.com'))
                    ->send(new \App\Mail\BlogGenerationReport($blog, $error, $logs, false));
            } catch (\Exception $mailEx) {
                Log::error("Failed to send blog generation email: " . $mailEx->getMessage());
            }
        }
    }
}
