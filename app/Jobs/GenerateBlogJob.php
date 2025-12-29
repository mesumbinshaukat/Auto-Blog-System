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
    protected $customPrompt;

    public function __construct($categoryId, $jobId, $customPrompt = null)
    {
        $this->categoryId = $categoryId;
        $this->jobId = $jobId;
        $this->customPrompt = $customPrompt;
    }

    public function handle(BlogGeneratorService $generator)
    {
        $blog = null;
        $error = null;
        $logs = [];
        $category = null;
        
        try {
            Log::channel('daily')->info("JOB START [{$this->jobId}]: Fetching Category {$this->categoryId}");
            $logs[] = "Job started for category ID: {$this->categoryId}";
            
            $category = Category::find($this->categoryId);
            
            if (!$category) {
                Log::error("GenerateBlogJob: Category not found");
                $logs[] = "ERROR: Category ID {$this->categoryId} not found";
                Cache::put("blog_job_{$this->jobId}", [
                    'status' => 'failed',
                    'message' => 'Category not found',
                    'progress' => 0
                ], 300);
                return;
            }

            Log::info("GenerateBlogJob: Starting generation...");
            $logs[] = "Starting blog generation for category: {$category->name}";
            if ($this->customPrompt) {
                $logs[] = "Using Custom Prompt: " . \Illuminate\Support\Str::limit($this->customPrompt, 50);
            }
            
            $blog = $generator->generateBlogForCategory($category, function ($status, $progress) use (&$logs) {
                Log::info("GenerateBlogJob: Progress $progress% - $status");
                $logs[] = "Progress $progress%: $status";
                Cache::put("blog_job_{$this->jobId}", [
                    'status' => 'processing',
                    'message' => $status,
                    'progress' => $progress
                ], 600); 
            }, $this->customPrompt);

            if ($blog) {
                $logs[] = "Blog generated successfully: {$blog->title}";
                Cache::put("blog_job_{$this->jobId}", [
                    'status' => 'completed',
                    'message' => 'Blog generated successfully!',
                    'progress' => 100,
                    'blog_title' => $blog->title
                ], 600);
            } else {
                $logs[] = "Generation returned null (duplicate or error)";
                Cache::put("blog_job_{$this->jobId}", [
                    'status' => 'failed',
                    'message' => 'Generation returned null (duplicate or error)',
                    'progress' => 0
                ], 600);
            }

        } catch (\Throwable $e) {
            $error = $e;
            $errorMessage = $e->getMessage();
            $attempt = $this->attempts();
            
            Log::error("Async Job Failed (Attempt $attempt/{$this->tries}): $errorMessage");
            Log::error("Stack trace: " . $e->getTraceAsString());
            $logs[] = "ERROR (Attempt $attempt): $errorMessage";
            
            Cache::put("blog_job_{$this->jobId}", [
                'status' => 'failed',
                'message' => $errorMessage,
                'progress' => 0,
                'attempt' => $attempt
            ], 600);
            
            // Check for quota errors - delay and retry
            if (str_contains($errorMessage, 'quota') || str_contains($errorMessage, '402') || str_contains($errorMessage, '429')) {
                Log::warning("Quota/Rate limit error detected, will retry with delay");
                $logs[] = "Quota/Rate limit detected, retrying...";
                
                // Re-queue with delay
                $this->release(300); // 5 minutes
                return;
            }
            
            throw $e; // Let Laravel handle retry
        } finally {
            // Send unified email notification for all scenarios
            // Note: BlogGeneratorService already sends email, but job-level email provides additional context
            try {
                \Illuminate\Support\Facades\Mail::to(env('REPORTS_EMAIL', 'mesumbinshaukat@gmail.com'))
                    ->send(new \App\Mail\BlogGenerationReport($blog, $error, $logs, false));
            } catch (\Exception $mailEx) {
                Log::error("Failed to send job email notification: " . $mailEx->getMessage());
            }
        }
    }
}
