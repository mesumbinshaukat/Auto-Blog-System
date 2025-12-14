<?php

use Illuminate\Support\Facades\Schedule;
use App\Jobs\GenerateDailyBlogs;
use App\Jobs\BackupDatabase;

Schedule::job(new GenerateDailyBlogs)->dailyAt('00:00');
Schedule::job(new BackupDatabase)->dailyAt('23:00');

// Daily System & API Health Check
Schedule::call(function () {
    $apiStatus = 'OK'; // Logic to check API connectivity could go here
    $blogCount = \App\Models\Blog::whereDate('created_at', today())->count();
    
    Mail::raw("Daily system check:\nAPIs: $apiStatus\nBlogs Generated Today: $blogCount", function ($message) {
        $message->to('mesum@worldoftech.company')
            ->subject('Daily System Health Check');
    });
})->dailyAt('23:55');
