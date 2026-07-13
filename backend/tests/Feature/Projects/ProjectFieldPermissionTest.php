<?php

use App\Models\Project;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// AC-035 — a role with total_budget non-editable in role_field_permissions:
// PATCH {total_budget: 999} -> 422, value unchanged
// ---------------------------------------------------------------------------

it('update: total_budget editable:false for the actor\'s role -> 422, no write (AC-035)', function () {
    foreach (['viewAny', 'view', 'update'] as $ability) {
        Permission::findOrCreate("projects.{$ability}");
    }

    $role = Role::create(['name' => 'project-budget-locked']);
    $role->givePermissionTo(['projects.view', 'projects.update']);
    $role->fieldPermissions()->create([
        'resource' => 'projects',
        'field' => 'total_budget',
        'visible' => true,
        'editable' => false,
        'required' => false,
    ]);

    $actor = User::factory()->create();
    $actor->assignRole($role);

    $target = Project::factory()->create(['total_budget' => 100]);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/projects/{$target->id}", ['total_budget' => 999])
        ->assertStatus(422)
        ->assertJsonValidationErrors('total_budget');

    $this->assertDatabaseHas('projects', ['id' => $target->id, 'total_budget' => 100.00]);
});

it('a 403 (no base write ability) takes precedence over the field-level 422', function () {
    $actor = User::factory()->create();
    Permission::findOrCreate('projects.update');
    $target = Project::factory()->create();
    Sanctum::actingAs($actor);

    $this->patchJson("/api/projects/{$target->id}", ['total_budget' => 999])->assertForbidden();
});
