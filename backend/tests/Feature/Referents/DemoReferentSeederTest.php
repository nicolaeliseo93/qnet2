<?php

use App\Models\Address;
use App\Models\Contact;
use App\Models\PersonalData;
use App\Models\Referent;
use Database\Seeders\DemoReferentSeeder;
use Database\Seeders\DemoReferentTypeSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

it('seeds referents each with a complete anagraphic card, contacts and addresses', function (): void {
    $this->seed(DemoReferentTypeSeeder::class);
    $this->seed(DemoReferentSeeder::class);

    $referents = Referent::query()->with('personalData.contacts', 'personalData.addresses')->get();

    expect($referents)->not->toBeEmpty();

    $referents->each(function (Referent $referent): void {
        $card = $referent->personalData;

        expect($card)->not->toBeNull()
            ->and($referent->name)->toBe($card->full_name)
            ->and($card->contacts)->not->toBeEmpty()
            ->and($card->addresses)->not->toBeEmpty()
            ->and($card->contacts->firstWhere('is_primary', true))->not->toBeNull()
            ->and($card->addresses->firstWhere('is_primary', true))->not->toBeNull();
    });
});

it('classifies referents with a seeded type and a valid contact scope', function (): void {
    $this->seed(DemoReferentTypeSeeder::class);
    $this->seed(DemoReferentSeeder::class);

    $referents = Referent::query()->with('referentType')->get();

    expect($referents->whereNotNull('referent_type_id'))->not->toBeEmpty();

    $referents->each(function (Referent $referent): void {
        expect($referent->contact_scope->value)->toBeIn(['internal', 'external']);

        if ($referent->referent_type_id !== null) {
            expect($referent->referentType)->not->toBeNull();
        }
    });
});

it('is idempotent: re-running does not duplicate referents or orphan cards', function (): void {
    $this->seed(DemoReferentTypeSeeder::class);
    $this->seed(DemoReferentSeeder::class);

    $firstCount = Referent::query()->count();

    $this->seed(DemoReferentSeeder::class);

    expect(Referent::query()->count())->toBe($firstCount)
        ->and(PersonalData::query()->count())->toBe($firstCount)
        ->and(Contact::query()->whereMorphedTo('contactable', PersonalData::class)->count())
        ->toBe(Contact::query()->count())
        ->and(Address::query()->whereMorphedTo('addressable', PersonalData::class)->count())
        ->toBe(Address::query()->count());
});
