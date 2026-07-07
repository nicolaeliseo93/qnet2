<?php

use App\Models\Address;
use App\Models\CompanySite;
use App\Models\CompanySiteBank;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('userWithCompanySiteAbilities')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function userWithCompanySiteAbilities(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("company-sites.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("company-sites.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// update — PUT/PATCH /api/company-sites/{companySite} (AC-008)
// ---------------------------------------------------------------------------

it('update: PATCH partial (only name) leaves email/address untouched', function () {
    $actor = userWithCompanySiteAbilities(['update']);
    $target = CompanySite::factory()->create(['name' => 'Old Name', 'email' => 'old@acme.test']);
    $address = Address::factory()->primary()->for($target, 'addressable')->create(['line1' => 'Original Street']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/company-sites/{$target->id}", ['name' => 'New Name'])
        ->assertOk()
        ->assertJsonPath('data.name', 'New Name')
        ->assertJsonPath('data.email', 'old@acme.test')
        ->assertJsonPath('data.address.line1', 'Original Street');

    expect($target->addresses()->count())->toBe(1)
        ->and($address->fresh()->line1)->toBe('Original Street');
});

it('update: PATCH with address updates the EXISTING address, never duplicates it', function () {
    $actor = userWithCompanySiteAbilities(['update']);
    $target = CompanySite::factory()->create();
    Address::factory()->primary()->for($target, 'addressable')->create(['line1' => 'Before']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/company-sites/{$target->id}", ['address' => ['line1' => 'After']])
        ->assertOk()->assertJsonPath('data.address.line1', 'After');

    expect($target->addresses()->count())->toBe(1);
});

it('update: banks diff — add, update, remove in one authoritative PATCH', function () {
    $actor = userWithCompanySiteAbilities(['update']);
    $target = CompanySite::factory()->create();
    $keep = CompanySiteBank::factory()->for($target)->create(['name' => 'Keep Me']);
    $remove = CompanySiteBank::factory()->for($target)->create(['name' => 'Remove Me']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/company-sites/{$target->id}", [
        'banks' => [
            ['id' => $keep->id, 'name' => 'Kept Renamed'],
            ['name' => 'Brand New'],
        ],
    ])->assertOk();

    $names = $target->banks()->pluck('name')->all();
    expect($names)->toEqualCanonicalizing(['Kept Renamed', 'Brand New'])
        ->and(CompanySiteBank::find($remove->id))->toBeNull();
});

it('update: a bank id belonging to ANOTHER site is treated as a create, never an update', function () {
    $actor = userWithCompanySiteAbilities(['update']);
    $target = CompanySite::factory()->create();
    $other = CompanySite::factory()->create();
    $foreignBank = CompanySiteBank::factory()->for($other)->create(['name' => 'Foreign']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/company-sites/{$target->id}", [
        'banks' => [['id' => $foreignBank->id, 'name' => 'Hijacked']],
    ])->assertOk();

    expect($foreignBank->fresh()->name)->toBe('Foreign')
        ->and($other->banks()->count())->toBe(1)
        ->and($target->banks()->count())->toBe(1)
        ->and($target->banks()->first()->name)->toBe('Hijacked');
});

it('update: default_bank_id resolved post-sync against the site\'s own banks', function () {
    $actor = userWithCompanySiteAbilities(['update']);
    $target = CompanySite::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->patchJson("/api/company-sites/{$target->id}", [
        'banks' => [['name' => 'Banca Uno']],
    ])->assertOk();

    $bankId = $response->json('data.banks.0.id');

    $this->patchJson("/api/company-sites/{$target->id}", ['default_bank_id' => $bankId])
        ->assertOk()
        ->assertJsonPath('data.default_bank_id', $bankId);
});

it('update: default_bank_id foreign to this site is rejected', function () {
    $actor = userWithCompanySiteAbilities(['update']);
    $target = CompanySite::factory()->create();
    $other = CompanySite::factory()->create();
    $foreignBank = CompanySiteBank::factory()->for($other)->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/company-sites/{$target->id}", ['default_bank_id' => $foreignBank->id])
        ->assertStatus(422)->assertJsonValidationErrors('default_bank_id');
});

it('update: 403 without company-sites.update', function () {
    $actor = userWithCompanySiteAbilities([]);
    $target = CompanySite::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/company-sites/{$target->id}", ['name' => 'Nope'])->assertForbidden();
});

it('update: 404 for a non-existent site', function () {
    $actor = userWithCompanySiteAbilities(['update']);
    Sanctum::actingAs($actor);

    $this->patchJson('/api/company-sites/999999', ['name' => 'Ghost'])->assertNotFound();
});

// ---------------------------------------------------------------------------
// field permissions — "Altro" is read-only, server-side (AC-009)
// ---------------------------------------------------------------------------

it('update: a changed "Altro" field is rejected with 422, even with company-sites.update', function () {
    $actor = userWithCompanySiteAbilities(['update']);
    $target = CompanySite::factory()->create(['company_type' => 1]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/company-sites/{$target->id}", ['company_type' => 2])
        ->assertStatus(422)->assertJsonValidationErrors('company_type');
});

it('update: resubmitting the SAME "Altro" value is a no-op, not rejected', function () {
    $actor = userWithCompanySiteAbilities(['update']);
    $target = CompanySite::factory()->create(['company_type' => 1]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/company-sites/{$target->id}", ['company_type' => 1, 'name' => $target->name])
        ->assertOk();
});
