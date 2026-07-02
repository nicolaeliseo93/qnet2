<?php

use App\Enums\PersonalDataTypeEnum;
use App\Models\Address;
use App\Models\Contact;
use App\Models\PersonalData;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('creates a valid individual card from the individual state', function () {
    $card = PersonalData::factory()->individual()->create();

    expect($card->type)->toBe(PersonalDataTypeEnum::Individual)
        ->and($card->first_name)->not->toBeNull()
        ->and($card->last_name)->not->toBeNull()
        ->and($card->company_name)->toBeNull();
});

it('creates a card of a random type from the factory default', function () {
    $types = collect(range(1, 30))
        ->map(fn () => PersonalData::factory()->create()->type)
        ->unique();

    // Over enough draws the default yields both shapes, and every card is valid.
    expect($types)->toContain(PersonalDataTypeEnum::Individual)
        ->and($types)->toContain(PersonalDataTypeEnum::Company);
});

it('creates a valid company card from the company state', function () {
    $card = PersonalData::factory()->company()->create();

    expect($card->type)->toBe(PersonalDataTypeEnum::Company)
        ->and($card->company_name)->not->toBeNull()
        ->and($card->vat_number)->not->toBeNull();
});

it('full_name accessor returns the trimmed person name for an individual', function () {
    $card = PersonalData::factory()->individual()->create([
        'first_name' => 'Ada',
        'last_name' => 'Lovelace',
    ]);

    expect($card->full_name)->toBe('Ada Lovelace');
});

it('full_name accessor returns the company name for a company', function () {
    $card = PersonalData::factory()->company()->create([
        'company_name' => 'Analytical Engines Ltd',
    ]);

    expect($card->full_name)->toBe('Analytical Engines Ltd');
});

it('ceo accessor returns the contact person for a company and null for an individual', function () {
    $company = PersonalData::factory()->company()->create([
        'first_name' => 'Grace',
        'last_name' => 'Hopper',
    ]);
    $individual = PersonalData::factory()->individual()->create();

    expect($company->ceo)->toBe('Grace Hopper')
        ->and($individual->ceo)->toBeNull();
});

it('belongs to an owner through the personable morph', function () {
    $user = User::factory()->create();
    $card = PersonalData::factory()->for($user, 'personable')->create();

    expect($card->personable)->toBeInstanceOf(User::class)
        ->and($card->personable->id)->toBe($user->id);
});

it('owns contacts and addresses through morph relations', function () {
    $card = PersonalData::factory()->create();
    Contact::factory()->count(2)->for($card, 'contactable')->create();
    Address::factory()->count(3)->for($card, 'addressable')->create();

    expect($card->contacts)->toHaveCount(2)
        ->and($card->addresses)->toHaveCount(3);
});
