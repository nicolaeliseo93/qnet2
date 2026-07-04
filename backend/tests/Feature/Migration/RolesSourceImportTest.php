<?php

use App\Enums\MigrationStatus;
use App\Jobs\RunMigrationJob;
use App\Migrations\MigrationRegistry;
use App\Models\MigrationRun;
use App\Models\Role;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

if (! function_exists('fakeMigrationsBaseUrl')) {
    function fakeMigrationsBaseUrl(): string
    {
        return 'https://external-crm.test';
    }
}

if (! function_exists('seedMigrationsConfig')) {
    function seedMigrationsConfig(): void
    {
        config([
            'migrations.base_url' => fakeMigrationsBaseUrl(),
            'migrations.token' => null,
            'migrations.timeout' => 5,
            'migrations.retry_times' => 1,
            'migrations.retry_sleep_ms' => 1,
            'migrations.import_batch_size' => 100,
        ]);
    }
}

if (! function_exists('migrationsSuperAdminActor')) {
    function migrationsSuperAdminActor(): User
    {
        Role::query()->firstOrCreate(['name' => 'super-admin']);

        $actor = User::factory()->create();
        $actor->assignRole('super-admin');

        return $actor;
    }
}

if (! function_exists('runMigrationJobFor')) {
    function runMigrationJobFor(MigrationRun $run): void
    {
        (new RunMigrationJob($run->id))->handle(app(MigrationRegistry::class));
    }
}

// ---------------------------------------------------------------------------
// AC-011 — RolesSource: adoption on existing name, creation, no permissions
// ---------------------------------------------------------------------------

it('adopts old_id onto an existing role sharing the same name (no duplicate)', function () {
    seedMigrationsConfig();
    $existing = Role::factory()->create(['name' => 'operator']);
    Http::fake([
        fakeMigrationsBaseUrl().'/roles*' => Http::response([
            'items' => [['id' => 1, 'name' => 'operator']],
            'pagination' => ['total' => 1],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'roles']);

    runMigrationJobFor($run);

    expect(Role::query()->where('name', 'operator')->count())->toBe(1)
        ->and($existing->fresh()->old_id)->toBe(1);

    $fresh = $run->fresh();
    expect($fresh->status)->toBe(MigrationStatus::Completed)
        ->and($fresh->created_rows)->toBe(1)
        ->and($fresh->skipped_rows)->toBe(0);
});

it('creates a new role with old_id when the name does not exist yet', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/roles*' => Http::response([
            'items' => [['id' => 2, 'name' => 'brand-new-role']],
            'pagination' => ['total' => 1],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'roles']);

    runMigrationJobFor($run);

    $role = Role::query()->where('name', 'brand-new-role')->first();

    expect($role)->not->toBeNull()
        ->and($role->old_id)->toBe(2)
        ->and($role->permissions)->toHaveCount(0); // AC-011: permissions are never imported
});

it('re-importing the same roles is idempotent (skip, no duplicate)', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/roles*' => Http::response([
            'items' => [['id' => 3, 'name' => 'already-migrated']],
            'pagination' => ['total' => 1],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $firstRun = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'roles']);
    runMigrationJobFor($firstRun);

    $secondRun = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'roles']);
    runMigrationJobFor($secondRun);

    expect(Role::query()->where('name', 'already-migrated')->count())->toBe(1);

    $fresh = $secondRun->fresh();
    expect($fresh->skipped_rows)->toBe(1)
        ->and($fresh->created_rows)->toBe(0);
});
