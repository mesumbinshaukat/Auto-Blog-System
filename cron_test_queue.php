<?php
/**
 * Cron Test Script for Laravel Queue Worker
 * Place this in: /home/u146506433/
 * 
 * Cron command:
 * * * * * * /usr/bin/php /home/u146506433/cron_test_queue.php >> /home/u146506433/cron_queue.log 2>&1
 */

$logFile = '/home/u146506433/cron_queue.log';
$projectPath = '/home/u146506433/domains/worldoftech.company/public_html/blogs';

// Start logging
$timestamp = date('Y-m-d H:i:s');
$output = "\n========================================\n";
$output .= "QUEUE WORKER TEST - $timestamp\n";
$output .= "========================================\n";

// Check if project directory exists
if (!is_dir($projectPath)) {
    $output .= "ERROR: Project directory not found: $projectPath\n";
    file_put_contents($logFile, $output, FILE_APPEND);
    exit(1);
}

$output .= "✓ Project directory exists\n";

// Check if artisan file exists
$artisanPath = $projectPath . '/artisan';
if (!file_exists($artisanPath)) {
    $output .= "ERROR: Artisan file not found: $artisanPath\n";
    file_put_contents($logFile, $output, FILE_APPEND);
    exit(1);
}

$output .= "✓ Artisan file exists\n";

// Get PHP version
$phpVersion = phpversion();
$output .= "PHP Version: $phpVersion\n";

// Check if another queue worker is running
exec('ps aux | grep "queue:work" | grep -v grep', $processes);
if (!empty($processes)) {
    $output .= "WARNING: Queue worker already running:\n";
    foreach ($processes as $proc) {
        $output .= "  $proc\n";
    }
}

// Change to project directory
chdir($projectPath);
$output .= "Changed directory to: " . getcwd() . "\n";

// Check pending jobs count
try {
    $output .= "\nChecking pending jobs...\n";
    $jobsCheck = '/usr/bin/php artisan tinker --execute="echo \'Pending jobs: \' . DB::table(\'jobs\')->count(); echo \'\nFailed jobs: \' . DB::table(\'failed_jobs\')->count();"';
    exec($jobsCheck, $jobsOutput, $jobsReturn);
    $output .= implode("\n", $jobsOutput) . "\n";
} catch (Exception $e) {
    $output .= "Could not check jobs: " . $e->getMessage() . "\n";
}

// Execute the queue worker
$command = '/usr/bin/php artisan queue:work --stop-when-empty --tries=3 --timeout=300 2>&1';
$output .= "\nExecuting: $command\n";
$output .= "---OUTPUT START---\n";

exec($command, $execOutput, $returnCode);
$output .= implode("\n", $execOutput) . "\n";

$output .= "---OUTPUT END---\n";
$output .= "Return Code: $returnCode\n";

if ($returnCode === 0) {
    $output .= "✓ Queue worker executed successfully\n";
} else {
    $output .= "✗ Queue worker failed with code: $returnCode\n";
}

// Check memory usage
$memoryUsage = memory_get_peak_usage(true) / 1024 / 1024;
$output .= sprintf("Peak Memory Usage: %.2f MB\n", $memoryUsage);

$output .= "========================================\n\n";

// Write to log
file_put_contents($logFile, $output, FILE_APPEND);

echo $output;
