<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;
use Illuminate\Support\Facades\Config;

class AdminAlert extends Mailable
{
    use Queueable, SerializesModels;

    protected $title = "";
    protected $content = "";

    /**
     * Create a new message instance.
     *
     * @return void
     */
    public function __construct($title, $content)
    {
        $this->title = $title;
        $this->content = $content;
    }

    /**
     * Build the message.
     *
     * @return $this
     */
    public function build()
    {
        // dd(config('app.site_url'));
        return $this->view('emails.admin_alert_email')
                    ->subject($this->title ?? "Notification")
                    ->with([
                        'title' => $this->title,
                        'content' => $this->content
                    ]);
    }
}
