<?php

use App\Models\Address;
use App\Models\Contact;
use App\Models\PersonalData;
use App\Models\Registry;
use App\Models\User;
use Database\Seeders\DemoReferentSeeder;
use Database\Seeders\DemoReferentTypeSeeder;
use Database\Seeders\DemoRegistrySeeder;
use Database\Seeders\DemoSectorSeeder;
use Database\Seeders\DemoSourceSeeder;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Seeds every lookup DemoRegistrySeeder depends on (sources/sectors/
 * referents + at least one internal manager), mirroring the dependency order
 * declared in DemoDataSeeder.
 */
function seedRegistryDependencies(): void
{
    test()->seed(DemoSourceSeeder::class);
    test()->seed(DemoSectorSeeder::class);
    test()->seed(DemoReferentTypeSeeder::class);
    test()->seed(DemoReferentSeeder::class);
    User::factory()->count(3)->create();
}

it('seeds registries each with a complete anagraphic card, contacts and a site-typed address', function (): void {
    seedRegistryDependencies();
    test()->seed(DemoRegistrySeeder::class);

    $registries = Registry::query()->with('personalData.contacts', 'personalData.addresses')->get();

    expect($registries)->not->toBeEmpty();

    $registries->each(function (Registry $registry): void {
        $card = $registry->personalData;

        expect($card)->not->toBeNull()
            ->and($registry->name)->toBe($card->full_name)
            ->and($card->contacts)->not->toBeEmpty()
            ->and($card->addresses)->not->toBeEmpty()
            ->and($card->contacts->firstWhere('is_primary', true))->not->toBeNull()
            ->and($card->addresses->firstWhere('is_primary', true))->not->toBeNull()
            ->and($card->addresses->first()->site_type)->not->toBeNull();
    });
});

it('normalizes is_qualified_supplier to false whenever is_supplier is false', function (): void {
    seedRegistryDependencies();
    test()->seed(DemoRegistrySeeder::class);

    $violations = Registry::query()->where('is_supplier', false)->where('is_qualified_supplier', true)->count();

    expect($violations)->toBe(0)
        ->and(Registry::query()->where('is_qualified_supplier', true)->count())->toBeGreaterThan(0);
});

it('attaches at most 4 internal managers per registry', function (): void {
    seedRegistryDependencies();
    test()->seed(DemoRegistrySeeder::class);

    Registry::query()->with('managers')->get()->each(
        fn (Registry $registry) => expect($registry->managers->count())->toBeLessThanOrEqual(4)
    );
});

it('is idempotent: re-running does not duplicate registries or orphan cards/pivots', function (): void {
    seedRegistryDependencies();
    test()->seed(DemoRegistrySeeder::class);

    $firstCount = Registry::query()->count();

    test()->seed(DemoRegistrySeeder::class);

    expect(Registry::query()->count())->toBe($firstCount)
        ->and(PersonalData::query()->where('personable_type', 'registry')->count())->toBe($firstCount)
        ->and(Contact::query()->whereMorphedTo('contactable', PersonalData::class)->count())
        ->toBe(Contact::query()->count())
        ->and(Address::query()->whereMorphedTo('addressable', PersonalData::class)->count())
        ->toBe(Address::query()->count());
});
