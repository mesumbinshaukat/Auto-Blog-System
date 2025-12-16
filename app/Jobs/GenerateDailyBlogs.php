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
        Log::info("Scheduler: Starting daily blog generation (5 blogs with >=3.5hr gaps)");

        $categories = Category::all();
        if ($categories->isEmpty()) {
            Log::error("Scheduler: No categories found, aborting generation");
            
            // Send email notification
            try {
                \Illuminate\Support\Facades\Mail::to(env('REPORTS_EMAIL', 'masumbinshaukat@gmail.com'))
                    ->send(new \App\Mail\BlogGenerationReport(
                        null,
                        new \Exception("No categories available for blog generation"),
                        ["ERROR: No categories found in database"],
                        false
                    ));
            } catch (\Exception $e) {
                Log::error("Failed to send no-categories email: " . $e->getMessage());
            }
            
            return;
        }

        $scheduledTimes = [];
        $now = Carbon::now();
        
        // Generate exactly 5 blogs with >=3.5hr (210 min) gaps
        for ($i = 0; $i < 5; $i++) {
            // Calculate delay: base interval (3.5h * i) + random jitter (0-120 min)
            $baseDelayMinutes = $i * 210; // 0, 210, 420, 630, 840 minutes
            $randomJitter = rand(0, 120); // 0-2 hours for organic distribution
            
            $delayMinutes = $baseDelayMinutes + $randomJitter;
            $scheduleAt = $now->copy()->addMinutes($delayMinutes);
            
            // Pick random category for each blog
            $category = $categories->random();

            ProcessBlogGeneration::dispatch($category->id)
                ->delay($scheduleAt);
                
            $scheduledTimes[] = $scheduleAt->toDateTimeString();
            Log::info("Scheduler: Queued blog #" . ($i+1) . " for {$category->name} at {$scheduleAt->toDateTimeString()} (delay: {$delayMinutes} min)");
        }
        
        Log::info("Scheduler: Successfully scheduled 5 blogs at: " . implode(", ", $scheduledTimes));
    }
}
