<?php

namespace App\Jobs;

use App\Models\Category;
use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Bus\Dispatchable;
use Illuminate\Queue\InteractsWithQueue;
use Illuminate\Queue\SerializesModels;
use Carbon\Carbon;
use Illuminate\Support\Facades\Log;

class GenerateDailyBlogs implements ShouldQueue
{
    use Dispatchable, InteractsWithQueue, Queueable, SerializesModels;

    public function handle(): void
    {
        Log::info("Scheduling daily blogs...");

        // Requirements: 5 blogs, random times, >= 3.5h gap.
        // Start time: now (e.g. midnight).
        
        $categories = Category::all();
        if ($categories->isEmpty()) {
            Log::warning("No categories found. skipping generation.");
            return;
        }

        $startTime = Carbon::now()->addMinutes(30); // Start shortly after reset
        $scheduledTimes = [];

        for ($i = 0; $i < 5; $i++) {
            // Add minimum 3.5 hours (210 mins) + random buffer (0-60 mins)
            $delayMinutes = ($i * 210) + rand(0, 60);
            $scheduleAt = $startTime->copy()->addMinutes($delayMinutes);
            
            $scheduledTimes[] = $scheduleAt;

            // Pick random category
            $category = $categories->random();

            ProcessBlogGeneration::dispatch($category->id)
                ->delay($scheduleAt);
                
            Log::info("Scheduled blog for {$category->name} at {$scheduleAt}");
        }
    }
}
