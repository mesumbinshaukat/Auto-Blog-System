<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class QuotaExceededMail extends Mailable
{
    use Queueable, SerializesModels;

    public $api;
    public $details;
    public $retryAt;

    /**
     * Create a new message instance.
     */
    public function __construct(string $api, string $details, $retryAt = null)
    {
        $this->api = $api;
        $this->details = $details;
        $this->retryAt = $retryAt ? $retryAt->format('M d, Y H:i:s') : 'Unknown';
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "🚨 API Quota Exceeded: {$this->api}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.quota-exceeded',
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
