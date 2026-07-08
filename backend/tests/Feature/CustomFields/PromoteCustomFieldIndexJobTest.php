<?php

declare(strict_types=1);

use App\CustomFields\CustomFieldIndexDdlBuilder;
use App\DataObjects\CustomFields\UpdateCustomFieldData;
use App\Jobs\PromoteCustomFieldIndexJob;
use App\Models\CustomFieldDefinition;
use App\Models\CustomFieldValue;
use App\Services\CustomFieldService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Schema;

// spec 0021 — T15 (AC-021): the DDL itself (STORED generated column + B-tree
// index / multi-valued index) is MySQL/MariaDB-only and cannot be executed
// against the sqlite test database (different generated-column/index
// semantics). What IS asserted here, on sqlite:
//   1. the driver guard makes the job a safe no-op (no exception, no schema
//      change) when the connection is not mysql/mariadb;
//   2. is_indexed false->true dispatches the job to the queue (see
//      CustomFieldAdminCrudTest for the Queue::fake() assertion against the
//      admin PATCH endpoint — this file exercises the SERVICE-level path via
//      CustomFieldService::update() directly);
//   3. the job resolves a definition that no longer wants indexing (deleted,
//      or toggled back off) as a no-op.
// The generated-column expression / DDL string builder itself is unit-tested
// in isolation in tests/Unit/CustomFields/CustomFieldIndexDdlBuilderTest.php
// (pure string assertions, no DB). Actual index USE by MySQL's optimizer
// (EXPLAIN) is a prod-time check — see PromoteCustomFieldIndexJob's docblock.
uses(RefreshDatabase::class);

it('is a safe no-op on sqlite: no exception, no schema change, logs the skip', function (): void {
    Log::spy();
    $definition = CustomFieldDefinition::factory()->forEntity('companies')->ofType('integer')->create([
        'key' => 'headcount',
        'is_indexed' => true,
    ]);

    (new PromoteCustomFieldIndexJob($definition->id))->handle(new CustomFieldIndexDdlBuilder);

    expect(Schema::hasColumn('custom_field_values', 'cfg_headcount'))->toBeFalse();
    Log::shouldHaveReceived('info')->withArgs(
        fn (string $message): bool => str_contains($message, 'no-op on non-MySQL driver'),
    )->once();
});

it('no-ops when the definition no longer exists (deleted between dispatch and run)', function (): void {
    $definition = CustomFieldDefinition::factory()->forEntity('companies')->ofType('text')->create(['is_indexed' => true]);
    $id = $definition->id;
    $definition->delete();

    expect(fn () => (new PromoteCustomFieldIndexJob($id))->handle(new CustomFieldIndexDdlBuilder))
        ->not->toThrow(Throwable::class);
});

it('no-ops when is_indexed was toggled back to false before the job ran', function (): void {
    $definition = CustomFieldDefinition::factory()->forEntity('companies')->ofType('text')->create(['is_indexed' => true]);
    $definition->update(['is_indexed' => false]);

    expect(fn () => (new PromoteCustomFieldIndexJob($definition->id))->handle(new CustomFieldIndexDdlBuilder))
        ->not->toThrow(Throwable::class);
});

it('CustomFieldService::update dispatches PromoteCustomFieldIndexJob to the queue on false->true, once', function (): void {
    Queue::fake();
    $definition = CustomFieldDefinition::factory()->forEntity('companies')->ofType('text')->create(['is_indexed' => false]);

    app(CustomFieldService::class)->update($definition, UpdateCustomFieldData::fromValidated(['is_indexed' => true]));

    Queue::assertPushed(
        PromoteCustomFieldIndexJob::class,
        fn (PromoteCustomFieldIndexJob $job): bool => $job->definitionId() === $definition->id,
    );
    Queue::assertPushed(PromoteCustomFieldIndexJob::class, 1);
});

it('rollback() is a safe no-op on sqlite too', function (): void {
    $definition = CustomFieldDefinition::factory()->forEntity('companies')->ofType('text')->create(['is_indexed' => false]);

    expect(fn () => (new PromoteCustomFieldIndexJob($definition->id))->rollback(new CustomFieldIndexDdlBuilder, $definition))
        ->not->toThrow(Throwable::class);
});

afterEach(function (): void {
    CustomFieldValue::query()->delete();
});
