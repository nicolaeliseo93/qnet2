<?php

use App\Models\User;
use App\Notifications\ResetPasswordNotification;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\App;

uses(RefreshDatabase::class);

it('exposes the user preferred locale to the framework', function () {
    $user = User::factory()->create(['locale' => 'it']);

    expect($user->preferredLocale())->toBe('it');
});

it('renders the reset email in english by default', function () {
    App::setLocale('en');
    $user = User::factory()->make(['locale' => 'en']);

    $mail = (new ResetPasswordNotification('token'))->toMail($user);

    expect($mail->subject)->toBe('Reset your password');
});

it('renders the reset email in italian when the locale is italian', function () {
    App::setLocale('it');
    $user = User::factory()->make(['locale' => 'it']);

    $mail = (new ResetPasswordNotification('token'))->toMail($user);

    expect($mail->subject)->toBe('Reimposta la password');
});

it('builds a reset link that points to the frontend', function () {
    $user = User::factory()->make(['email' => 'jane@example.com']);

    $mail = (new ResetPasswordNotification('the-token'))->toMail($user);

    expect($mail->viewData['url'])
        ->toContain('/reset-password?')
        ->toContain('token=the-token')
        ->toContain('email=jane%40example.com');
});

it('translates a framework validation message to italian', function () {
    App::setLocale('it');

    expect(__('validation.required', ['attribute' => 'email']))
        ->toBe('Il campo email è obbligatorio.');
});
