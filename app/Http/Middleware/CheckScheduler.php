<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Carbon\Carbon;
use App\Jobs\GenerateDailyBlogs;
use Symfony\Component\HttpFoundation\Response;

class CheckScheduler
{
    /**
     * Handle an incoming request.
     *
     * @param  \Closure(\Illuminate\Http\Request): (\Symfony\Component\HttpFoundation\Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        // Run logic AFTER the response to minimize user impact?
        // Laravel Terminable Middleware is better for this, but for simple "poor man's cron"
        // running before/after in handle is simplest. If we use terminate(), it requires 
        // the middleware to be registered differently or depend on FPM/Server setup.
        // Let's run BEFORE for simplicity if fast, or use terminate() method.
        // Creating a terminable middleware is safer for response time.
        
        return $next($request);
    }

    /**
     * Handle tasks after the response has been sent to the browser.
     */
    public function terminate(Request $request, Response $response): void
    {
        $this->runDailyScheduler();
        $this->processQueue();
    }

    protected function runDailyScheduler(): void
    {
        // 1. Check if we need to run daily tasks
        // Default to running if never run (or cache cleared)
        $lastRun = Cache::get('last_daily_run');
        
        // If run less than 24 hours ago, skip
        if ($lastRun && Carbon::parse($lastRun)->addHours(24)->isFuture()) {
            return;
        }
        
        // 2. Acquire lock to prevent multiple requests triggering it simultaneously
        // Lock for 5 minutes (300s) to give time for dispatch logic
        $lock = Cache::lock('daily_scheduler_lock', 300);
        
        if ($lock->get()) {
            try {
                Log::info('Scheduler: Triggering daily blog generation.');
                
                // Dispatch the main job
                // dispatchSync or dispatch is fine. dispatchSync blocks this request slightly 
                // but ensures it enters queue. GenerateDailyBlogs is fast (just dispatches other jobs).
                GenerateDailyBlogs::dispatch();
                
                // Update last run time to NOW
                Cache::put('last_daily_run', Carbon::now()->toDateTimeString());
                
                // Alert if it's been > 25 hours (low traffic hint) but we just ran it, 
                // so we are good. If we wanted an alert for *missing* runs, we'd need an external monitor.
                // But we can log "Low traffic detected" if $lastRun was > 25 hours ago.
                if ($lastRun && Carbon::parse($lastRun)->addHours(25)->isPast()) {
                     Log::warning('Scheduler: Low traffic detected. Scheduler ran late (last run: ' . $lastRun . ').');
                     
                     // Send email alert (rate limited to once per 24h to avoid spam)
                     try {
                         if (Cache::add('scheduler_alert_sent', true, 86400)) { // 24 hours
                             \Illuminate\Support\Facades\Mail::raw("Warning: Auto-Blog Scheduler is running late.\nLast run: $lastRun\nCurrent time: " . now()->toDateTimeString() . "\n\nThis indicates low site traffic or cron issues.", function ($message) {
                                 $message->to(env('REPORTS_EMAIL', 'admin@example.com'))
                                     ->subject('⚠️ Auto-Blog Scheduler Warning: Low Traffic / Late Run');
                             });
                         }
                     } catch (\Exception $e) {
                         Log::error("Failed to send scheduler alert: " . $e->getMessage());
                     }
                }
                
            } catch (\Exception $e) {
                Log::error('Scheduler Error: ' . $e->getMessage());
            } finally {
                $lock->release();
            }
        }
    }

    protected function processQueue(): void
    {
        // 1. Check if there are jobs in the database
        // Use DB directly for speed, avoid overhead if empty
        $jobsCount = DB::table('jobs')->count();
        
        if ($jobsCount === 0) {
            return;
        }

        // 2. Acquire lock to prevent every concurrent request from picking up a job
        // Lock for 60 seconds. We only process ONE job per request to avoid timeout.
        $lock = Cache::lock('queue_processor_lock', 60);

        if ($lock->get()) {
            try {
                // Run one job
                // --stop-when-empty ensures it stops if weird race condition cleared queue
                // --once processes only one job
                Artisan::call('queue:work', [
                    '--once' => true,
                    '--stop-when-empty' => true,
                    '--queue' => 'default',
                    '--memory' => 128,
                    '--timeout' => 60
                ]);
                
                // Log output only if detailed debug needed, otherwise keep silent to save logs
                // Log::info('Scheduler: Processed background job.');
                
            } catch (\Exception $e) {
                Log::error('Queue Worker Error: ' . $e->getMessage());
            } finally {
                // Release lock immediately so next request can pick up next job?
                // OR keep it locked for a bit to throttle?
                // User asked: "run Artisan::call... without blocking long".
                // If we release immediately, high traffic site might churn through queue fast (good).
                // But shared host might limit CPU. Let's release.
                $lock->release();
            }
        }
    }
}
