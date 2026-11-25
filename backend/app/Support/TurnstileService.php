<?php

namespace App\Support;

use DateTimeImmutable;
use Illuminate\Http\Client\ConnectionException;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Http;

class TurnstileService
{
    public const SITEVERIFY = 'https://challenges.cloudflare.com/turnstile/v0/siteverify';

    private const VALID_SECONDS = 300;

    private const CLOCK_SKEW_SECONDS = 30;

    public function enabled(): bool
    {
        return (bool) config('turnstile.enabled');
    }

    public function verify(Request $request, string $action): void
    {
        if (! $this->enabled()) {
            return;
        }

        $secret = trim((string) config('turnstile.secret_key'));
        $hostnames = array_filter(array_map('trim', explode(',', strtolower((string) config('turnstile.allowed_hostnames')))));
        $testKey = in_array($secret, [
            '1x0000000000000000000000000000000AA',
            '2x0000000000000000000000000000000AA',
            '3x0000000000000000000000000000000AA',
        ], true);
        if ($secret === '' || ($testKey ? ! app()->environment('local', 'testing') : $hostnames === [])) {
            $this->reject('captcha_configuration', 503);
        }

        $body = $request->isJson() ? $request->json()->all() : $request->request->all();
        $token = $body['turnstile_token'] ?? null;
        if ($token === null || $token === '') {
            $this->requireChallenge();
        }
        if (! is_string($token) || strlen($token) > 2048) {
            $this->reject('captcha_invalid');
        }

        $cacheKey = 'turnstile-used:'.hash('sha256', $token);
        if (! $testKey && Cache::has($cacheKey)) {
            $this->reject('captcha_expired');
        }

        try {
            // The peer and forwarding headers depend on the deployment's proxy configuration.
            $response = Http::asForm()->acceptJson()->withoutRedirecting()->connectTimeout(2)->timeout(4)
                ->post(self::SITEVERIFY, ['secret' => $secret, 'response' => $token]);
        } catch (ConnectionException) {
            $this->reject('captcha_unavailable', 503);
        }

        $result = $response->json();
        if (! $response->successful() || ! is_bool($result['success'] ?? null)) {
            $this->reject('captcha_unavailable', 503);
        }
        if (! $result['success']) {
            $errors = (array) ($result['error-codes'] ?? []);
            if (array_intersect($errors, ['missing-input-secret', 'invalid-input-secret'])) {
                $this->reject('captcha_configuration', 503);
            }
            if (in_array('internal-error', $errors, true)) {
                $this->reject('captcha_unavailable', 503);
            }
            $this->reject(in_array('timeout-or-duplicate', $errors, true) ? 'captcha_expired' : 'captcha_invalid');
        }

        if ($testKey) {
            // Official dummy responses omit action and reuse the same token across widgets.
            return;
        }
        if (($result['action'] ?? null) !== $action || ! in_array($result['hostname'] ?? null, $hostnames, true)) {
            $this->reject('captcha_invalid');
        }

        $timestamp = $result['challenge_ts'] ?? null;
        if (! is_string($timestamp) || ! preg_match('/^\d{4}-\d{2}-\d{2}T\d{2}:\d{2}:\d{2}(?:\.\d{1,6})?(?:Z|[+-]\d{2}:\d{2})$/D', $timestamp)) {
            $this->reject('captcha_invalid');
        }
        $issuedAt = date_create_immutable($timestamp);
        $now = now()->getTimestamp();
        if (! $issuedAt || DateTimeImmutable::getLastErrors() !== false || $issuedAt->getTimestamp() > $now + self::CLOCK_SKEW_SECONDS) {
            $this->reject('captcha_invalid');
        }
        if ($issuedAt->getTimestamp() <= $now - self::VALID_SECONDS
            || ! Cache::add($cacheKey, true, self::VALID_SECONDS + self::CLOCK_SKEW_SECONDS)) {
            $this->reject('captcha_expired');
        }
    }

    public function requireChallenge(): never
    {
        $this->reject('captcha_required');
    }

    private function reject(string $code, int $status = 422): never
    {
        abort(response()->json(['code' => $code, 'message' => __('turnstile.'.$code)], $status)
            ->header('Cache-Control', 'no-store'));
    }
}
