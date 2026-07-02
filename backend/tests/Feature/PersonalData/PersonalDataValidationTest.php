<?php

use App\Enums\PersonalDataTypeEnum;
use App\Http\Requests\PersonalData\StorePersonalDataRequest;
use Illuminate\Support\Facades\Validator;

function validatePersonalData(array $payload): Illuminate\Validation\Validator
{
    $request = StorePersonalDataRequest::create('/', 'POST', $payload);

    return Validator::make($payload, $request->rules());
}

it('requires first and last name for an individual', function () {
    expect(validatePersonalData([
        'type' => PersonalDataTypeEnum::Individual->value,
    ])->fails())->toBeTrue();

    expect(validatePersonalData([
        'type' => PersonalDataTypeEnum::Individual->value,
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
    ])->passes())->toBeTrue();
});

it('requires a company name for a company', function () {
    expect(validatePersonalData([
        'type' => PersonalDataTypeEnum::Company->value,
    ])->fails())->toBeTrue();

    expect(validatePersonalData([
        'type' => PersonalDataTypeEnum::Company->value,
        'company_name' => 'Engines Ltd',
    ])->passes())->toBeTrue();
});

it('rejects an unknown type and a future birth date', function () {
    expect(validatePersonalData(['type' => 'alien'])->fails())->toBeTrue();

    expect(validatePersonalData([
        'type' => PersonalDataTypeEnum::Individual->value,
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
        'birth_date' => now()->addYear()->toDateString(),
    ])->fails())->toBeTrue();
});
