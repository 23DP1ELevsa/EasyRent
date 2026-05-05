<?php

namespace App\Mail;

use App\Models\Maksajums;
use App\Models\Rezervacija;
use Carbon\Carbon;
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
        $vehicle = $this->rezervacija->transportlidzeklis;
        $clientPersona = $this->rezervacija->klients?->persona;
        $providerPersona = $vehicle?->sniedzejs?->persona;
        $replyToAddress = config('mail.reply_to.address');
        $replyToName = config('mail.reply_to.name');

        $mail = $this
            ->subject($this->recipientType === 'provider'
                ? 'EasyRent: jauna apmaksāta rezervācija un rēķins'
                : 'EasyRent: jūsu rezervācijas rēķins')
            ->view('emails.reservation-invoice')
            ->with([
                'recipientType' => $this->recipientType,
                'invoiceNumber' => $this->maksajums->rekins,
                'transactionNumber' => $this->maksajums->tranzakcijas_numurs,
                'amount' => number_format((float) $this->maksajums->summa, 2, '.', ' '),
                'paidAt' => Carbon::parse($this->rezervacija->maksajuma_datums ?? now())->format('Y-m-d'),
                'reservationId' => $this->rezervacija->rezervacija_id,
                'reservedAt' => Carbon::parse($this->rezervacija->izveides_datums ?? now())->format('Y-m-d H:i'),
                'startAt' => Carbon::parse($this->rezervacija->sakuma_laiks)->format('Y-m-d H:i'),
                'endAt' => Carbon::parse($this->rezervacija->beigu_laiks)->format('Y-m-d H:i'),
                'vehicleName' => trim(implode(' ', array_filter([$vehicle?->marka, $vehicle?->modelis]))),
                'vehicleType' => $vehicle?->veids?->nosaukums,
                'vehicleRegistrationNumber' => $vehicle?->registracijas_numurs,
                'clientName' => trim(($clientPersona?->vards ?? '') . ' ' . ($clientPersona?->uzvards ?? '')),
                'clientEmail' => $clientPersona?->epasts,
                'providerName' => trim(($providerPersona?->vards ?? '') . ' ' . ($providerPersona?->uzvards ?? '')),
                'providerEmail' => $providerPersona?->epasts,
            ]);

        if ($replyToAddress) {
            $mail->replyTo($replyToAddress, $replyToName);
        }

        return $mail;
    }
}