<!DOCTYPE html>
<html lang="lv">
<head>
    <meta charset="UTF-8">
    <title>EasyRent rēķins</title>
</head>
<body style="margin:0;padding:24px;background:#f5f7fb;font-family:Arial,sans-serif;color:#1f2937;">
    <div style="max-width:720px;margin:0 auto;background:#ffffff;border-radius:16px;padding:32px;border:1px solid #dbe3f0;">
        <h1 style="margin-top:0;font-size:24px;">
            @if ($recipientType === 'provider')
                Jauna apmaksāta rezervācija
            @else
                Jūsu rezervācijas rēķins
            @endif
        </h1>

        <p style="font-size:15px;line-height:1.6;">
            @if ($recipientType === 'provider')
                Pakalpojums ir apmaksāts. Zemāk ir rēķina un rezervācijas detaļas.
            @else
                Paldies par apmaksu. Zemāk ir jūsu rēķina un rezervācijas detaļas.
            @endif
        </p>

        <table style="width:100%;border-collapse:collapse;margin:24px 0;">
            <tr>
                <td style="padding:10px 12px;border:1px solid #dbe3f0;background:#f8fafc;"><strong>Rēķina numurs</strong></td>
                <td style="padding:10px 12px;border:1px solid #dbe3f0;">{{ $invoiceNumber }}</td>
            </tr>
            <tr>
                <td style="padding:10px 12px;border:1px solid #dbe3f0;background:#f8fafc;"><strong>Transakcijas numurs</strong></td>
                <td style="padding:10px 12px;border:1px solid #dbe3f0;">{{ $transactionNumber }}</td>
            </tr>
            <tr>
                <td style="padding:10px 12px;border:1px solid #dbe3f0;background:#f8fafc;"><strong>Rezervācijas ID</strong></td>
                <td style="padding:10px 12px;border:1px solid #dbe3f0;">{{ $reservationId }}</td>
            </tr>
            <tr>
                <td style="padding:10px 12px;border:1px solid #dbe3f0;background:#f8fafc;"><strong>Summa</strong></td>
                <td style="padding:10px 12px;border:1px solid #dbe3f0;">{{ $amount }} EUR</td>
            </tr>
            <tr>
                <td style="padding:10px 12px;border:1px solid #dbe3f0;background:#f8fafc;"><strong>Apmaksas datums</strong></td>
                <td style="padding:10px 12px;border:1px solid #dbe3f0;">{{ $paidAt }}</td>
            </tr>
            <tr>
                <td style="padding:10px 12px;border:1px solid #dbe3f0;background:#f8fafc;"><strong>Rezervēts</strong></td>
                <td style="padding:10px 12px;border:1px solid #dbe3f0;">{{ $reservedAt }}</td>
            </tr>
            <tr>
                <td style="padding:10px 12px;border:1px solid #dbe3f0;background:#f8fafc;"><strong>Nomas sākums</strong></td>
                <td style="padding:10px 12px;border:1px solid #dbe3f0;">{{ $startAt }}</td>
            </tr>
            <tr>
                <td style="padding:10px 12px;border:1px solid #dbe3f0;background:#f8fafc;"><strong>Nomas beigas</strong></td>
                <td style="padding:10px 12px;border:1px solid #dbe3f0;">{{ $endAt }}</td>
            </tr>
            <tr>
                <td style="padding:10px 12px;border:1px solid #dbe3f0;background:#f8fafc;"><strong>Transportlīdzeklis</strong></td>
                <td style="padding:10px 12px;border:1px solid #dbe3f0;">{{ $vehicleName ?: '—' }}</td>
            </tr>
            <tr>
                <td style="padding:10px 12px;border:1px solid #dbe3f0;background:#f8fafc;"><strong>Transporta veids</strong></td>
                <td style="padding:10px 12px;border:1px solid #dbe3f0;">{{ $vehicleType ?: '—' }}</td>
            </tr>
            <tr>
                <td style="padding:10px 12px;border:1px solid #dbe3f0;background:#f8fafc;"><strong>Reģistrācijas numurs</strong></td>
                <td style="padding:10px 12px;border:1px solid #dbe3f0;">{{ $vehicleRegistrationNumber ?: '—' }}</td>
            </tr>
            <tr>
                <td style="padding:10px 12px;border:1px solid #dbe3f0;background:#f8fafc;"><strong>Klients</strong></td>
                <td style="padding:10px 12px;border:1px solid #dbe3f0;">{{ $clientName ?: '—' }} ({{ $clientEmail ?: '—' }})</td>
            </tr>
            <tr>
                <td style="padding:10px 12px;border:1px solid #dbe3f0;background:#f8fafc;"><strong>Pakalpojumu sniedzējs</strong></td>
                <td style="padding:10px 12px;border:1px solid #dbe3f0;">{{ $providerName ?: '—' }} ({{ $providerEmail ?: '—' }})</td>
            </tr>
        </table>

        <p style="font-size:13px;color:#6b7280;margin-bottom:0;">Šī vēstule ir ģenerēta automātiski no EasyRent sistēmas.</p>
    </div>
</body>
</html>