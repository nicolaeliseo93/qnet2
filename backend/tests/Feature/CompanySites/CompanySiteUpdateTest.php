<?php

use App\Models\Address;
use App\Models\Company;
use App\Models\CompanySite;
use App\Models\CompanySiteBank;
use App\Models\PersonalData;
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

if (! function_exists('companySiteCompanyProfile')) {
    /**
     * @param  array<string, mixed>  $overrides
     * @return array<string, mixed>
     */
    function companySiteCompanyProfile(array $overrides = []): array
    {
        return array_merge([
            'type' => 'company',
            'company_name' => 'Acme SpA',
        ], $overrides);
    }
}

// ---------------------------------------------------------------------------
// update — PUT/PATCH /api/company-sites/{companySite} (AC-008)
// ---------------------------------------------------------------------------

it('update: PATCH partial (only name) leaves the card + address untouched', function () {
    $actor = userWithCompanySiteAbilities(['update']);
    $target = CompanySite::factory()->create(['name' => 'Old Name']);
    $card = PersonalData::factory()->company()->for($target, 'personable')->create(['company_name' => 'Old SpA']);
    $address = Address::factory()->primary()->for($card, 'addressable')->create(['line1' => 'Original Street']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/company-sites/{$target->id}", ['name' => 'New Name'])
        ->assertOk()
        ->assertJsonPath('data.name', 'New Name')
        ->assertJsonPath('data.personal_data.company_name', 'Old SpA')
        ->assertJsonPath('data.personal_data.addresses.0.line1', 'Original Street');

    expect($card->addresses()->count())->toBe(1)
        ->and($address->fresh()->line1)->toBe('Original Street');
});

it('update: PATCH personal_data rewrites the card and its single address', function () {
    $actor = userWithCompanySiteAbilities(['update']);
    $target = CompanySite::factory()->create();
    $card = PersonalData::factory()->company()->for($target, 'personable')->create();
    Address::factory()->primary()->for($card, 'addressable')->create(['line1' => 'Before']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/company-sites/{$target->id}", [
        'personal_data' => companySiteCompanyProfile(['addresses' => [['line1' => 'After']]]),
    ])->assertOk()->assertJsonPath('data.personal_data.addresses.0.line1', 'After');

    expect($card->fresh()->addresses()->count())->toBe(1)
        ->and($card->fresh()->addresses()->first()->line1)->toBe('After');
});

it('update: PATCH personal_data.contacts full-replaces the card contacts', function () {
    $actor = userWithCompanySiteAbilities(['update']);
    $target = CompanySite::factory()->create();
    PersonalData::factory()->company()->for($target, 'personable')->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/company-sites/{$target->id}", [
        'personal_data' => companySiteCompanyProfile([
            'contacts' => [['type' => 'email', 'value' => 'new@acme.test', 'is_primary' => true]],
        ]),
    ])->assertOk()->assertJsonPath('data.personal_data.contacts.0.value', 'new@acme.test');
});

it('update: 422 when personal_data.addresses carries more than one address (max 1)', function () {
    $actor = userWithCompanySiteAbilities(['update']);
    $target = CompanySite::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/company-sites/{$target->id}", [
        'personal_data' => companySiteCompanyProfile(['addresses' => [['line1' => 'A'], ['line1' => 'B']]]),
    ])->assertStatus(422)->assertJsonValidationErrors('personal_data.addresses');
});

it('update: banks diff — add, update, remove in one authoritative PATCH', function () {
    $actor = userWithCompanySiteAbilities(['update']);
    $target = CompanySite::factory()->create();
    $keep = CompanySiteBank::factory()->for($target)->create(['name' => 'Keep Me']);
    CompanySiteBank::factory()->for($target)->create(['name' => 'Remove Me']);
    $remove = CompanySiteBank::where('name', 'Remove Me')->first();
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

it('update: PATCH company_id sets the owning company and its nested reference', function () {
    $actor = userWithCompanySiteAbilities(['update']);
    $target = CompanySite::factory()->create();
    $company = Company::factory()->create(['denomination' => 'Acme Holding SpA']);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/company-sites/{$target->id}", ['company_id' => $company->id])
        ->assertOk()
        ->assertJsonPath('data.company_id', $company->id)
        ->assertJsonPath('data.company.label', 'Acme Holding SpA');

    expect($target->fresh()->company_id)->toBe($company->id);
});

it('update: 422 when company_id does not reference an existing company', function () {
    $actor = userWithCompanySiteAbilities(['update']);
    $target = CompanySite::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/company-sites/{$target->id}", ['company_id' => 999999])
        ->assertStatus(422)->assertJsonValidationErrors('company_id');
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
