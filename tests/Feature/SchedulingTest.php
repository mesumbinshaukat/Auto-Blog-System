<?php

namespace Tests\Feature;

use Tests\TestCase;
use App\Models\User;
use App\Models\Category;
use App\Models\Blog;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Queue;
use App\Jobs\GenerateDailyBlogs;
use App\Jobs\ProcessBlogGeneration;
use App\Jobs\BackupDatabase;

class SchedulingTest extends TestCase
{
    use RefreshDatabase;

    public function test_daily_generation_job_is_queued()
    {
        Queue::fake();

        Category::create(['name' => 'Tech', 'slug' => 'tech']);

        // Trigger the job
        (new GenerateDailyBlogs)->handle();

        // Check if individual processing jobs were pushed
        Queue::assertPushed(ProcessBlogGeneration::class, 5);
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
