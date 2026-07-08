<?php

use App\Enums\MigrationStatus;
use App\Jobs\RunMigrationJob;
use App\Migrations\MigrationRegistry;
use App\Models\MigrationRun;
use App\Models\Role;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Http;

uses(RefreshDatabase::class);

// The shared helpers (fakeMigrationsBaseUrl/seedMigrationsConfig/
// migrationsSuperAdminActor/runMigrationJobFor) are defined once, guarded by
// function_exists, across the Migration feature suite (see CompaniesSourceImportTest).
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
// TagsSource — create + old_id (standalone lookup, no association imported)
// ---------------------------------------------------------------------------

it('creates tags with their old_id', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/tags*' => Http::response([
            'items' => [
                ['id' => 5, 'name' => 'Prospect'],
                ['id' => 6, 'name' => 'Customer'],
            ],
            'pagination' => ['total' => 2],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'tags']);

    runMigrationJobFor($run);

    expect(Tag::query()->where('old_id', 5)->value('name'))->toBe('Prospect')
        ->and(Tag::query()->where('old_id', 6)->value('name'))->toBe('Customer');

    $fresh = $run->fresh();
    expect($fresh->status)->toBe(MigrationStatus::Completed)
        ->and($fresh->created_rows)->toBe(2)
        ->and($fresh->report)->toBeNull();
});

it('re-importing the same tags is idempotent (skip, no duplicate)', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/tags*' => Http::response([
            'items' => [['id' => 9, 'name' => 'Supplier']],
            'pagination' => ['total' => 1],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    runMigrationJobFor(MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'tags']));

    $secondRun = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'tags']);
    runMigrationJobFor($secondRun);

    expect(Tag::query()->where('name', 'Supplier')->count())->toBe(1)
        ->and($secondRun->fresh()->skipped_rows)->toBe(1)
        ->and($secondRun->fresh()->created_rows)->toBe(0);
});

it('isolates a failed tag row (missing name) without blocking the valid one', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/tags*' => Http::response([
            'items' => [
                ['id' => 10, 'name' => ''],
                ['id' => 11, 'name' => 'Valid Tag'],
            ],
            'pagination' => ['total' => 2],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'tags']);

    runMigrationJobFor($run);

    expect(Tag::query()->where('name', 'Valid Tag')->exists())->toBeTrue();

    $fresh = $run->fresh();
    expect($fresh->created_rows)->toBe(1)
        ->and($fresh->failed_rows)->toBe(1)
        ->and(collect($fresh->report)->firstWhere('level', 'error'))->not->toBeNull();
});
