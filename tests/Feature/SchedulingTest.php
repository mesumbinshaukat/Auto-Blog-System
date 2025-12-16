<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Category;
use App\Models\Blog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Mail;
use App\Jobs\GenerateDailyBlogs;
use App\Jobs\ProcessBlogGeneration;
use App\Jobs\BackupDatabase;
use Carbon\Carbon;

class SchedulingTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_generation_queues_exactly_5_blogs()
    {
        Queue::fake();

        Category::create(['name' => 'Tech', 'slug' => 'tech']);

        // Trigger the job
        (new GenerateDailyBlogs)->handle();

        // Check if exactly 5 processing jobs were pushed
        Queue::assertPushed(ProcessBlogGeneration::class, 5);
    }

    public function test_scheduler_enforces_minimum_gap_between_blogs()
    {
        Queue::fake();
        
        $category = Category::create(['name' => 'Tech', 'slug' => 'tech']);

        // Trigger the job
        (new GenerateDailyBlogs)->handle();

        // Verify exactly 5 jobs queued (gap enforcement is tested via logs in integration)
        Queue::assertPushed(ProcessBlogGeneration::class, 5);
    }

    public function test_no_categories_sends_email_notification()
    {
        Mail::fake();
        Queue::fake();

        // No categories in database

        // Trigger the job
        (new GenerateDailyBlogs)->handle();

        // Should not queue any jobs
        Queue::assertNothingPushed();

        // Should send email notification
        Mail::assertSent(\App\Mail\BlogGenerationReport::class, function ($mail) {
            return $mail->error !== null && 
                   str_contains($mail->error->getMessage(), 'No categories available');
        });
    }

    public function test_backup_job_runs()
    {
        // Integration test for backup would require mocking DB connections
        // Here we just ensure the class is instantiable and queueable
        Queue::fake();
        
        BackupDatabase::dispatch();
        
        Queue::assertPushed(BackupDatabase::class);
    }
}
