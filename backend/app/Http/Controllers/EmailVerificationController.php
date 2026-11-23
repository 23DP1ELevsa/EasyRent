<?php

namespace App\Http\Controllers;

use App\Models\Persona;
use App\Support\EmailVerification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Throwable;

use function Illuminate\Support\defer;

class EmailVerificationController extends Controller
{
    public function confirm(Request $request, EmailVerification $verification): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'id' => ['required', 'integer', 'min:1'],
            'token' => ['required', 'string', 'regex:/\A[a-f0-9]{64}\z/'],
            'interactive' => ['sometimes', 'boolean'],
            'auto_login' => ['sometimes', 'boolean'],
        ]);
        if ($validator->fails()) {
            return $this->result('invalid_verification_link', 422);
        }

        $data = $validator->validated();
        $id = (int) $data['id'];
        try {
            $result = $verification->confirm($request, $id, $data['token'], $request->boolean('interactive'), $request->boolean('auto_login', true));
        } catch (Throwable) {
            Log::error('Email verification could not be completed.');

            return $this->result('verification_unavailable', 503);
        }
        $code = $result['code'];
        if ($code === 'email_verified') {
            $verification->clearWebSessionFor($request, $id);
        }

        return $this->result($code, match ($code) {
            'email_verified' => 200,
            'verification_interaction_required' => 409,
            'verification_link_expired' => 410,
            default => 422,
        }, $result);
    }

    public function resend(Request $request, EmailVerification $verification): JsonResponse
    {
        $validator = Validator::make($request->all(), [
            'email' => ['required', 'string', 'email', 'max:255'],
            'locale' => ['sometimes', 'required', Rule::in(['lv', 'ru', 'en'])],
        ]);
        if ($validator->fails()) {
            return $this->result('invalid_verification_request', 422, ['errors' => $validator->errors()]);
        }

        $email = Persona::normalizeEmail($validator->validated()['email']);
        $locale = app()->getLocale();
        $requestId = (string) Str::uuid();
        if ($id = $verification->pendingId($request, $email)) {
            $status = $verification->send($email, $locale, $id, $requestId);
            $details = ['retry_after' => EmailVerification::COOLDOWN];
            $outcome = match ($status) {
                'sent' => $this->result('verification_mail_sent', 200, $details),
                'unavailable' => $this->result('mail_unavailable', 503, $details),
                'rate_limited' => $this->result('verification_mail_rate_limited', 429, $details),
                default => null,
            };
            if ($outcome) {
                $outcome->header('X-Request-ID', $requestId);

                return $status === 'sent' ? $outcome : $outcome->header('Retry-After', EmailVerification::COOLDOWN);
            }
        }

        defer(fn () => $verification->send($email, $locale, requestId: $requestId));

        $response = $this->result('verification_requested', 202, ['retry_after' => EmailVerification::COOLDOWN])
            ->header('X-Request-ID', $requestId);
        $response->headers->set('Content-Length', (string) strlen($response->getContent()));

        return $response;
    }

    private function result(string $code, int $status, array $data = []): JsonResponse
    {
        return response()->json(['code' => $code, 'message' => __('email-verification.'.$code)] + $data, $status)
            ->header('Cache-Control', 'no-store');
    }
}
