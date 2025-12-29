<?php

namespace App\Mail;

use App\Models\Blog;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

class BlogGenerationReport extends Mailable
{
    use Queueable, SerializesModels;

    public $blog;
    public $error;
    public $logs;
    public $isDuplicate;
    public $generatedAt;
    public $status;
    public $fallbackMode;

    /**
     * Create a new message instance.
     */
    public function __construct(?Blog $blog, ?\Exception $error, array $logs = [], bool $isDuplicate = false, ?string $fallbackMode = null)
    {
        $this->blog = $blog;
        $this->error = $error;
        $this->logs = $logs;
        $this->isDuplicate = $isDuplicate;
        $this->generatedAt = now()->format('Y-m-d H:i:s');
        $this->fallbackMode = $fallbackMode;
        
        // Determine status
        if ($blog) {
            $this->status = $fallbackMode ? "Success ($fallbackMode)" : 'Success';
        } elseif ($isDuplicate) {
            $this->status = 'All Topics Duplicate';
        } else {
            $this->status = 'Failed';
        }
    }

    /**
     * Get the message envelope.
     */
    public function envelope(): Envelope
    {
        $title = $this->blog ? $this->blog->title : 'Generation Failed';
        $statusIcon = $this->status === 'Success' ? '✅' : '❌';
        
        return new Envelope(
            subject: "Blog Generation [{$this->status}]: {$title} - {$this->generatedAt}",
        );
    }

    /**
     * Get the message content definition.
     */
    public function content(): Content
    {
        return new Content(
            view: 'mail.blog-report',
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
