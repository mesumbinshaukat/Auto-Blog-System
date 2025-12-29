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
use App\Mail\SystemErrorReport;
use Illuminate\Support\Facades\Mail;
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
                Cache::put('daily_scheduler_lock_start', time(), 300);
                
                GenerateDailyBlogs::dispatch();
                
                Cache::put('last_daily_run', Carbon::now()->toDateTimeString());
                
                if ($lastRun && Carbon::parse($lastRun)->addHours(25)->isPast()) {
                     Log::warning('Scheduler: Low traffic detected. Scheduler ran late (last run: ' . $lastRun . ').');
                     
                     try {
                         if (Cache::add('scheduler_alert_sent', true, 86400)) { 
                             Mail::to(env('REPORTS_EMAIL', 'admin@example.com'))
                                 ->send(new SystemErrorReport('Scheduler Stalled', "Auto-Blog Scheduler ran late. Last run: $lastRun. This indicates low site traffic or cron issues. Current time: " . now()->toDateTimeString()));
                         }
                     } catch (\Exception $e) {
                         Log::error("Failed to send scheduler alert: " . $e->getMessage());
                     }
                }
                
            } catch (\Exception $e) {
                Log::error('Scheduler Error: ' . $e->getMessage());
                try {
                    Mail::to(env('REPORTS_EMAIL', 'admin@example.com'))
                        ->send(new SystemErrorReport('Daily Scheduler Error', $e->getMessage()));
                } catch (\Exception $mailEx) {}
            } finally {
                $lock->release();
            }
        } else {
            // If lock couldn't be acquired and it's OVERDUE, it might be a stuck lock
            if ($lastRun && Carbon::parse($lastRun)->addHours(25)->isPast()) {
                $lockOwner = Cache::get('daily_scheduler_lock');
                // We don't have a reliable way to check owner for atomic locks without internal repo info,
                // but we can check if it's been > 10 mins (lock is for 300s but let's be safe)
                // Note: daily_scheduler_lock_time is not yet implemented, let's add it in the success block
                // Actually, let's just use a simple timestamp cache.
                $lockTime = Cache::get('daily_scheduler_lock_start');
                if ($lockTime && (time() - $lockTime) > 3600) { // Force release after 1 hour as requested
                    Log::warning("Force releasing stuck daily_scheduler_lock (held for >3600s)");
                    Cache::forget('daily_scheduler_lock');
                    try {
                        Mail::to(env('REPORTS_EMAIL', 'admin@example.com'))
                            ->send(new SystemErrorReport('Stuck Lock Recovered', "The daily scheduler lock was stuck for >3600s and has been force-released."));
                    } catch (\Exception $e) {}
                }
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

        $startTime = microtime(true);
        $jobsProcessed = 0;
        $maxJobsPerRun = ($jobsCount > 5) ? 2 : 1; 
        $lockDuration = ($jobsCount > 5) ? 300 : 120; // Extend lock if backlog

        $lock = Cache::lock('queue_processor_lock', $lockDuration);

        if ($lock->get()) {
            try {
                // Record lock time for stuck detection
                Cache::put('queue_processor_lock_time', time(), $lockDuration);

                while ($jobsProcessed < $maxJobsPerRun) {
                    $remainingJobs = DB::table('jobs')->count();
                    if ($remainingJobs === 0) break;

                    try {
                        Artisan::call('queue:work', [
                            '--once' => true,
                            '--stop-when-empty' => true,
                            '--queue' => 'default',
                            '--memory' => 256,
                            '--timeout' => 900,
                            '--tries' => 1
                        ]);
                    } catch (\Illuminate\Queue\MaxAttemptsExceededException $e) {
                         Log::error("Queue Worker: Job max attempts reached. Skipping. " . $e->getMessage());
                    } catch (\Throwable $e) {
                         Log::error("Queue Worker: Job failed with error: " . $e->getMessage());
                    }

                    $jobsProcessed++;

                    // If we've spent >30s, don't pick up another one
                    if ((microtime(true) - $startTime) > 30) {
                        break;
                    }
                }
            } catch (\Exception $e) {
                Log::error('Queue Worker Error: ' . $e->getMessage());
                try {
                    Mail::to(env('REPORTS_EMAIL', 'admin@example.com'))
                        ->send(new SystemErrorReport('Queue Worker Failed', $e->getMessage()));
                } catch (\Exception $mailEx) {}
            } finally {
                $lock->release();
            }
        } else {
             // Stuck lock detection for queue worker
             $lockTime = Cache::get('queue_processor_lock_time', 0);
             if ($lockTime && (time() - $lockTime) > 600) {
                 Log::warning("Force releasing stuck queue_processor_lock");
                 Cache::forget('queue_processor_lock');
             }
        }
    }
}
