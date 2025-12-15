<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BlogGenerationFailedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $topic;
    public $errorMessage;
    public $attemptNumber;
    public $trace;
    public $category;
    public $apisAttempted;
    public $maxAttempts;

    /**
     * Create a new message instance.
     */
    public function __construct(string $topic, string $errorMessage, int $attemptNumber = 1, string $trace = '', string $category = 'N/A', array $apisAttempted = [], int $maxAttempts = 5)
    {
        $this->topic = $topic;
        $this->errorMessage = $errorMessage;
        $this->attemptNumber = $attemptNumber;
        $this->trace = $trace;
        $this->category = $category;
        $this->apisAttempted = $apisAttempted;
        $this->maxAttempts = $maxAttempts;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "❌ Blog Generation Failed: {$this->topic}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.blog-generation-failed',
            with: [
                'failedAt' => now()->format('M d, Y H:i:s'),
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
