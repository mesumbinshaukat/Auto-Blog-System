<?php
/**
 * Cron Test Script for Laravel Scheduler
 * Place this in: /home/u146506433/
 * 
 * Cron command:
 * * * * * * /usr/bin/php /home/u146506433/cron_test_scheduler.php >> /home/u146506433/cron_scheduler.log 2>&1
 */

$logFile = '/home/u146506433/cron_scheduler.log';
$projectPath = '/home/u146506433/domains/worldoftech.company/public_html/blogs';

// Start logging
$timestamp = date('Y-m-d H:i:s');
$output = "\n========================================\n";
$output .= "SCHEDULER TEST - $timestamp\n";
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

// Check PHP requirements
if (version_compare($phpVersion, '8.2.0', '<')) {
    $output .= "WARNING: PHP version is below 8.2.0\n";
}

// Change to project directory and run scheduler
chdir($projectPath);
$output .= "Changed directory to: " . getcwd() . "\n";

// Execute the scheduler
$command = '/usr/bin/php artisan schedule:run 2>&1';
$output .= "Executing: $command\n";
$output .= "---OUTPUT START---\n";

exec($command, $execOutput, $returnCode);
$output .= implode("\n", $execOutput) . "\n";

$output .= "---OUTPUT END---\n";
$output .= "Return Code: $returnCode\n";

if ($returnCode === 0) {
    $output .= "✓ Scheduler executed successfully\n";
} else {
    $output .= "✗ Scheduler failed with code: $returnCode\n";
}

// Check jobs table
try {
    $output .= "\nChecking jobs table...\n";
    $jobsCheck = '/usr/bin/php artisan tinker --execute="echo \'Jobs in queue: \' . DB::table(\'jobs\')->count();"';
    exec($jobsCheck, $jobsOutput, $jobsReturn);
    $output .= implode("\n", $jobsOutput) . "\n";
} catch (Exception $e) {
    $output .= "Could not check jobs table: " . $e->getMessage() . "\n";
}

$output .= "========================================\n\n";

// Write to log
file_put_contents($logFile, $output, FILE_APPEND);

echo $output;
