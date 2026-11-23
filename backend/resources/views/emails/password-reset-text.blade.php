EasyRent

{!! filled($name) ? __('password-reset.mail.greeting', ['name' => $name]) : __('password-reset.mail.greetingGeneric') !!}

{!! __('password-reset.mail.intro') !!}

{!! __('password-reset.mail.action') !!}
{!! $resetUrl !!}

{!! __('password-reset.mail.expires', ['minutes' => $minutes]) !!}

{!! __('password-reset.mail.ignore') !!}
