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
        try {
            $category = Category::find($this->categoryId);
            if (!$category) {
                Log::error("Category ID {$this->categoryId} not found for blog generation job.");
                return;
            }

            Log::info("Starting blog generation for category: {$category->name}");
            $blog = $generator->generateBlogForCategory($category);
            
            if ($blog) {
                Log::info("Blog generated successfully: {$blog->title}");
            } else {
                Log::warning("Blog generation returned null for category: {$category->name}");
            }

        } catch (\Exception $e) {
            Log::error("Blog generation job failed: " . $e->getMessage());
            
            // Send email notification
            \Illuminate\Support\Facades\Mail::to('mesum@worldoftech.company')
                ->send(new \App\Mail\BlogGenerationFailed($e->getMessage(), $category->name ?? 'Unknown'));
        }
    }
}
