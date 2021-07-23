<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class UserConfirmEmail extends Mailable
{
    use Queueable, SerializesModels;

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($title, $content, $url , $action)
    {
        $this->title = $title;
        $this->content = $content;
        $this->url = $url;
        $this->action = $action;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        return $this->view('emails.confirm_email')
        ->subject($this->title ?? "Notification")
        ->with([
            'title' => $this->title,
            'content' => $this->content,
            'url' => $this->url,
            'action' => $this->action,
        ]);
    }
}
