<?php

use Illuminate\Support\Facades\Schedule;
use App\Jobs\GenerateDailyBlogs;
use App\Jobs\BackupDatabase;

Schedule::job(new GenerateDailyBlogs)->dailyAt('00:00');
Schedule::job(new BackupDatabase)->dailyAt('23:00');
