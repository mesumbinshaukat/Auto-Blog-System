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

    public $timeout = 600; // 10 minutes

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
            Log::error("Async Job Failed: " . $e->getMessage());
            Cache::put("blog_job_{$this->jobId}", [
                'status' => 'failed',
                'message' => $e->getMessage(),
                'progress' => 0
            ], 600);
            throw $e;
        }
    }
}
