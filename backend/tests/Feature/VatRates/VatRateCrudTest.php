<?php

use App\Models\User;
use App\Models\VatRate;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('vatRateUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function vatRateUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("vat-rates.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("vat-rates.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// show — GET /api/vat-rates/{vatRate}
// ---------------------------------------------------------------------------

it('show: 200 with the full data shape', function () {
    $actor = vatRateUserWith(['view']);
    $target = VatRate::factory()->create(['name' => 'IVA 22%', 'rate' => 22]);
    Sanctum::actingAs($actor);

    $response = $this->getJson("/api/vat-rates/{$target->id}")
        ->assertOk()
        ->assertJsonPath('success', true)
        ->assertJsonPath('data.id', $target->id)
        ->assertJsonPath('data.name', 'IVA 22%')
        ->assertJsonPath('data.rate', '22.00');

    expect($response->json('permissions'))->toHaveKeys(['resource', 'fields', 'actions']);
});

it('show: 403 without vat-rates.view', function () {
    $actor = vatRateUserWith([]);
    $target = VatRate::factory()->create();
    Sanctum::actingAs($actor);

    $this->getJson("/api/vat-rates/{$target->id}")->assertForbidden();
});

it('show: 404 for a non-existent vat rate', function () {
    $actor = vatRateUserWith(['view']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/vat-rates/999999')->assertNotFound();
});

// ---------------------------------------------------------------------------
// create — POST /api/vat-rates
// ---------------------------------------------------------------------------

it('create: 201 + persists', function () {
    $actor = vatRateUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/vat-rates', ['name' => 'IVA 10%', 'rate' => 10])
        ->assertCreated()
        ->assertJsonPath('data.name', 'IVA 10%')
        ->assertJsonPath('data.rate', '10.00');

    $this->assertDatabaseHas('vat_rates', ['name' => 'IVA 10%']);
});

it('create: 403 without vat-rates.create', function () {
    $actor = vatRateUserWith([]);
    Sanctum::actingAs($actor);

    $this->postJson('/api/vat-rates', ['name' => 'Nope', 'rate' => 5])->assertForbidden();
});

it('create: 422 when name is missing', function () {
    $actor = vatRateUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/vat-rates', ['rate' => 5])
        ->assertStatus(422)->assertJsonValidationErrors('name');
});

it('create: 422 when rate is missing or negative', function () {
    $actor = vatRateUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/vat-rates', ['name' => 'X'])
        ->assertStatus(422)->assertJsonValidationErrors('rate');

    $this->postJson('/api/vat-rates', ['name' => 'X', 'rate' => -1])
        ->assertStatus(422)->assertJsonValidationErrors('rate');
});

it('create: 422 when name exceeds 191 characters', function () {
    $actor = vatRateUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/vat-rates', ['name' => str_repeat('a', 192), 'rate' => 5])
        ->assertStatus(422)->assertJsonValidationErrors('name');
});

// ---------------------------------------------------------------------------
// update — PUT/PATCH /api/vat-rates/{vatRate}
// ---------------------------------------------------------------------------

it('update: PATCH partial {rate} updates the vat rate', function () {
    $actor = vatRateUserWith(['update']);
    $target = VatRate::factory()->create(['name' => 'Before', 'rate' => 4]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/vat-rates/{$target->id}", ['rate' => 22])
        ->assertOk()
        ->assertJsonPath('data.name', 'Before')
        ->assertJsonPath('data.rate', '22.00');

    $this->assertDatabaseHas('vat_rates', ['id' => $target->id, 'name' => 'Before', 'rate' => 22.00]);
});

it('update: 403 without vat-rates.update', function () {
    $actor = vatRateUserWith([]);
    $target = VatRate::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/vat-rates/{$target->id}", ['name' => 'Nope'])->assertForbidden();
});

it('update: 404 for a non-existent vat rate', function () {
    $actor = vatRateUserWith(['update']);
    Sanctum::actingAs($actor);

    $this->patchJson('/api/vat-rates/999999', ['name' => 'Ghost'])->assertNotFound();
});

// ---------------------------------------------------------------------------
// delete — DELETE /api/vat-rates/{vatRate}
// ---------------------------------------------------------------------------

it('delete: 204, removes the vat rate (unguarded — products.vat_rate_id nullOnDelete)', function () {
    $actor = vatRateUserWith(['delete']);
    $target = VatRate::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/vat-rates/{$target->id}")->assertNoContent();

    $this->assertDatabaseMissing('vat_rates', ['id' => $target->id]);
});

it('delete: 403 without vat-rates.delete', function () {
    $actor = vatRateUserWith([]);
    $target = VatRate::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/vat-rates/{$target->id}")->assertForbidden();
});

it('delete: 404 for a non-existent vat rate', function () {
    $actor = vatRateUserWith(['delete']);
    Sanctum::actingAs($actor);

    $this->deleteJson('/api/vat-rates/999999')->assertNotFound();
});
