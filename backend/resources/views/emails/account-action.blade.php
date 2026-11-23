<!DOCTYPE html>
<html lang="{{ $locale }}">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $subject }}</title>
</head>
<body style="margin:0;background:#f3f7f5;color:#172329;font-family:Arial,sans-serif">
    <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="background:#f3f7f5">
        <tr><td align="center" style="padding:24px 16px">
            <table role="presentation" width="100%" cellspacing="0" cellpadding="0" style="max-width:560px;background:#fff;border:1px solid #dce8e2;border-radius:16px">
                <tr><td style="padding:24px 24px 0;color:#006b43;font-size:24px;font-weight:bold">
                    EasyRent
                </td></tr>
                <tr><td style="padding:16px 24px 24px;font-size:16px;line-height:1.55">
                    <h1 style="margin:0 0 16px;font-size:24px;line-height:1.25">{{ $subject }}</h1>
                    <p style="margin:0 0 8px">{{ $greeting }}</p>
                    <p style="margin:0 0 20px">{{ $intro }}</p>
                    <table role="presentation" cellspacing="0" cellpadding="0">
                        <tr><td bgcolor="#006b43" style="border-radius:10px">
                            <a href="{{ $actionUrl }}" style="display:inline-block;padding:13px 22px;color:#fff;font-weight:bold;text-decoration:none">{{ $action }}</a>
                        </td></tr>
                    </table>
                    <p style="margin:20px 0 8px;color:#40514a">{{ $expires }}</p>
                    <p style="margin:0;color:#40514a">{{ $ignore }}</p>
                </td></tr>
            </table>
        </td></tr>
    </table>
</body>
</html>
