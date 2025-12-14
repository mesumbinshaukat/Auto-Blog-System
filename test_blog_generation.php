#!/usr/bin/env php
<?php
/**
 * Production Blog Generation Test Script
 * 
 * This script tests blog generation directly without queues
 * to identify the exact error preventing production jobs from running.
 * 
 * Usage: php test_blog_generation.php
 */

define('LARAVEL_START', microtime(true));

// Require the Composer autoloader
require __DIR__.'/vendor/autoload.php';

// Bootstrap Laravel
$app = require_once __DIR__.'/bootstrap/app.php';

$kernel = $app->make(Illuminate\Contracts\Console\Kernel::class);
$kernel->bootstrap();

echo "\n========================================\n";
echo "PRODUCTION BLOG GENERATION TEST\n";
echo "========================================\n\n";

// Check environment
echo "Environment: " . app()->environment() . "\n";
echo "PHP Version: " . phpversion() . "\n";
echo "Laravel Version: " . app()->version() . "\n\n";

// Check database connection
try {
    DB::connection()->getPdo();
    echo "✓ Database connection successful\n";
} catch (Exception $e) {
    echo "✗ Database connection failed: " . $e->getMessage() . "\n";
    exit(1);
}

// Check required environment variables
$requiredEnvVars = ['GEMINI_API_KEY', 'HUGGINGFACE_API_KEY'];
foreach ($requiredEnvVars as $var) {
    $value = env($var);
    if (empty($value)) {
        echo "✗ Missing environment variable: $var\n";
    } else {
        echo "✓ $var is set (" . substr($value, 0, 10) . "...)\n";
    }
}
echo "\n";

// Check categories
$categories = \App\Models\Category::all();
echo "Categories found: " . $categories->count() . "\n";
if ($categories->isEmpty()) {
    echo "✗ No categories found in database!\n";
    exit(1);
}

// Select first category for testing
$category = $categories->first();
echo "Testing with category: {$category->name} (ID: {$category->id})\n\n";

// Check jobs table
$pendingJobs = DB::table('jobs')->count();
$failedJobs = DB::table('failed_jobs')->count();
echo "Pending jobs in queue: $pendingJobs\n";
echo "Failed jobs: $failedJobs\n\n";

echo "========================================\n";
echo "STARTING BLOG GENERATION TEST\n";
echo "========================================\n\n";

try {
    // Create instances of required services
    echo "[1/6] Initializing services...\n";
    $scrapingService = app(\App\Services\ScrapingService::class);
    $aiService = app(\App\Services\AIService::class);
    $thumbnailService = app(\App\Services\ThumbnailService::class);
    $blogGenerator = new \App\Services\BlogGeneratorService(
        $scrapingService,
        $aiService,
        $thumbnailService
    );
    echo "✓ Services initialized\n\n";

    // Test scraping
    echo "[2/6] Testing scraping service...\n";
    $topics = $scrapingService->fetchTrendingTopics($category->slug);
    echo "✓ Found " . count($topics) . " topics\n";
    if (!empty($topics)) {
        echo "Sample topic: " . $topics[0] . "\n";
    }
    echo "\n";

    // Test research
    echo "[3/6] Testing research service...\n";
    $testTopic = $topics[0] ?? 'Artificial Intelligence';
    $research = $scrapingService->researchTopic($testTopic);
    echo "✓ Research data length: " . strlen($research) . " characters\n\n";

    // Test AI service
    echo "[4/6] Testing AI content generation...\n";
    $rawContent = $aiService->generateRawContent($testTopic, $category->name, substr($research, 0, 500));
    echo "✓ Raw content generated: " . strlen($rawContent) . " characters\n\n";

    // Test AI optimization
    echo "[5/6] Testing AI optimization...\n";
    $optimized = $aiService->optimizeAndHumanize($rawContent);
    echo "✓ Content optimized\n";
    echo "  - Content length: " . strlen($optimized['content']) . " characters\n";
    echo "  - TOC items: " . count($optimized['toc']) . "\n\n";

    // Test full blog generation
    echo "[6/6] Testing FULL blog generation...\n";
    echo "This may take 2-3 minutes...\n\n";
    
    $blog = $blogGenerator->generateBlogForCategory($category, function($message, $progress) {
        echo "  [$progress%] $message\n";
    });

    if ($blog) {
        echo "\n✓✓✓ SUCCESS! Blog generated!\n";
        echo "========================================\n";
        echo "Blog ID: {$blog->id}\n";
        echo "Title: {$blog->title}\n";
        echo "Slug: {$blog->slug}\n";
        echo "Content length: " . strlen($blog->content) . " characters\n";
        echo "Thumbnail: {$blog->thumbnail_path}\n";
        echo "========================================\n\n";
        
        // Verify thumbnail exists
        if ($blog->thumbnail_path) {
            $thumbnailFullPath = storage_path('app/public/' . $blog->thumbnail_path);
            if (file_exists($thumbnailFullPath)) {
                echo "✓ Thumbnail file exists (" . filesize($thumbnailFullPath) . " bytes)\n";
            } else {
                echo "✗ Thumbnail file NOT found at: $thumbnailFullPath\n";
            }
        }
        
    } else {
        echo "\n✗ Blog generation returned NULL (likely duplicate topic)\n";
    }

} catch (\Exception $e) {
    echo "\n✗✗✗ ERROR OCCURRED ✗✗✗\n";
    echo "========================================\n";
    echo "Error: " . $e->getMessage() . "\n";
    echo "File: " . $e->getFile() . "\n";
    echo "Line: " . $e->getLine() . "\n";
    echo "\nStack Trace:\n";
    echo $e->getTraceAsString() . "\n";
    echo "========================================\n";
    exit(1);
}

echo "\n========================================\n";
echo "TEST COMPLETED SUCCESSFULLY\n";
echo "========================================\n\n";

// Now test queue job
echo "Testing Queue Job Dispatch...\n";
try {
    $jobId = \Illuminate\Support\Str::uuid()->toString();
    \App\Jobs\GenerateBlogJob::dispatch($category->id, $jobId);
    echo "✓ Job dispatched with ID: $jobId\n";
    
    // Check if job was added to database
    $jobInDb = DB::table('jobs')->where('id', '>', 0)->latest('id')->first();
    if ($jobInDb) {
        echo "✓ Job found in database (ID: {$jobInDb->id})\n";
    } else {
        echo "✗ Job NOT found in database\n";
    }
    
    echo "\nTo process this job, run:\n";
    echo "php artisan queue:work --stop-when-empty\n";
    
} catch (\Exception $e) {
    echo "✗ Failed to dispatch job: " . $e->getMessage() . "\n";
}

echo "\nDone.\n";
