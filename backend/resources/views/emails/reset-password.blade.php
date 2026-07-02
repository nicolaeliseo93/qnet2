@extends('emails.layout')

@section('content')
    <h1>{{ __('Reset your password') }}</h1>

    <p>{{ __('Hello :name,', ['name' => $name]) }}</p>

    <p>{{ __('You are receiving this email because we received a password reset request for your account.') }}</p>

    <p>
        <a href="{{ $url }}" class="button" target="_blank" rel="noopener">
            {{ __('Reset password') }}
        </a>
    </p>

    <p>{{ __('This link will expire in :count minutes.', ['count' => $expireMinutes]) }}</p>

    <p>{{ __('If you did not request a password reset, no further action is required.') }}</p>

    <p class="muted">{{ __('If the button above does not work, copy and paste this URL into your browser:') }}</p>
    <p class="muted break"><a href="{{ $url }}">{{ $url }}</a></p>
@endsection
