<!DOCTYPE html>
<html lang="lv">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <title>EasyRent rēķins</title>
    <style>
        body {
            margin: 0;
            padding: 24px;
            background: #f5f7fb;
            font-family: Arial, sans-serif;
            color: #1f2937;
        }

        .invoice-card {
            max-width: 720px;
            margin: 0 auto;
            background: #ffffff;
            border-radius: 16px;
            padding: 32px;
            border: 1px solid #dbe3f0;
            box-sizing: border-box;
        }

        .invoice-table {
            width: 100%;
            border-collapse: collapse;
            margin: 24px 0;
            table-layout: fixed;
        }

        .invoice-label,
        .invoice-value {
            padding: 10px 12px;
            border: 1px solid #dbe3f0;
            vertical-align: top;
            word-break: break-word;
            overflow-wrap: anywhere;
        }

        .invoice-label {
            width: 38%;
            background: #f8fafc;
            font-weight: 700;
        }

        @media only screen and (max-width: 600px) {
            body {
                padding: 12px !important;
            }

            .invoice-card {
                padding: 20px 16px !important;
                border-radius: 12px !important;
            }

            .invoice-title {
                font-size: 20px !important;
                line-height: 1.25 !important;
            }

            .invoice-table,
            .invoice-table tbody,
            .invoice-table tr,
            .invoice-table td {
                display: block !important;
                width: 100% !important;
                box-sizing: border-box !important;
            }

            .invoice-table tr {
                margin-bottom: 12px;
            }

            .invoice-label,
            .invoice-value {
                padding: 10px 12px !important;
            }

            .invoice-label {
                border-bottom: 0 !important;
            }
        }
    </style>
</head>
<body style="margin:0;padding:24px;background:#f5f7fb;font-family:Arial,sans-serif;color:#1f2937;">
    <div class="invoice-card" style="max-width:720px;margin:0 auto;background:#ffffff;border-radius:16px;padding:32px;border:1px solid #dbe3f0;box-sizing:border-box;">
        <h1 class="invoice-title" style="margin-top:0;font-size:24px;line-height:1.2;">
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

        <table class="invoice-table" style="width:100%;border-collapse:collapse;margin:24px 0;table-layout:fixed;">
            <tr>
                <td class="invoice-label" style="padding:10px 12px;border:1px solid #dbe3f0;background:#f8fafc;vertical-align:top;word-break:break-word;overflow-wrap:anywhere;"><strong>Rēķina numurs</strong></td>
                <td class="invoice-value" style="padding:10px 12px;border:1px solid #dbe3f0;vertical-align:top;word-break:break-word;overflow-wrap:anywhere;">{{ $invoiceNumber }}</td>
            </tr>
            <tr>
                <td class="invoice-label" style="padding:10px 12px;border:1px solid #dbe3f0;background:#f8fafc;vertical-align:top;word-break:break-word;overflow-wrap:anywhere;"><strong>Transakcijas numurs</strong></td>
                <td class="invoice-value" style="padding:10px 12px;border:1px solid #dbe3f0;vertical-align:top;word-break:break-word;overflow-wrap:anywhere;">{{ $transactionNumber }}</td>
            </tr>
            <tr>
                <td class="invoice-label" style="padding:10px 12px;border:1px solid #dbe3f0;background:#f8fafc;vertical-align:top;word-break:break-word;overflow-wrap:anywhere;"><strong>Rezervācijas ID</strong></td>
                <td class="invoice-value" style="padding:10px 12px;border:1px solid #dbe3f0;vertical-align:top;word-break:break-word;overflow-wrap:anywhere;">{{ $reservationId }}</td>
            </tr>
            <tr>
                <td class="invoice-label" style="padding:10px 12px;border:1px solid #dbe3f0;background:#f8fafc;vertical-align:top;word-break:break-word;overflow-wrap:anywhere;"><strong>Summa</strong></td>
                <td class="invoice-value" style="padding:10px 12px;border:1px solid #dbe3f0;vertical-align:top;word-break:break-word;overflow-wrap:anywhere;">{{ $amount }} EUR</td>
            </tr>
            <tr>
                <td class="invoice-label" style="padding:10px 12px;border:1px solid #dbe3f0;background:#f8fafc;vertical-align:top;word-break:break-word;overflow-wrap:anywhere;"><strong>Apmaksas datums</strong></td>
                <td class="invoice-value" style="padding:10px 12px;border:1px solid #dbe3f0;vertical-align:top;word-break:break-word;overflow-wrap:anywhere;">{{ $paidAt }}</td>
            </tr>
            <tr>
                <td class="invoice-label" style="padding:10px 12px;border:1px solid #dbe3f0;background:#f8fafc;vertical-align:top;word-break:break-word;overflow-wrap:anywhere;"><strong>Rezervēts</strong></td>
                <td class="invoice-value" style="padding:10px 12px;border:1px solid #dbe3f0;vertical-align:top;word-break:break-word;overflow-wrap:anywhere;">{{ $reservedAt }}</td>
            </tr>
            <tr>
                <td class="invoice-label" style="padding:10px 12px;border:1px solid #dbe3f0;background:#f8fafc;vertical-align:top;word-break:break-word;overflow-wrap:anywhere;"><strong>Nomas sākums</strong></td>
                <td class="invoice-value" style="padding:10px 12px;border:1px solid #dbe3f0;vertical-align:top;word-break:break-word;overflow-wrap:anywhere;">{{ $startAt }}</td>
            </tr>
            <tr>
                <td class="invoice-label" style="padding:10px 12px;border:1px solid #dbe3f0;background:#f8fafc;vertical-align:top;word-break:break-word;overflow-wrap:anywhere;"><strong>Nomas beigas</strong></td>
                <td class="invoice-value" style="padding:10px 12px;border:1px solid #dbe3f0;vertical-align:top;word-break:break-word;overflow-wrap:anywhere;">{{ $endAt }}</td>
            </tr>
            <tr>
                <td class="invoice-label" style="padding:10px 12px;border:1px solid #dbe3f0;background:#f8fafc;vertical-align:top;word-break:break-word;overflow-wrap:anywhere;"><strong>Transportlīdzeklis</strong></td>
                <td class="invoice-value" style="padding:10px 12px;border:1px solid #dbe3f0;vertical-align:top;word-break:break-word;overflow-wrap:anywhere;">{{ $vehicleName ?: '—' }}</td>
            </tr>
            <tr>
                <td class="invoice-label" style="padding:10px 12px;border:1px solid #dbe3f0;background:#f8fafc;vertical-align:top;word-break:break-word;overflow-wrap:anywhere;"><strong>Transporta veids</strong></td>
                <td class="invoice-value" style="padding:10px 12px;border:1px solid #dbe3f0;vertical-align:top;word-break:break-word;overflow-wrap:anywhere;">{{ $vehicleType ?: '—' }}</td>
            </tr>
            <tr>
                <td class="invoice-label" style="padding:10px 12px;border:1px solid #dbe3f0;background:#f8fafc;vertical-align:top;word-break:break-word;overflow-wrap:anywhere;"><strong>Reģistrācijas numurs</strong></td>
                <td class="invoice-value" style="padding:10px 12px;border:1px solid #dbe3f0;vertical-align:top;word-break:break-word;overflow-wrap:anywhere;">{{ $vehicleRegistrationNumber ?: '—' }}</td>
            </tr>
            <tr>
                <td class="invoice-label" style="padding:10px 12px;border:1px solid #dbe3f0;background:#f8fafc;vertical-align:top;word-break:break-word;overflow-wrap:anywhere;"><strong>Klients</strong></td>
                <td class="invoice-value" style="padding:10px 12px;border:1px solid #dbe3f0;vertical-align:top;word-break:break-word;overflow-wrap:anywhere;">{{ $clientName ?: '—' }} ({{ $clientEmail ?: '—' }})</td>
            </tr>
            <tr>
                <td class="invoice-label" style="padding:10px 12px;border:1px solid #dbe3f0;background:#f8fafc;vertical-align:top;word-break:break-word;overflow-wrap:anywhere;"><strong>Pakalpojumu sniedzējs</strong></td>
                <td class="invoice-value" style="padding:10px 12px;border:1px solid #dbe3f0;vertical-align:top;word-break:break-word;overflow-wrap:anywhere;">{{ $providerName ?: '—' }} ({{ $providerEmail ?: '—' }})</td>
            </tr>
        </table>

        <p style="font-size:13px;color:#6b7280;margin-bottom:0;">Šī vēstule ir ģenerēta automātiski no EasyRent sistēmas.</p>
    </div>
</body>
</html>