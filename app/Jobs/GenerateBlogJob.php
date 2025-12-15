<?php

namespace App\Jobs;

use App\Models\Category;
use App\Services\BlogGeneratorService;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class GenerateBlogJob implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public $timeout = 900; // 15 minutes
    public $tries = 5;
    public $backoff = [60, 300, 600]; // Retry delays: 1min, 5min, 10min

    protected $categoryId;
    protected $jobId;

    public function __construct($categoryId, $jobId)
    {
        $this->categoryId = $categoryId;
        $this->jobId = $jobId;
    }

    public function handle(BlogGeneratorService $generator)
    {
        try {
            Log::channel('daily')->info("JOB START [{$this->jobId}]: Fetching Category {$this->categoryId}");
            $category = Category::find($this->categoryId);
            
            if (!$category) {
                Log::error("GenerateBlogJob: Category not found");
                Cache::put("blog_job_{$this->jobId}", [
                    'status' => 'failed',
                    'message' => 'Category not found',
                    'progress' => 0
                ], 300);
                return;
            }

            Log::info("GenerateBlogJob: Starting generation...");
            $blog = $generator->generateBlogForCategory($category, function ($status, $progress) {
                Log::info("GenerateBlogJob: Progress $progress% - $status");
                Cache::put("blog_job_{$this->jobId}", [
                    'status' => 'processing',
                    'message' => $status,
                    'progress' => $progress
                ], 600); 
            });

            if ($blog) {
                Cache::put("blog_job_{$this->jobId}", [
                    'status' => 'completed',
                    'message' => 'Blog generated successfully!',
                    'progress' => 100,
                    'blog_title' => $blog->title
                ], 600);
            } else {
                Cache::put("blog_job_{$this->jobId}", [
                    'status' => 'failed',
                    'message' => 'Generation returned null (duplicate or error)',
                    'progress' => 0
                ], 600);
            }

        } catch (\Exception $e) {
            $errorMessage = $e->getMessage();
            $attempt = $this->attempts();
            
            Log::error("Async Job Failed (Attempt $attempt/{$this->tries}): $errorMessage");
            Log::error("Stack trace: " . $e->getTraceAsString());
            
            Cache::put("blog_job_{$this->jobId}", [
                'status' => 'failed',
                'message' => $errorMessage,
                'progress' => 0,
                'attempt' => $attempt
            ], 600);
            
            // Check for quota errors - delay and retry
            if (str_contains($errorMessage, 'quota') || str_contains($errorMessage, '402') || str_contains($errorMessage, '429')) {
                Log::warning("Quota/Rate limit error detected, will retry with delay");
                
                // Send email notification if this is attempt 3 or higher
                if ($attempt >= 3) {
                    try {
                        \Mail::to(env('REPORTS_EMAIL', 'admin@example.com'))->send(
                            new \App\Mail\QuotaExceededMail("Blog generation quota exceeded (Attempt $attempt)")
                        );
                    } catch (\Exception $mailEx) {
                        Log::error("Failed to send quota email: " . $mailEx->getMessage());
                    }
                }
                
                // Re-queue with delay
                $this->release(300); // 5 minutes
                return;
            }
            
            // Send critical error email if we've tried 3+ times
            if ($attempt >= 3) {
                try {
                    \Mail::to(env('REPORTS_EMAIL', 'admin@example.com'))->send(
                        new \App\Mail\CriticalErrorMail("Blog generation failed", $errorMessage, $e->getTraceAsString())
                    );
                } catch (\Exception $mailEx) {
                    Log::error("Failed to send critical error email: " . $mailEx->getMessage());
                }
            }
            
            throw $e; // Let Laravel handle retry
        }
    }
}
