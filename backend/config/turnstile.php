<?php

return [
    'enabled' => env('TURNSTILE_ENABLED', false),
    'secret_key' => env('TURNSTILE_SECRET_KEY'),
    'allowed_hostnames' => env('TURNSTILE_ALLOWED_HOSTNAMES', 'easyrent.vip'),
    'login_attempts' => (int) env('TURNSTILE_LOGIN_ATTEMPTS', 3),
];
