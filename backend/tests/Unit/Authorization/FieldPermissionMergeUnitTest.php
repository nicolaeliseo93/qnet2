<?php

use App\Authorization\AbstractResourceAuthorization;
use App\Authorization\FieldDefinition;
use App\Authorization\FieldPermission;
use App\Authorization\FieldPermissionRepository;
use App\Models\Role;
use App\Models\User;
use App\Services\RoleAssignmentGuard;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Tests\TestCase;

// Touches the database (DB-configured matrix rows, actor roles), so bind the
// full TestCase + RefreshDatabase (the default Pest binding only applies to
// the Feature suite — see tests/Pest.php).
uses(TestCase::class, RefreshDatabase::class);

/**
 * A fixture whose ceiling is entirely fixed (ignores actor/model), so every
 * test below isolates the MERGE algorithm itself
 * (AbstractResourceAuthorization::fieldPermissions(), spec 0006) rather than
 * re-testing a concrete resource's ceiling rules (already covered by
 * UsersAuthorization/RolesAuthorization tests).
 *
 * `lockField` reproduces FieldPermission::disabled() as the ceiling — a state
 * NEITHER UsersAuthorization NOR RolesAuthorization ever produces, so this is
 * the only place the "ceiling.disabled is preserved through the merge, DB can
 * never lift it" branch is exercised.
 */
class MergeFixtureAuthorization extends AbstractResourceAuthorization
{
    public function __construct(FieldPermissionRepository $fieldPermissionRepository)
    {
        parent::__construct($fieldPermissionRepository);
    }

    public function resource(): string
    {
        return 'merge-fixture';
    }

    /**
     * @return array<int, FieldDefinition>
     */
    public function fields(): array
    {
        return [
            new FieldDefinition('openField', 'text'),
            new FieldDefinition('requiredField', 'text'),
            new FieldDefinition('lockedField', 'text'),
        ];
    }

    /**
     * @return array<int, string>
     */
    public function actions(): array
    {
        return [];
    }

    /**
     * @return array<string, FieldPermission>
     */
    protected function fieldPermissionCeiling(User $actor, ?Model $model): array
    {
        return [
            'openField' => FieldPermission::visibleEditable(),
            'requiredField' => FieldPermission::visibleEditable(required: true),
            'lockedField' => FieldPermission::disabled(),
        ];
    }
}

if (! function_exists('mergeFixtureAuthorization')) {
    function mergeFixtureAuthorization(): MergeFixtureAuthorization
    {
        return new MergeFixtureAuthorization(new FieldPermissionRepository);
    }
}

if (! function_exists('actorWithRole')) {
    function actorWithRole(Role $role): User
    {
        $actor = User::factory()->create();
        $actor->assignRole($role);

        return $actor;
    }
}

it('no DB row for a field: full pass-through of the ceiling (0004 behavior unchanged)', function () {
    $role = Role::create(['name' => 'no-row']);
    $actor = actorWithRole($role);

    $permission = mergeFixtureAuthorization()->fieldPermissions($actor, null)['openField'];

    expect($permission->visible)->toBeTrue()
        ->and($permission->editable)->toBeTrue();
});

it('db.visible false hides the field even though the ceiling was editable', function () {
    $role = Role::create(['name' => 'hides-open']);
    $role->fieldPermissions()->create(['resource' => 'merge-fixture', 'field' => 'openField', 'visible' => false, 'editable' => true, 'required' => false]);
    $actor = actorWithRole($role);

    $permission = mergeFixtureAuthorization()->fieldPermissions($actor, null)['openField'];

    expect($permission->visible)->toBeFalse()
        ->and($permission->hidden)->toBeTrue()
        ->and($permission->editable)->toBeFalse();
});

it('ceiling.disabled is preserved through the merge — DB can never lift a hard lock', function () {
    $role = Role::create(['name' => 'tries-to-unlock']);
    // DB tries to make the locked field fully open — must have NO effect.
    $role->fieldPermissions()->create(['resource' => 'merge-fixture', 'field' => 'lockedField', 'visible' => true, 'editable' => true, 'required' => true]);
    $actor = actorWithRole($role);

    $permission = mergeFixtureAuthorization()->fieldPermissions($actor, null)['lockedField'];

    expect($permission->disabled)->toBeTrue()
        ->and($permission->editable)->toBeFalse()
        ->and($permission->readonly)->toBeFalse(); // disabled, not plain readonly
});

it('required = ceiling.required OR (db.required AND editable)', function () {
    $role = Role::create(['name' => 'adds-required']);
    $role->fieldPermissions()->create(['resource' => 'merge-fixture', 'field' => 'openField', 'visible' => true, 'editable' => true, 'required' => true]);
    $actor = actorWithRole($role);

    $permission = mergeFixtureAuthorization()->fieldPermissions($actor, null)['openField'];

    expect($permission->required)->toBeTrue(); // ceiling was not required, but DB adds it
});

it('required never surfaces on a field the merge made non-editable, even if ceiling.required was true', function () {
    $role = Role::create(['name' => 'locks-required-field']);
    $role->fieldPermissions()->create(['resource' => 'merge-fixture', 'field' => 'requiredField', 'visible' => true, 'editable' => false, 'required' => true]);
    $actor = actorWithRole($role);

    $permission = mergeFixtureAuthorization()->fieldPermissions($actor, null)['requiredField'];

    expect($permission->editable)->toBeFalse()
        ->and($permission->required)->toBeFalse(); // required is meaningless when not editable
});

it('privileged bypass: a super-admin actor gets the full ceiling regardless of any DB row', function () {
    $superRole = Role::create(['name' => RoleAssignmentGuard::PRIVILEGED_ROLE]);
    $superRole->fieldPermissions()->create(['resource' => 'merge-fixture', 'field' => 'openField', 'visible' => false, 'editable' => false, 'required' => false]);
    $actor = actorWithRole($superRole);

    $permission = mergeFixtureAuthorization()->fieldPermissions($actor, null)['openField'];

    expect($permission->visible)->toBeTrue()
        ->and($permission->editable)->toBeTrue();
});

it('an actor holding no role at all gets the full ceiling (no db lookup possible)', function () {
    $actor = User::factory()->create();

    $permission = mergeFixtureAuthorization()->fieldPermissions($actor, null)['openField'];

    expect($permission->visible)->toBeTrue()
        ->and($permission->editable)->toBeTrue();
});
