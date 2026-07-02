<?php

use App\Models\Address;
use App\Models\Contact;
use App\Models\PersonalData;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

it('denies CRUD on personal_data when the user lacks the permission', function () {
    $user = User::factory()->create();
    $card = PersonalData::factory()->create();

    expect($user->can('viewAny', PersonalData::class))->toBeFalse()
        ->and($user->can('update', $card))->toBeFalse()
        ->and($user->can('delete', $card))->toBeFalse();
});

it('allows a personal_data ability mapped to the matching permission', function () {
    Permission::create(['name' => 'personal_data.update']);
    $user = User::factory()->create();
    $user->givePermissionTo('personal_data.update');
    $card = PersonalData::factory()->create();

    expect($user->can('update', $card))->toBeTrue()
        ->and($user->can('delete', $card))->toBeFalse();
});

it('maps contacts and addresses abilities to their resource permissions', function () {
    Permission::create(['name' => 'contacts.create']);
    Permission::create(['name' => 'addresses.view']);
    $user = User::factory()->create();
    $user->givePermissionTo(['contacts.create', 'addresses.view']);

    $contact = Contact::factory()->create();
    $address = Address::factory()->create();

    expect($user->can('create', Contact::class))->toBeTrue()
        ->and($user->can('view', $address))->toBeTrue()
        ->and($user->can('delete', $contact))->toBeFalse();
});

it('permissions:sync registers the standard CRUD permissions for the three resources', function () {
    $this->artisan('permissions:sync')->assertSuccessful();

    foreach (['personal_data', 'contacts', 'addresses'] as $resource) {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            expect(Permission::where('name', "{$resource}.{$ability}")->exists())->toBeTrue();
        }
    }
});
