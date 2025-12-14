<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Support\Facades\Mail;

class TestEmail extends Command
{
    protected $signature = 'test:email {--to=masumbinshaukat@gmail.com}';
    protected $description = 'Send a test email to verify email configuration';

    public function handle()
    {
        $to = $this->option('to');
        
        try {
            Mail::raw('This is a test email from Auto Blog System. If you receive this, your email configuration is working correctly!', function ($message) use ($to) {
                $message->to($to)
                    ->subject('Test Email - Auto Blog System');
            });

            $this->info("✅ Test email sent successfully to: {$to}");
            return Command::SUCCESS;
            
        } catch (\Exception $e) {
            $this->error("❌ Failed to send email: " . $e->getMessage());
            return Command::FAILURE;
        }
    }
}
