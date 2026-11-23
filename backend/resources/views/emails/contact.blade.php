<h2>{{ __('app.contactTitle') }}</h2>

<p><strong>{{ __('app.contactName') }}:</strong> {{ $name }}</p>
<p><strong>{{ __('app.contactEmail') }}:</strong> {{ $email }}</p>

<p><strong>{{ __('app.contactComment') }}:</strong></p>
<p>{!! nl2br(e($comment)) !!}</p>
