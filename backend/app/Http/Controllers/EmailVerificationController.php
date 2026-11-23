<?php

namespace App\Http\Controllers;

use App\Support\EmailVerification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Validator;
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
        ]);
        if ($validator->fails()) {
            return $this->result('invalid_verification_link', 422);
        }

        $data = $validator->validated();
        $id = (int) $data['id'];
        try {
            $code = $verification->confirm($id, $data['token']);
        } catch (Throwable) {
            Log::error('Email verification could not be completed.');

            return $this->result('verification_unavailable', 503);
        }
        if ($code === 'email_verified') {
            $verification->clearWebSessionFor($request, $id);
        }

        return $this->result($code, match ($code) {
            'email_verified' => 200,
            'verification_link_expired' => 410,
            default => 422,
        });
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

        $email = mb_strtolower($validator->validated()['email']);
        $locale = app()->getLocale();
        defer(fn () => $verification->send($email, $locale));

        $response = $this->result('verification_requested', 202, ['retry_after' => EmailVerification::COOLDOWN]);
        $response->headers->set('Content-Length', (string) strlen($response->getContent()));

        return $response;
    }

    private function result(string $code, int $status, array $data = []): JsonResponse
    {
        return response()->json(['code' => $code, 'message' => __('email-verification.'.$code)] + $data, $status)
            ->header('Cache-Control', 'no-store');
    }
}
