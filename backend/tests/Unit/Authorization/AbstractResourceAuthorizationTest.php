<?php

use App\Authorization\AbstractResourceAuthorization;
use App\Authorization\FieldDefinition;
use App\Authorization\FieldPermission;
use App\Authorization\FieldPermissionRepository;
use App\Models\User;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;
use Tests\TestCase;

// Needs the app container (Gate/permission checks touch the DB), so bind the
// full TestCase + RefreshDatabase explicitly (the default Pest binding only
// applies to the Feature suite — see tests/Pest.php).
uses(TestCase::class, RefreshDatabase::class);

/**
 * Minimal concrete ResourceAuthorization overriding NOTHING beyond the three
 * required catalogue methods + fieldPermissionCeiling(). Spec 0006 made
 * `fieldPermissions()` FINAL in the abstract (it now merges the ceiling with
 * the DB matrix), so a concrete class can no longer inherit a default field-
 * permission rule — every concrete class (this fixture included) supplies its
 * own `fieldPermissionCeiling()`. This one reproduces the exact 0004 default
 * rule (visibleEditable-when-write / visibleReadonly-else) so it still
 * exercises `resourcePermissions()`/`actionPermissions()`'s own abstract
 * defaults, which are unaffected by 0006.
 *
 * With no roles/DB matrix rows configured for the actor, `fieldPermissions()`
 * (the final merge) passes this ceiling through unchanged — see spec 0006
 * merge semantics ("absence of any row = full/unrestricted") — so calling the
 * public `fieldPermissions()` here still exercises fieldPermissionCeiling()
 * end to end.
 */
class WidgetsAuthorizationFixture extends AbstractResourceAuthorization
{
    public function resource(): string
    {
        return 'widgets';
    }

    /**
     * @return array<int, FieldDefinition>
     */
    public function fields(): array
    {
        return [new FieldDefinition('title', 'text')];
    }

    /**
     * @return array<int, string>
     */
    public function actions(): array
    {
        return ['publish'];
    }

    /**
     * @return array<string, FieldPermission>
     */
    protected function fieldPermissionCeiling(User $actor, ?Model $model): array
    {
        $editable = $this->actorMayWrite($actor, $model);

        $permissions = [];

        foreach ($this->fields() as $field) {
            $permissions[$field->key] = $editable
                ? FieldPermission::visibleEditable()
                : FieldPermission::visibleReadonly();
        }

        return $permissions;
    }

    /**
     * Public passthroughs so the test can exercise the protected no-op
     * contextual hooks directly (spec 0004: "present but no-op" — nothing in
     * this slice calls them, so this fixture is the only way to cover them).
     */
    public function callAppliesResourceState(User $actor, ?Model $model): bool
    {
        return $this->appliesResourceState($actor, $model);
    }

    public function callAppliesOwnership(User $actor, ?Model $model): bool
    {
        return $this->appliesOwnership($actor, $model);
    }

    public function callAppliesLocation(User $actor, ?Model $model): bool
    {
        return $this->appliesLocation($actor, $model);
    }
}

if (! function_exists('widgetsAuthorization')) {
    function widgetsAuthorization(): WidgetsAuthorizationFixture
    {
        return new WidgetsAuthorizationFixture(new FieldPermissionRepository);
    }
}

if (! function_exists('seedWidgetsPermissions')) {
    function seedWidgetsPermissions(): void
    {
        foreach (['view', 'create', 'update', 'delete', 'export', 'import', 'publish'] as $ability) {
            Permission::findOrCreate("widgets.{$ability}");
        }
    }
}

/*
|--------------------------------------------------------------------------
| resourcePermissions() — every one of the 6 standard abilities
|--------------------------------------------------------------------------
*/

it('resourcePermissions(): maps every one of the 6 standard abilities to "{resource}.{ability}"', function () {
    seedWidgetsPermissions();
    $actor = User::factory()->create();
    $actor->givePermissionTo(['widgets.view', 'widgets.delete']);

    expect(widgetsAuthorization()->resourcePermissions($actor, null))->toBe([
        'view' => true,
        'create' => false,
        'update' => false,
        'delete' => true,
        'export' => false,
        'import' => false,
    ]);
});

it('resourcePermissions(): all true for an actor holding every ability', function () {
    seedWidgetsPermissions();
    $actor = User::factory()->create();
    $actor->givePermissionTo(['widgets.view', 'widgets.create', 'widgets.update', 'widgets.delete', 'widgets.export', 'widgets.import']);

    expect(widgetsAuthorization()->resourcePermissions($actor, null))->toBe([
        'view' => true,
        'create' => true,
        'update' => true,
        'delete' => true,
        'export' => true,
        'import' => true,
    ]);
});

/*
|--------------------------------------------------------------------------
| fieldPermissions() — visibleEditable-when-write / visibleReadonly-else,
| create (model null) vs update (model set) ability switch. No roles/DB
| matrix rows exist for these actors, so the final merge (spec 0006) is a
| pure pass-through of fieldPermissionCeiling() — see class docblock.
|--------------------------------------------------------------------------
*/

it('fieldPermissions(): visibleEditable in create-context when the actor may create', function () {
    seedWidgetsPermissions();
    $actor = User::factory()->create();
    $actor->givePermissionTo('widgets.create');

    $permission = widgetsAuthorization()->fieldPermissions($actor, null)['title'];

    expect($permission->visible)->toBeTrue()
        ->and($permission->editable)->toBeTrue()
        ->and($permission->hidden)->toBeFalse()
        ->and($permission->readonly)->toBeFalse();
});

it('fieldPermissions(): visibleReadonly in create-context when the actor may NOT create', function () {
    seedWidgetsPermissions();
    $actor = User::factory()->create();

    $permission = widgetsAuthorization()->fieldPermissions($actor, null)['title'];

    expect($permission->visible)->toBeTrue()
        ->and($permission->editable)->toBeFalse()
        ->and($permission->readonly)->toBeTrue();
});

it('fieldPermissions(): switches the gating ability from create to update once $model is not null', function () {
    seedWidgetsPermissions();
    $actor = User::factory()->create();
    $actor->givePermissionTo('widgets.update'); // NOT widgets.create
    $model = User::factory()->create(); // any Model instance: the ceiling only checks null-ness

    $authorization = widgetsAuthorization();
    $createContext = $authorization->fieldPermissions($actor, null)['title'];
    $updateContext = $authorization->fieldPermissions($actor, $model)['title'];

    expect($createContext->editable)->toBeFalse() // no widgets.create
        ->and($updateContext->editable)->toBeTrue(); // has widgets.update
});

it('fieldPermissions(): visibleReadonly in update-context when the actor may NOT update', function () {
    seedWidgetsPermissions();
    $actor = User::factory()->create();
    $model = User::factory()->create();

    $permission = widgetsAuthorization()->fieldPermissions($actor, $model)['title'];

    expect($permission->editable)->toBeFalse()
        ->and($permission->readonly)->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| actionPermissions() — each action gated by "{resource}.{action}"
|--------------------------------------------------------------------------
*/

it('actionPermissions(): default gates each action by "{resource}.{action}"', function () {
    seedWidgetsPermissions();
    $actor = User::factory()->create();
    $actor->givePermissionTo('widgets.publish');

    expect(widgetsAuthorization()->actionPermissions($actor, null))->toBe(['publish' => true]);
});

it('actionPermissions(): false when the actor lacks the mapped permission', function () {
    seedWidgetsPermissions();
    $actor = User::factory()->create();

    expect(widgetsAuthorization()->actionPermissions($actor, null))->toBe(['publish' => false]);
});

/*
|--------------------------------------------------------------------------
| Contextual hooks — present but no-op by default (spec 0004)
|--------------------------------------------------------------------------
*/

it('appliesResourceState()/appliesOwnership()/appliesLocation(): no-op default, always true, regardless of actor/model', function () {
    $actor = User::factory()->create();
    $model = User::factory()->create();
    $authorization = widgetsAuthorization();

    expect($authorization->callAppliesResourceState($actor, null))->toBeTrue()
        ->and($authorization->callAppliesResourceState($actor, $model))->toBeTrue()
        ->and($authorization->callAppliesOwnership($actor, null))->toBeTrue()
        ->and($authorization->callAppliesOwnership($actor, $model))->toBeTrue()
        ->and($authorization->callAppliesLocation($actor, null))->toBeTrue()
        ->and($authorization->callAppliesLocation($actor, $model))->toBeTrue();
});
