<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class BlogGenerationFailed extends Mailable
{
    use Queueable, SerializesModels;

    public $errorMessage;
    public $categoryName;

    public function __construct($errorMessage, $categoryName)
    {
        $this->errorMessage = $errorMessage;
        $this->categoryName = $categoryName;
    }

    public function build()
    {
        return $this->subject('Blog Generation Failure Alert')
                    ->view('emails.blog_failed');
    }
}
