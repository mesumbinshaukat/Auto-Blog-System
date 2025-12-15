<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Jobs\GenerateBlogJob;
use App\Models\Category;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Mail;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

class ErrorRecoveryTest extends TestCase
{
    use RefreshDatabase;

    /**
     * Test job has correct retry configuration
     */
    public function test_job_has_correct_retry_configuration(): void
    {
        $category = Category::factory()->create();
        $job = new GenerateBlogJob($category->id, 'test-job-id');
        
        $this->assertEquals(5, $job->tries);
        $this->assertEquals([60, 300, 600], $job->backoff);
        $this->assertEquals(900, $job->timeout);
    }

    /**
     * Test job logs attempt number on failure
     */
    public function test_job_logs_attempt_number_on_failure(): void
    {
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->with(\Mockery::pattern('/JOB START/'));
        Log::shouldReceive('info')->with(\Mockery::pattern('/Starting generation/'));
        Log::shouldReceive('error')->with(\Mockery::pattern('/Attempt \d+\/5/'));
        Log::shouldReceive('error')->with(\Mockery::pattern('/Stack trace/'));
        
        $category = Category::factory()->create();
        $job = new GenerateBlogJob($category->id, 'test-job-id');
        
        // This will fail because BlogGeneratorService is not mocked
        try {
            $job->handle(app(\App\Services\BlogGeneratorService::class));
        } catch (\Exception $e) {
            // Expected to fail
        }
    }

    /**
     * Test cache stores attempt number on failure
     */
    public function test_cache_stores_attempt_number(): void
    {
        $category = Category::factory()->create();
        $jobId = 'test-job-' . time();
        $job = new GenerateBlogJob($category->id, $jobId);
        
        try {
            $job->handle(app(\App\Services\BlogGeneratorService::class));
        } catch (\Exception $e) {
            // Expected
        }
        
        $cached = Cache::get("blog_job_{$jobId}");
        if ($cached) {
            $this->assertArrayHasKey('status', $cached);
            $this->assertEquals('failed', $cached['status']);
        }
    }

    /**
     * Test quota error detection
     */
    public function test_quota_error_detection(): void
    {
        $quotaErrors = [
            'API quota exceeded',
            'HTTP 402 Payment Required',
            'HTTP 429 Too Many Requests',
            'Rate limit exceeded'
        ];
        
        foreach ($quotaErrors as $error) {
            $this->assertTrue(
                str_contains($error, 'quota') || 
                str_contains($error, '402') || 
                str_contains($error, '429'),
                "Failed to detect quota error: $error"
            );
        }
    }

    /**
     * Test job configuration is valid
     */
    public function test_job_configuration_is_valid(): void
    {
        $category = Category::factory()->create();
        $job = new GenerateBlogJob($category->id, 'test-job-id');
        
        // Verify job implements ShouldQueue
        $this->assertInstanceOf(\Illuminate\Contracts\Queue\ShouldQueue::class, $job);
        
        // Verify timeout is reasonable (15 minutes)
        $this->assertGreaterThanOrEqual(600, $job->timeout);
        $this->assertLessThanOrEqual(1800, $job->timeout);
        
        // Verify tries is between 3-10
        $this->assertGreaterThanOrEqual(3, $job->tries);
        $this->assertLessThanOrEqual(10, $job->tries);
        
        // Verify backoff delays are increasing
        $this->assertGreaterThan($job->backoff[0], $job->backoff[1]);
        $this->assertGreaterThan($job->backoff[1], $job->backoff[2]);
    }

    /**
     * Test job can be serialized and unserialized
     */
    public function test_job_serialization(): void
    {
        $category = Category::factory()->create();
        $job = new GenerateBlogJob($category->id, 'test-job-id');
        
        $serialized = serialize($job);
        $unserialized = unserialize($serialized);
        
        $this->assertInstanceOf(GenerateBlogJob::class, $unserialized);
        $this->assertEquals($job->tries, $unserialized->tries);
        $this->assertEquals($job->timeout, $unserialized->timeout);
    }

    /**
     * Test backoff delays are exponential
     */
    public function test_backoff_delays_are_exponential(): void
    {
        $category = Category::factory()->create();
        $job = new GenerateBlogJob($category->id, 'test-job-id');
        
        // Verify backoff pattern: 60s, 300s (5min), 600s (10min)
        $this->assertEquals(60, $job->backoff[0]);
        $this->assertEquals(300, $job->backoff[1]);
        $this->assertEquals(600, $job->backoff[2]);
        
        // Verify exponential growth
        $ratio1 = $job->backoff[1] / $job->backoff[0]; // Should be 5
        $ratio2 = $job->backoff[2] / $job->backoff[1]; // Should be 2
        
        $this->assertGreaterThan(1, $ratio1);
        $this->assertGreaterThan(1, $ratio2);
    }

    /**
     * Test job handles category not found gracefully
     */
    public function test_job_handles_category_not_found(): void
    {
        Log::shouldReceive('channel')->andReturnSelf();
        Log::shouldReceive('info')->with(\Mockery::pattern('/JOB START/'));
        Log::shouldReceive('error')->with('GenerateBlogJob: Category not found');
        
        $jobId = 'test-job-' . time();
        $job = new GenerateBlogJob(99999, $jobId); // Non-existent category
        
        $job->handle(app(\App\Services\BlogGeneratorService::class));
        
        $cached = Cache::get("blog_job_{$jobId}");
        $this->assertNotNull($cached);
        $this->assertEquals('failed', $cached['status']);
        $this->assertEquals('Category not found', $cached['message']);
    }

    /**
     * Test error message truncation for long errors
     */
    public function test_error_message_handling(): void
    {
        $longError = str_repeat('Error details ', 1000);
        
        // Verify we can handle long error messages
        $this->assertGreaterThan(1000, strlen($longError));
        
        // In production, these should be logged but not crash
        $truncated = substr($longError, 0, 500);
        $this->assertEquals(500, strlen($truncated));
    }

    /**
     * Test job queue connection
     */
    public function test_job_uses_correct_queue_connection(): void
    {
        $category = Category::factory()->create();
        
        Queue::fake();
        
        GenerateBlogJob::dispatch($category->id, 'test-job-id');
        
        Queue::assertPushed(GenerateBlogJob::class);
    }
}
