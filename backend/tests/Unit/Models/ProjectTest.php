<?php

use App\Models\Campaign;
use App\Models\Concerns\LogsModelActivity;
use App\Models\PipelineStatus;
use App\Models\Project;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\QueryException;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Tests\TestCase;

// Touches the database (migrations, factories), so bind the full TestCase +
// RefreshDatabase explicitly (the default Pest binding only applies to the
// Feature suite — see tests/Pest.php).
uses(TestCase::class, RefreshDatabase::class);

// ---------------------------------------------------------------------------
// schema
// ---------------------------------------------------------------------------

it('creates the projects table with the expected columns', function () {
    expect(Schema::hasTable('projects'))->toBeTrue();
    expect(Schema::hasColumns('projects', [
        'id', 'code', 'name', 'description', 'pipeline_status_id',
        'business_function_id', 'state_id', 'product_category_id',
        'partner_id', 'start_date', 'end_date', 'total_budget', 'target_lead',
        'created_at', 'updated_at',
    ]))->toBeTrue();
    expect(Schema::hasColumn('projects', 'registry_id'))->toBeFalse();
    expect(Schema::hasColumn('projects', 'source_id'))->toBeFalse();
});

it('code is unique at the database level', function () {
    Project::factory()->create(['code' => 'PRJ-0001']);

    expect(fn () => Project::factory()->create(['code' => 'PRJ-0001']))
        ->toThrow(QueryException::class);
});

it('pipeline_status_id is required at the database level', function () {
    expect(fn () => DB::table('projects')->insert([
        'code' => 'PRJ-9999', 'name' => 'No Status', 'created_at' => now(), 'updated_at' => now(),
    ]))->toThrow(QueryException::class);
});

it('down() reverses the migration, up() recreates it', function () {
    $migration = require database_path('migrations/2026_07_13_110100_create_projects_table.php');

    $migration->down();

    expect(Schema::hasTable('projects'))->toBeFalse();

    $migration->up();

    expect(Schema::hasTable('projects'))->toBeTrue();
});

// ---------------------------------------------------------------------------
// model relations and casts
// ---------------------------------------------------------------------------

it('pipelineStatus() is a BelongsTo relation to PipelineStatus', function () {
    $relation = (new Project)->pipelineStatus();

    expect($relation)->toBeInstanceOf(BelongsTo::class);
    expect($relation->getRelated())->toBeInstanceOf(PipelineStatus::class);
});

it('campaigns() is a HasMany relation to Campaign', function () {
    $relation = (new Project)->campaigns();

    expect($relation)->toBeInstanceOf(HasMany::class);
    expect($relation->getRelated())->toBeInstanceOf(Campaign::class);
});

it('casts total_budget to decimal:2 and target_lead to int', function () {
    $project = Project::factory()->create(['total_budget' => '123.4', 'target_lead' => '10']);

    expect($project->total_budget)->toBe('123.40')
        ->and($project->target_lead)->toBeInt()->toBe(10);
});

it('`code` is deliberately absent from #[Fillable]: mass-assignment cannot set it', function () {
    $status = PipelineStatus::factory()->create();
    $project = new Project([
        'name' => 'Guarded',
        'pipeline_status_id' => $status->id,
        'code' => 'SHOULD-NOT-STICK',
    ]);

    expect($project->code)->toBeNull();
});

it('logs model activity on the projects log channel', function () {
    expect(class_uses(Project::class))->toHaveKey(LogsModelActivity::class);
});
