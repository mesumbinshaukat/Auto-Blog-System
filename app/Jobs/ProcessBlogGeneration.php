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

    public $tries = 1;
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

        } catch (\Throwable $e) {
            $error = $e;
            Log::error("Blog generation job fatal error: " . $e->getMessage());
            $logs[] = "CRITICAL ERROR: " . $e->getMessage();
            
            // Re-throw to ensure Laravel handles it, but our finally block sends the report
            throw $e;
        } finally {
            // Send unified email notification ONLY if it wasn't already sent by the service
            // The service sends report on its own success/failure. 
            // We only send here if $error is set (meaning it crashed BEFORE service could report)
            if ($error) {
                try {
                    \Illuminate\Support\Facades\Mail::to(env('REPORTS_EMAIL', 'mesumbinshaukat@gmail.com'))
                        ->send(new \App\Mail\BlogGenerationReport(null, $error instanceof \Exception ? $error : new \Exception($error->getMessage()), $logs, false));
                } catch (\Exception $mailEx) {
                    Log::error("Failed to send emergency job report: " . $mailEx->getMessage());
                }
            }
        }
    }

    /**
     * Handle a job failure.
     */
    public function failed(\Throwable $exception): void
    {
        Log::error("ProcessBlogGeneration job permanently failed: " . $exception->getMessage());
        
        try {
            \Illuminate\Support\Facades\Mail::to(env('REPORTS_EMAIL', 'mesumbinshaukat@gmail.com'))
                ->send(new \App\Mail\SystemErrorReport(
                    "Job Permanently Failed: ProcessBlogGeneration",
                    "The blog generation job for Category ID {$this->categoryId} has failed after all attempts.\n\nError: " . $exception->getMessage()
                ));
        } catch (\Exception $e) {
            Log::error("Failed to send job failure alert: " . $e->getMessage());
        }
    }
}
