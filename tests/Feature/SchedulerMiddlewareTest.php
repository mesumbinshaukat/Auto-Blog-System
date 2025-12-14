<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\DB;
use App\Jobs\GenerateDailyBlogs;
use Carbon\Carbon;

use Illuminate\Foundation\Testing\RefreshDatabase;

class SchedulerMiddlewareTest extends TestCase
{
    use RefreshDatabase;

    protected function setUp(): void
    {
        parent::setUp();
        Cache::flush();
        
        // Ensure jobs table exists for middleware query if separate from RefreshDatabase logic?
        // RefreshDatabase handles migration.
    }
    
    // ... existing tests ...

    public function test_queue_worker_runs_if_jobs_exist()
    {
        Artisan::shouldReceive('call')
            ->once()
            ->with('queue:work', \Mockery::on(function ($args) {
                return isset($args['--once']) && $args['--once'] === true
                    && isset($args['--stop-when-empty']) && $args['--stop-when-empty'] === true;
            }));

        // Insert a dummy job into the database so count > 0
        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => json_encode(['job' => 'TestJob', 'data' => []]),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => Carbon::now()->timestamp,
            'created_at' => Carbon::now()->timestamp,
        ]);
        
        // Simulate request to trigger middleware
        $this->get('/');
    }
    
    public function test_scheduler_locks_prevent_double_execution()
    {
        Bus::fake();
        
        // Force lock to be taken
        Cache::lock('daily_scheduler_lock', 300)->get();
        
        // simulate needs run
        Cache::put('last_daily_run', Carbon::now()->subHours(25)->toDateTimeString());
        
        $this->get('/');
        
        // Should NOT dispatch because lock is held
        Bus::assertNotDispatched(GenerateDailyBlogs::class);
    }
}
