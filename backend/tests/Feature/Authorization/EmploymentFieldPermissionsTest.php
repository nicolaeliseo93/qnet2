<?php

use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

/**
 * Feature coverage for AC-012 (spec 0015): UsersAuthorization::fields()
 * includes the 12 `employment.*` keys; a role with a field denied in the
 * matrix cannot write it (ceiling respected), and pre-existing values are
 * preserved (CHANGE-based enforcement, spec 0008).
 */
const EMPLOYMENT_FIELD_KEYS = [
    'employment.is_manager', 'employment.job_description', 'employment.reports_to_id',
    'employment.business_function_id', 'employment.relationship_type', 'employment.company_id',
    'employment.operational_site_id', 'employment.qualification_type', 'employment.hired_at',
    'employment.terminated_at', 'employment.standard_daily_minutes', 'employment.break_daily_minutes',
];

if (! function_exists('employmentFieldPermActor')) {
    function employmentFieldPermActor(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("users.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("users.{$ability}");
        }

        return $user;
    }
}

it('AC-012: permissions.fields includes the 12 employment.* keys, editable when the actor may update', function () {
    $actor = employmentFieldPermActor(['view', 'update']);
    $target = User::factory()->withEmployment()->create();
    Sanctum::actingAs($actor);

    $fields = $this->getJson("/api/users/{$target->id}")->assertOk()->json('permissions.fields');

    foreach (EMPLOYMENT_FIELD_KEYS as $key) {
        expect($fields)->toHaveKey($key);
        expect($fields[$key]['visible'])->toBeTrue()
            ->and($fields[$key]['editable'])->toBeTrue();
    }
});

it('AC-012: employment.* fields are visibleReadonly when the actor may NOT update', function () {
    $actor = employmentFieldPermActor(['view']);
    $target = User::factory()->withEmployment()->create();
    Sanctum::actingAs($actor);

    $fields = $this->getJson("/api/users/{$target->id}")->assertOk()->json('permissions.fields');

    expect($fields['employment.job_description']['editable'])->toBeFalse()
        ->and($fields['employment.job_description']['readonly'])->toBeTrue();
});

it('AC-012: a role denying employment.job_description in the matrix cannot write it, and the existing value is preserved', function () {
    Permission::findOrCreate('users.view');
    Permission::findOrCreate('users.update');

    $role = Role::create(['name' => 'employment-field-perm-'.uniqid()]);
    $role->givePermissionTo(['users.view', 'users.update']);
    $role->fieldPermissions()->create([
        'resource' => 'users', 'field' => 'employment.job_description', 'visible' => true, 'editable' => false, 'required' => false,
    ]);

    $actor = User::factory()->create();
    $actor->assignRole($role);

    $target = User::factory()->withEmployment(fn ($f) => $f->state(['job_description' => 'Original role']))->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/users/{$target->id}", [
        'employment' => ['job_description' => 'Changed role'],
    ])->assertStatus(422)->assertJsonValidationErrors(['employment.job_description']);

    $this->assertDatabaseHas('employment_profiles', [
        'user_id' => $target->id,
        'job_description' => 'Original role',
    ]);
});

it('AC-012: no employment.* resource permission exists in the system — governed entirely by the field matrix', function () {
    expect(Permission::where('name', 'like', 'employment.%')->exists())->toBeFalse();
});
