<?php

namespace App\Mail;

use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ContactMessageMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public array $data) {}

    public function build()
    {
        return $this
            ->subject('EasyRent: Jauns ziņojums no kontaktformas')
            ->replyTo($this->data['email'], $this->data['name']) // ATBILDES IES UZ LIETOTĀJU
            ->view('emails.contact')
            ->with($this->data);
    }
}

