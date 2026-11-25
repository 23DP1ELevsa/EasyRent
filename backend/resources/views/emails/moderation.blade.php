<!doctype html>
<html lang="{{ $locale }}">
<head><meta charset="utf-8"><meta name="viewport" content="width=device-width, initial-scale=1"><title>{{ $subject }}</title></head>
<body style="margin:0;background:#f4f6fa;color:#182235;font:16px/1.6 Arial,sans-serif">
<main style="max-width:540px;margin:24px auto;padding:28px;background:#fff;border-radius:12px">
    <p style="font-weight:bold">EasyRent</p>
    <h1 style="font-size:24px;line-height:1.25">{{ $subject }}</h1>
    <p style="white-space:pre-wrap">{{ $reason }}</p>
    <p>{{ $term }}</p>
    <p><a href="{{ $supportUrl }}">{{ $support }}</a></p>
</main>
</body>
</html>
