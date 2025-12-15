<?php

namespace App\Mail;

use App\Models\Blog;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BlogGeneratedSuccessMail extends Mailable
{
    use Queueable, SerializesModels;

    public $blog;
    public $generationTime;
    public $apisUsed;
    public $keywords;

    /**
     * Create a new message instance.
     */
    public function __construct(Blog $blog, $generationTime = null, array $apisUsed = [], array $keywords = [])
    {
        $this->blog = $blog;
        $this->generationTime = $generationTime ?? 'N/A';
        $this->apisUsed = $apisUsed;
        $this->keywords = $keywords;
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        return new Envelope(
            subject: "✅ Blog Generated: {$this->blog->title}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'emails.blog-generated-success',
            with: [
                'blogUrl' => route('blog.show', $this->blog->slug),
                'thumbnailUrl' => $this->blog->thumbnail_url,
                'wordCount' => str_word_count(strip_tags($this->blog->content)),
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
