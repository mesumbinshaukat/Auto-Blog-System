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
use Illuminate\Support\Facades\Cache;
use App\Jobs\ProcessBlogGeneration;

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
        
        // 1. Daily Limit Check (Enforce 5 per day)
        $today = $now->format('Y-m-d');
        $cachedDate = Cache::get('daily_blog_date');
        
        if ($cachedDate !== $today) {
            Log::info("Scheduler: New day detected ($today), resetting daily blog count.");
            Cache::put('daily_blog_date', $today, 86400 * 2);
            Cache::put('daily_blog_count', 0, 86400 * 2);
        }
        
        $currentCount = (int)Cache::get('daily_blog_count', 0);
        $maxDaily = 5;
        
        if ($currentCount >= $maxDaily) {
            Log::warning("Scheduler: Daily blog limit reached ($currentCount/$maxDaily). Skipping generation for today.");
            
            // Notify admin about limit reached
            try {
                $email = env('REPORTS_EMAIL', 'mesumbinshaukat@gmail.com');
                \Illuminate\Support\Facades\Mail::to($email)
                    ->send(new \App\Mail\SystemErrorReport(
                        "Daily Blog Limit Reached",
                        "The system has already generated/queued $currentCount blogs today ($today). The daily limit is $maxDaily. No more blogs will be scheduled until tomorrow."
                    ));
            } catch (\Exception $e) {
                Log::error("Failed to send daily limit notification: " . $e->getMessage());
            }
            
            return;
        }

        $remainingToSchedule = $maxDaily - $currentCount;
        Log::info("Scheduler: $currentCount/$maxDaily blogs already processed today. Scheduling up to $remainingToSchedule more.");

        // Generate exactly remaining blogs with >=3.5hr (210 min) gaps
        for ($i = 0; $i < $remainingToSchedule; $i++) {
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
            
            // Increment count immediately to prevent over-scheduling in concurrent runs
            $currentCount++;
            Cache::put('daily_blog_count', $currentCount, 86400 * 2);
            
            Log::info("Scheduler: Queued blog #" . ($currentCount) . " for {$category->name} at {$scheduleAt->toDateTimeString()} (delay: {$delayMinutes} min)");
        }
        
        Log::info("Scheduler: Successfully scheduled blogs at: " . implode(", ", $scheduledTimes));
    }
}
