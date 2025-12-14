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
            // Add minimum 3.5 hours (210 mins) + random buffer (0-120 mins) for organic spread
            // 3.5h, 7h+rand, 10.5h+rand... 
            // Better: Base interval + jitter
            $baseDelayMinutes = ($i * 210); // 3.5 hours * i
            $randomJitter = rand(0, 120); // 0-2 hours jitter
            
            $delayMinutes = $baseDelayMinutes + $randomJitter;
            
            $scheduleAt = Carbon::now()->addMinutes($delayMinutes);
            
            // Pick random category (fresh random each time)
            $category = $categories->random();

            ProcessBlogGeneration::dispatch($category->id)
                ->delay($scheduleAt);
                
            Log::info("Scheduler: Queued blog for {$category->name} at {$scheduleAt->toDateTimeString()}");
        }
        
        Log::info("Scheduler: Successfully scheduled 5 blogs.");
    }
}
