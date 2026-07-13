<?php

use App\Enums\MigrationStatus;
use App\Jobs\RunMigrationJob;
use App\Migrations\MigrationRegistry;
use App\Models\Attribute;
use App\Models\MigrationRun;
use App\Models\Role;
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
// AttributesSource — catalogue entry: create + old_id (+ ENUM options)
// ---------------------------------------------------------------------------

it('creates attributes with their old_id', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/attributes*' => Http::response([
            'items' => [
                ['id' => 5, 'code' => 'weight', 'name' => 'Weight', 'type' => 'decimal'],
                ['id' => 6, 'code' => 'active', 'name' => 'Active', 'type' => 'boolean'],
            ],
            'pagination' => ['total' => 2],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'attributes']);

    runMigrationJobFor($run);

    $weight = Attribute::query()->where('old_id', 5)->first();
    expect($weight->code)->toBe('weight')
        ->and($weight->name)->toBe('Weight')
        ->and($weight->type)->toBe('decimal')
        ->and(Attribute::query()->where('old_id', 6)->value('code'))->toBe('active');

    $fresh = $run->fresh();
    expect($fresh->status)->toBe(MigrationStatus::Completed)
        ->and($fresh->created_rows)->toBe(2)
        ->and($fresh->report)->toBeNull();
});

it('imports an ENUM attribute with its options', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/attributes*' => Http::response([
            'items' => [
                [
                    'id' => 20,
                    'code' => 'size',
                    'name' => 'Size',
                    'type' => 'enum',
                    'options' => [
                        ['value' => 'S', 'label' => 'Small', 'sort_order' => 0],
                        ['value' => 'L', 'label' => 'Large', 'sort_order' => 1],
                    ],
                ],
            ],
            'pagination' => ['total' => 1],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'attributes']);

    runMigrationJobFor($run);

    $size = Attribute::query()->where('old_id', 20)->first();
    expect($size->type)->toBe('enum')
        ->and($size->options()->pluck('value')->all())->toBe(['S', 'L']);

    expect($run->fresh()->created_rows)->toBe(1);
});

it('re-importing the same attributes is idempotent (skip, no duplicate)', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/attributes*' => Http::response([
            'items' => [['id' => 9, 'code' => 'color', 'name' => 'Color', 'type' => 'text']],
            'pagination' => ['total' => 1],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    runMigrationJobFor(MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'attributes']));

    $secondRun = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'attributes']);
    runMigrationJobFor($secondRun);

    expect(Attribute::query()->where('code', 'color')->count())->toBe(1)
        ->and($secondRun->fresh()->skipped_rows)->toBe(1)
        ->and($secondRun->fresh()->created_rows)->toBe(0);
});

it('isolates a failed attribute row (missing code) without blocking the valid one', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/attributes*' => Http::response([
            'items' => [
                ['id' => 10, 'code' => '', 'name' => 'Broken', 'type' => 'text'],
                ['id' => 11, 'code' => 'material', 'name' => 'Material', 'type' => 'text'],
            ],
            'pagination' => ['total' => 2],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'attributes']);

    runMigrationJobFor($run);

    expect(Attribute::query()->where('code', 'material')->exists())->toBeTrue();

    $fresh = $run->fresh();
    expect($fresh->created_rows)->toBe(1)
        ->and($fresh->failed_rows)->toBe(1)
        ->and(collect($fresh->report)->firstWhere('level', 'error'))->not->toBeNull();
});

it('isolates a failed attribute row (unregistered type) without blocking the valid one', function () {
    seedMigrationsConfig();
    Http::fake([
        fakeMigrationsBaseUrl().'/attributes*' => Http::response([
            'items' => [
                ['id' => 12, 'code' => 'legacy_flag', 'name' => 'Legacy flag', 'type' => 'NOT_A_TYPE'],
                ['id' => 13, 'code' => 'weight', 'name' => 'Weight', 'type' => 'decimal'],
            ],
            'pagination' => ['total' => 2],
        ]),
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'attributes']);

    runMigrationJobFor($run);

    expect(Attribute::query()->where('code', 'weight')->exists())->toBeTrue()
        ->and(Attribute::query()->where('code', 'legacy_flag')->exists())->toBeFalse();

    $fresh = $run->fresh();
    expect($fresh->created_rows)->toBe(1)
        ->and($fresh->failed_rows)->toBe(1);
});
