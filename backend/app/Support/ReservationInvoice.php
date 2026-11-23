<?php

namespace App\Support;

use App\Models\Maksajums;
use App\Models\Rezervacija;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\DB;

class ReservationInvoice
{
    public static function archive(Rezervacija $reservation, Maksajums $payment): void
    {
        $archive = DB::table('rezervaciju_rekinu_arhivs');
        if ($archive->where('maksajums_id', $payment->getKey())->exists()) {
            return;
        }

        // Both participants may delete their accounts at the same time. Keep the first copy.
        $archive->insertOrIgnore([
            'maksajums_id' => $payment->getKey(),
            'saturs' => Crypt::encryptString(json_encode(self::details($reservation), JSON_THROW_ON_ERROR)),
        ]);
        if (! $archive->where('maksajums_id', $payment->getKey())->lockForUpdate()->first()) {
            throw new \RuntimeException('The invoice archive could not be saved.');
        }
    }

    public static function viewData(Rezervacija $reservation, Maksajums $payment, string $recipientType): array
    {
        $archived = DB::table('rezervaciju_rekinu_arhivs')->where('maksajums_id', $payment->getKey())->value('saturs');

        return [
            'recipientType' => $recipientType,
            'invoiceNumber' => $payment->rekins,
            'transactionNumber' => $payment->tranzakcijas_numurs,
            'amount' => number_format((float) $payment->summa, 2, '.', ' '),
            'paidAt' => $reservation->maksajuma_datums?->format('Y-m-d') ?? $payment->created_at?->format('Y-m-d'),
            'reservationId' => $reservation->getKey(),
            'reservedAt' => $reservation->izveides_datums?->format('Y-m-d H:i'),
            'startAt' => $reservation->sakuma_laiks->format('Y-m-d H:i'),
            'endAt' => $reservation->beigu_laiks->format('Y-m-d H:i'),
        ] + ($archived === null
            ? self::details($reservation)
            : json_decode(Crypt::decryptString($archived), true, flags: JSON_THROW_ON_ERROR));
    }

    private static function details(Rezervacija $reservation): array
    {
        $vehicle = $reservation->transportlidzeklis;
        $client = $reservation->klients?->persona;
        $provider = $vehicle?->sniedzejs?->persona;
        $snapshot = $reservation->transporta_snapshot ?? $vehicle?->bookingSnapshot();

        return [
            'vehicleName' => trim(($snapshot['marka'] ?? '').' '.($snapshot['modelis'] ?? '')),
            'vehicleType' => $snapshot['veids'] ?? null,
            'vehicleRegistrationNumber' => $vehicle?->registracijas_numurs,
            'clientName' => trim(($client?->vards ?? '').' '.($client?->uzvards ?? '')),
            'clientEmail' => $client?->epasts,
            'providerName' => trim(($provider?->vards ?? '').' '.($provider?->uzvards ?? '')),
            'providerEmail' => $provider?->epasts,
        ];
    }
}
