<?php

return [
    'requested' => 'If an eligible account exists, we’ll email you instructions to reset your password.',
    'invalidLink' => 'This password reset link is invalid or has expired. Request a new link.',
    'updated' => 'Your password has been updated. You can now sign in with your new password.',
    'passwordMixed' => 'Use at least one uppercase and one lowercase letter.',
    'passwordLength' => 'The password is too long or contains an unsupported character.',
    'mail' => [
        'subject' => 'Reset your EasyRent password',
        'greeting' => 'Hello, :name!',
        'greetingGeneric' => 'Hello!',
        'intro' => 'We received a request to reset the password for your EasyRent account.',
        'action' => 'Reset password',
        'expires' => 'This link is valid for :minutes minutes and can be used once.',
        'ignore' => 'If you didn’t request a password reset, you can ignore this email.',
    ],
];
