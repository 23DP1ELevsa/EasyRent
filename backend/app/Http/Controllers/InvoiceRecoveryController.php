<?php

namespace App\Http\Controllers;

use App\Mail\ReservationInvoiceMail;
use App\Models\Persona;
use App\Models\Rezervacija;
use App\Support\AccountMail;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Mail;
use Throwable;

use function Illuminate\Support\defer;

class InvoiceRecoveryController extends Controller
{
    public function send(Request $request): JsonResponse
    {
        $data = $request->validate(['booking_id' => ['required', 'integer', 'min:1'], 'email' => ['required', 'email', 'max:255']]);
        $email = Persona::normalizeEmail($data['email']);
        $locale = app()->getLocale();
        defer(function () use ($data, $email, $locale) {
            try {
                $this->deliver((int) $data['booking_id'], $email, $locale);
            } catch (Throwable) {
                AccountMail::logDelivery('invoice_recovery', $email, null, error: 'send_failed');
            }
        });

        $response = response()->json(['message' => __('admin.invoice_requested')], 202)->header('Cache-Control', 'no-store');
        $response->headers->set('Content-Length', (string) strlen($response->getContent()));

        return $response;
    }

    private function deliver(int $bookingId, string $email, string $locale): void
    {
        if (! AccountMail::available()) {
            return;
        }

        $account = Persona::forEmail($email)->whereNotNull('email_verified_at')->first();
        if (! $account) {
            return;
        }

        $bookings = match ($account->loma) {
            'klients' => Rezervacija::whereIn('klients_id', $account->klients()->select('klients_id')),
            'pakalpojumu_sniedzejs' => Rezervacija::whereHas('transportlidzeklis.sniedzejs', fn ($query) => $query->where('persona_id', $account->getKey())),
            default => null,
        };
        $booking = $bookings?->where('apmaksas_statuss', 'apmaksata')->find($bookingId);
        $payment = $booking?->maksajumi()->where('statuss', 'apstiprinats')->whereNotNull('rekins')->first();
        if (! $payment || ! Cache::add('invoice-recovery:'.$account->getKey().':'.$bookingId, true, now()->addMinutes(15))) {
            return;
        }

        $party = $account->loma === 'klients' ? 'client' : 'provider';
        Mail::to($account->epasts)->locale($locale)->send(new ReservationInvoiceMail($booking, $payment, $party));
    }
}
