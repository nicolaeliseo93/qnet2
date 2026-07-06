<?php

use App\Enums\MigrationStatus;
use App\Jobs\RunMigrationJob;
use App\Migrations\MigrationRegistry;
use App\Models\BusinessFunction;
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

if (! function_exists('fakeUserBusinessFunctions')) {
    /**
     * @param  array<int, array<string, mixed>>  $items
     */
    function fakeUserBusinessFunctions(array $items): void
    {
        Http::fake([
            fakeMigrationsBaseUrl().'/user-business-functions*' => Http::response([
                'items' => $items,
                'pagination' => ['total' => count($items)],
            ]),
        ]);
    }
}

// ---------------------------------------------------------------------------
// UserBusinessFunctionsSource: attach the user <-> business-function pivot from
// a junction endpoint, remapping both sides via old_id (user-side counterpart
// of BusinessFunctionsSource AC-010).
// ---------------------------------------------------------------------------

it('attaches users to their business functions resolving both sides via old_id', function () {
    seedMigrationsConfig();
    $userOne = User::factory()->create(['old_id' => 501]);
    $userTwo = User::factory()->create(['old_id' => 502]);
    $finance = BusinessFunction::factory()->create(['old_id' => 1]);
    $operations = BusinessFunction::factory()->create(['old_id' => 2]);

    fakeUserBusinessFunctions([
        ['id' => 10, 'user_id' => 501, 'business_function_id' => 1],
        ['id' => 11, 'user_id' => 502, 'business_function_id' => 1],
        ['id' => 12, 'user_id' => 501, 'business_function_id' => 2],
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'user-business-functions']);

    runMigrationJobFor($run);

    expect($finance->users()->pluck('users.id')->sort()->values()->all())
        ->toBe(collect([$userOne->id, $userTwo->id])->sort()->values()->all())
        ->and($operations->users()->pluck('users.id')->all())->toBe([$userOne->id]);

    $fresh = $run->fresh();
    expect($fresh->status)->toBe(MigrationStatus::Completed)
        ->and($fresh->created_rows)->toBe(3)
        ->and($fresh->skipped_rows)->toBe(0);
});

it('warns and attaches nothing when a side is unresolved (self-heals on re-run)', function () {
    seedMigrationsConfig();
    $function = BusinessFunction::factory()->create(['old_id' => 1]);

    fakeUserBusinessFunctions([
        ['id' => 10, 'user_id' => 999, 'business_function_id' => 1],
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'user-business-functions']);

    runMigrationJobFor($run);

    expect($function->users()->count())->toBe(0);

    $fresh = $run->fresh();
    expect($fresh->created_rows)->toBe(0)
        ->and($fresh->skipped_rows)->toBe(1)
        ->and($fresh->report[0]['level'])->toBe('warning')
        ->and($fresh->report[0]['message'])->toContain('999');
});

it('re-importing the same associations is idempotent (skip, no duplicate pivot row)', function () {
    seedMigrationsConfig();
    $user = User::factory()->create(['old_id' => 501]);
    $function = BusinessFunction::factory()->create(['old_id' => 1]);

    fakeUserBusinessFunctions([
        ['id' => 10, 'user_id' => 501, 'business_function_id' => 1],
    ]);

    $actor = migrationsSuperAdminActor();
    $firstRun = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'user-business-functions']);
    runMigrationJobFor($firstRun);

    $secondRun = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'user-business-functions']);
    runMigrationJobFor($secondRun);

    expect($function->users()->count())->toBe(1)
        ->and($function->users()->pluck('users.id')->all())->toBe([$user->id]);

    $fresh = $secondRun->fresh();
    expect($fresh->skipped_rows)->toBe(1)
        ->and($fresh->created_rows)->toBe(0);
});

it('fails a junction row missing a required side, without blocking the others', function () {
    seedMigrationsConfig();
    $user = User::factory()->create(['old_id' => 501]);
    $function = BusinessFunction::factory()->create(['old_id' => 1]);

    fakeUserBusinessFunctions([
        ['id' => 10, 'user_id' => 501],
        ['id' => 11, 'user_id' => 501, 'business_function_id' => 1],
    ]);

    $actor = migrationsSuperAdminActor();
    $run = MigrationRun::factory()->create(['user_id' => $actor->id, 'source' => 'user-business-functions']);

    runMigrationJobFor($run);

    expect($function->users()->pluck('users.id')->all())->toBe([$user->id]);

    $fresh = $run->fresh();
    expect($fresh->created_rows)->toBe(1)
        ->and($fresh->failed_rows)->toBe(1)
        ->and($fresh->report[0]['level'])->toBe('error');
});
