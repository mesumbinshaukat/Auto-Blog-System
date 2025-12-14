#!/usr/bin/env php
<?php
/**
 * Quick Queue Worker Test
 * 
 * This script manually processes ONE job from the queue
 * to see the exact error that's preventing jobs from running.
 * 
 * Usage: php test_queue_worker.php
 */

define('LARAVEL_START', microtime(true));

require __DIR__.'/vendor/autoload.php';

$app = require_once __DIR__.'/bootstrap/app.php';
$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "\n========================================\n";
echo "QUEUE WORKER TEST\n";
echo "========================================\n\n";

// Check pending jobs
$pendingJobs = DB::table('jobs')->count();
echo "Pending jobs: $pendingJobs\n\n";

if ($pendingJobs === 0) {
    echo "No jobs in queue. Dispatching a test job...\n";
    
    $category = \App\Models\Category::first();
    if (!$category) {
        echo "✗ No categories found!\n";
        exit(1);
    }
    
    $jobId = \Illuminate\Support\Str::uuid()->toString();
    \App\Jobs\GenerateBlogJob::dispatch($category->id, $jobId);
    echo "✓ Test job dispatched (ID: $jobId)\n\n";
}

echo "Attempting to process ONE job...\n";
echo "========================================\n\n";

try {
    // Run queue worker for one job only
    Artisan::call('queue:work', [
        '--once' => true,
        '--tries' => 1,
        '--timeout' => 300,
        '--verbose' => true
    ]);
    
    $output = Artisan::output();
    echo $output;
    
    echo "\n========================================\n";
    echo "✓ Queue worker executed\n";
    echo "========================================\n";
    
} catch (\Exception $e) {
    echo "\n✗✗✗ ERROR ✗✗✗\n";
    echo "========================================\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . ":" . $e->getLine() . "\n";
    echo "\nStack Trace:\n" . $e->getTraceAsString() . "\n";
    echo "========================================\n";
}

// Check cache for job status
echo "\nChecking cache for job statuses...\n";
$cacheKeys = Cache::get('blog_job_*');
if ($cacheKeys) {
    print_r($cacheKeys);
} else {
    echo "No job statuses found in cache\n";
}

echo "\nDone.\n";
