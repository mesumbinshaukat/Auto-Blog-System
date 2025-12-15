<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class APIFailureReportMail extends Mailable
{
    use Queueable, SerializesModels;

    public $apis;
    public $fallback;

    /**
     * Create a new message instance.
     */
    public function __construct(array $apis, string $fallback = 'Mock Content')
    {
        $this->apis = $apis;
        $this->fallback = $fallback;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: '🔴 Multiple API Failures Detected',
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.api-failure-report',
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
