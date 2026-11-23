<?php

namespace App\Mail;

use App\Models\Maksajums;
use App\Models\Rezervacija;
use App\Support\ReservationInvoice;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Queue\SerializesModels;

class ReservationInvoiceMail extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(
        public Rezervacija $rezervacija,
        public Maksajums $maksajums,
        public string $recipientType,
    ) {
    }

    public function build()
    {
        $replyToAddress = config('mail.reply_to.address');
        $replyToName = config('mail.reply_to.name');

        $mail = $this
            ->subject($this->recipientType === 'provider'
                ? __('invoice.providerSubject')
                : __('invoice.clientSubject'))
            ->view('emails.reservation-invoice')
            ->with(ReservationInvoice::viewData($this->rezervacija, $this->maksajums, $this->recipientType));

        if ($replyToAddress) {
            $mail->replyTo($replyToAddress, $replyToName);
        }

        return $mail;
    }
}
