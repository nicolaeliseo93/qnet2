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
// AC-008 — UsersSource: create + old_id + idempotent re-import
// ---------------------------------------------------------------------------

it('creates users with their personal-data card, sets old_id, and re-import skips (idempotent)', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/users*' => Http::response([
            'data' => [
                ['id' => 101, 'email' => 'ada@example.test', 'first_name' => 'Ada', 'last_name' => 'Lovelace', 'locale' => 'en'],
                ['id' => 102, 'email' => 'alan@example.test', 'first_name' => 'Alan', 'last_name' => 'Turing', 'locale' => 'en'],
            ],
            'meta' => ['total' => 2],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'users']);

    runMigrationJobFor($run);

    $ada = User::query()->where('email', 'ada@example.test')->first();

    expect($ada)->not->toBeNull()
        ->and($ada->old_id)->toBe(101)
        ->and($ada->personalData?->first_name)->toBe('Ada')
        ->and($ada->personalData?->last_name)->toBe('Lovelace');

    $fresh = $run->fresh();
    expect($fresh->status)->toBe(MigrationStatus::Completed)
        ->and($fresh->created_rows)->toBe(2)
        ->and($fresh->skipped_rows)->toBe(0);

    // Re-import the SAME external users: idempotent, no duplicates.
    $secondRun = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'users']);
    runMigrationJobFor($secondRun);

    expect(User::query()->where('email', 'ada@example.test')->count())->toBe(1);

    $secondFresh = $secondRun->fresh();
    expect($secondFresh->created_rows)->toBe(0)
        ->and($secondFresh->skipped_rows)->toBe(2);
});

// ---------------------------------------------------------------------------
// AC-009 — remap roles via old_id + warning on unresolved reference
// ---------------------------------------------------------------------------

it('remaps role references via old_id and warns on an unresolved one', function () {
    seedMigrationsConfig();
    $migratedRole = Role::factory()->create(['name' => 'operator', 'old_id' => 55]);

    Http::fake([
        fakeMigrationsBaseUrl().'/users*' => Http::response([
            'data' => [
                ['id' => 201, 'email' => 'grace@example.test', 'first_name' => 'Grace', 'last_name' => 'Hopper', 'locale' => 'en', 'role_ids' => [55, 999]],
            ],
            'meta' => ['total' => 1],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'users']);

    runMigrationJobFor($run);

    $grace = User::query()->where('email', 'grace@example.test')->first();

    expect($grace)->not->toBeNull()
        ->and($grace->hasRole($migratedRole->name))->toBeTrue();

    $fresh = $run->fresh();
    expect($fresh->status)->toBe(MigrationStatus::Completed)
        ->and($fresh->created_rows)->toBe(1)
        ->and($fresh->report)->not->toBeNull()
        ->and($fresh->report[0]['level'])->toBe('warning')
        ->and($fresh->report[0]['message'])->toContain('999');
});
