<?php

use App\Enums\ContactTypeEnum;
use App\Http\Requests\PersonalData\StoreContactRequest;
use Illuminate\Support\Facades\Validator;

/**
 * Per-type validation of the contact `value` lives on
 * ContactTypeEnum::valueRules() and is wired through StoreContactRequest. We
 * validate the rules directly (no HTTP route is exposed yet — data layer only).
 */
function validateContact(array $payload): Illuminate\Validation\Validator
{
    $request = StoreContactRequest::create('/', 'POST', $payload);

    return Validator::make($payload, $request->rules());
}

it('accepts a valid email for an email contact', function () {
    expect(validateContact([
        'type' => ContactTypeEnum::Email->value,
        'value' => 'ada@example.com',
    ])->passes())->toBeTrue();
});

it('rejects an invalid email for an email contact', function () {
    expect(validateContact([
        'type' => ContactTypeEnum::Email->value,
        'value' => 'not-an-email',
    ])->fails())->toBeTrue();
});

it('accepts a valid email for a PEC contact', function () {
    expect(validateContact([
        'type' => ContactTypeEnum::Pec->value,
        'value' => 'office@pec.example.com',
    ])->passes())->toBeTrue();
});

it('accepts a valid url for a website contact and rejects a non-url', function () {
    expect(validateContact([
        'type' => ContactTypeEnum::Website->value,
        'value' => 'https://example.com',
    ])->passes())->toBeTrue();

    expect(validateContact([
        'type' => ContactTypeEnum::Website->value,
        'value' => 'definitely not a url',
    ])->fails())->toBeTrue();
});

it('accepts a phone-shaped value for a phone contact and rejects letters', function () {
    expect(validateContact([
        'type' => ContactTypeEnum::Phone->value,
        'value' => '+39 333 123 4567',
    ])->passes())->toBeTrue();

    expect(validateContact([
        'type' => ContactTypeEnum::Phone->value,
        'value' => 'call-me-maybe',
    ])->fails())->toBeTrue();
});

it('rejects an unknown contact type', function () {
    expect(validateContact([
        'type' => 'carrier-pigeon',
        'value' => 'whatever',
    ])->fails())->toBeTrue();
});
