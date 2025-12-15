<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class DailyGenerationSummaryMail extends Mailable
{
    use Queueable, SerializesModels;

    public $successCount;
    public $failureCount;
    public $errors;
    public $successfulBlogs;

    /**
     * Create a new message instance.
     */
    public function __construct(int $successCount, int $failureCount, array $errors = [], array $successfulBlogs = [])
    {
        $this->successCount = $successCount;
        $this->failureCount = $failureCount;
        $this->errors = $errors;
        $this->successfulBlogs = $successfulBlogs;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $status = $this->failureCount > 0 ? '⚠️' : '✅';
        return new Envelope(
            subject: "{$status} Daily Generation Summary - {$this->successCount} Success, {$this->failureCount} Failed",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.daily-generation-summary',
        );
    }

    /**
     * Get the attachments for the message.
     */
    public function attachments(): array
    {
        return [];
    }
}
