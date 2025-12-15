<?php

namespace App\Mail;

use App\Models\Blog;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class ThumbnailGenerationFailedMail extends Mailable
{
    use Queueable, SerializesModels;

    public $blog;
    public $errorDetails;
    public $fallback;
    public $apisAttempted;

    /**
     * Create a new message instance.
     */
    public function __construct(Blog $blog, string $errorDetails, string $fallback = 'SVG Fallback', array $apisAttempted = [])
    {
        $this->blog = $blog;
        $this->errorDetails = $errorDetails;
        $this->fallback = $fallback;
        $this->apisAttempted = $apisAttempted;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "⚠️ Thumbnail Failed: {$this->blog->title}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.thumbnail-generation-failed',
            with: [
                'blogUrl' => route('blog.show', $this->blog->slug),
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
