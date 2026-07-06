<?php

use App\DataObjects\PersonalData\CreatePersonalData;
use App\Enums\PersonalDataTypeEnum;
use App\Models\PersonalData;
use App\Models\User;
use App\Services\PersonalDataService;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

beforeEach(function () {
    $this->service = app(PersonalDataService::class);
    $this->user = User::factory()->create();
});

it('creates an individual card for an owner', function () {
    $card = $this->service->createFor($this->user, new CreatePersonalData(
        type: PersonalDataTypeEnum::Individual,
        firstName: 'Ada',
        lastName: 'Lovelace',
    ));

    expect($card->personable_id)->toBe($this->user->id)
        ->and($card->full_name)->toBe('Ada Lovelace');
});

it('creates a company card for an owner', function () {
    $card = $this->service->createFor($this->user, new CreatePersonalData(
        type: PersonalDataTypeEnum::Company,
        companyName: 'Engines Ltd',
        vatNumber: '12345678901',
    ));

    expect($card->type)->toBe(PersonalDataTypeEnum::Company)
        ->and($card->full_name)->toBe('Engines Ltd');
});

it('upsertFor updates the existing card instead of creating a second one', function () {
    $this->service->createFor($this->user, new CreatePersonalData(
        type: PersonalDataTypeEnum::Individual, firstName: 'Ada', lastName: 'L',
    ));

    $updated = $this->service->upsertFor($this->user, new CreatePersonalData(
        type: PersonalDataTypeEnum::Individual, firstName: 'Grace', lastName: 'Hopper',
    ));

    expect($updated->full_name)->toBe('Grace Hopper')
        ->and(PersonalData::where('personable_id', $this->user->id)->count())->toBe(1);
});

it('upsertFor creates the card when the owner has none', function () {
    $card = $this->service->upsertFor($this->user, new CreatePersonalData(
        type: PersonalDataTypeEnum::Individual, firstName: 'Ada', lastName: 'L',
    ));

    expect($card->exists)->toBeTrue()
        ->and($this->user->personalData->id)->toBe($card->id);
});
