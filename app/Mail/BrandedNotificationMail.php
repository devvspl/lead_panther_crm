<?php

namespace App\Mail;

use Illuminate\Mail\Mailable;

class BrandedNotificationMail extends Mailable
{
    public function __construct(public string $heading, public string $messageBody, public ?string $actionUrl = null) {}

    public function build(): self
    {
        return $this->subject($this->heading)->view('emails.notification');
    }
}
