<?php

namespace Tests\Feature;

use Tests\TestCase;
use Illuminate\Support\Facades\Artisan;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Cache;
use Carbon\Carbon;
use Illuminate\Foundation\Testing\RefreshDatabase;

class LongJobTest extends TestCase
{
    use RefreshDatabase;

    public function test_worker_can_handle_long_running_job_without_max_attempts_simulated()
    {
        // We can't easily mock a REAL process kill from PHPUnit within the same process
        // But we can verify that Artisan::call is triggered with the correct timeout
        // which we already did in SchedulerMiddlewareTest.
        
        // This test will verify that IF we run the worker command via Artisan::call
        // it doesn't throw immediate errors and parameters are passed.
        
        Artisan::shouldReceive('call')
            ->once()
            ->with('queue:work', \Mockery::on(function ($args) {
                return $args['--timeout'] === 900 && $args['--memory'] === 256;
            }))
            ->andReturn(0);

        DB::table('jobs')->insert([
            'queue' => 'default',
            'payload' => json_encode(['job' => 'LongJob']),
            'attempts' => 0,
            'reserved_at' => null,
            'available_at' => time(),
            'created_at' => time(),
        ]);

        $this->get('/');
        
        $this->assertTrue(true); // Reached here means no exception
    }
}
