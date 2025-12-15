<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class CriticalErrorMail extends Mailable
{
    use Queueable, SerializesModels;

    public $errorType;
    public $error;
    public $stackTrace;
    public $contextData;

    /**
     * Create a new message instance.
     */
    public function __construct(string $errorType, string $error, string $stackTrace, array $contextData = [])
    {
        $this->errorType = $errorType;
        $this->error = $error;
        $this->stackTrace = $stackTrace;
        $this->contextData = $contextData;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "🚨 CRITICAL: {$this->errorType}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.critical-error',
            with: [
                'occurredAt' => now()->format('M d, Y H:i:s'),
            ],
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
