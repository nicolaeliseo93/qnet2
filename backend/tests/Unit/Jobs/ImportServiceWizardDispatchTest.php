<?php

use App\Enums\ImportStatus;
use App\Jobs\AnalyzeImportJob;
use App\Jobs\ProcessStagedImportJob;
use App\Jobs\StageImportJob;
use App\Models\ImportRun;
use App\Models\User;
use App\Services\ImportService;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;
use Tests\TestCase;
use Tests\Unit\Jobs\Fixtures\FakeWizardImportDefinition;

uses(TestCase::class, RefreshDatabase::class);

// ---------------------------------------------------------------------------
// ImportService wizard entry points (new methods, additive to spec 0012):
// startAnalyze()/configure()/confirmStaged() drive the analyze -> configure
// -> stage -> review -> confirm state machine, mirroring start()/confirm()'s
// existing dispatch-and-guard shape.
// ---------------------------------------------------------------------------

it('startAnalyze() stores the file, creates an analyzing run, dispatches AnalyzeImportJob', function () {
    Storage::fake('local');
    Queue::fake();

    $actor = User::factory()->create();
    $definition = app(FakeWizardImportDefinition::class);
    $file = UploadedFile::fake()->createWithContent('leads.csv', "Full Name,Email\nMario Rossi,mario@test.com\n");

    $run = app(ImportService::class)->startAnalyze($actor, $definition, $file);

    expect($run->status)->toBe(ImportStatus::Analyzing)
        ->and($run->resource)->toBe('wizard-widgets')
        ->and($run->user_id)->toBe($actor->id)
        ->and($run->original_filename)->toBe('leads.csv');

    Storage::disk('local')->assertExists($run->stored_path);
    Queue::assertPushed(AnalyzeImportJob::class, 1);
});

it('configure() persists mapping/config/strategy, moves to staging, dispatches StageImportJob', function () {
    Queue::fake();

    $run = ImportRun::factory()->create(['resource' => 'wizard-widgets', 'status' => ImportStatus::Configuring]);

    $updated = app(ImportService::class)->configure(
        $run,
        ['Full Name' => 'full_name', 'Email' => 'email'],
        ['note' => 'batch 1'],
        'create_new',
    );

    expect($updated->status)->toBe(ImportStatus::Staging)
        ->and($updated->column_mapping)->toBe(['Full Name' => 'full_name', 'Email' => 'email'])
        ->and($updated->global_config)->toBe(['note' => 'batch 1'])
        ->and($updated->dedup_strategy)->toBe('create_new');

    Queue::assertPushed(StageImportJob::class, 1);
});

it('configure() rejects a run that is not configuring', function () {
    Queue::fake();
    $run = ImportRun::factory()->create(['resource' => 'wizard-widgets', 'status' => ImportStatus::Analyzing]);

    try {
        app(ImportService::class)->configure($run, [], [], 'create_new');
        $this->fail('Expected a 422 abort.');
    } catch (Throwable $exception) {
        expect($exception->getStatusCode())->toBe(422);
    }

    Queue::assertNotPushed(StageImportJob::class);
});

it('confirmStaged() moves a reviewing run to processing and dispatches ProcessStagedImportJob', function () {
    Queue::fake();
    $run = ImportRun::factory()->create(['resource' => 'wizard-widgets', 'status' => ImportStatus::Reviewing]);

    $updated = app(ImportService::class)->confirmStaged($run);

    expect($updated->status)->toBe(ImportStatus::Processing);
    Queue::assertPushed(ProcessStagedImportJob::class, 1);
});

it('confirmStaged() rejects a run that is not reviewing', function () {
    Queue::fake();
    $run = ImportRun::factory()->create(['resource' => 'wizard-widgets', 'status' => ImportStatus::Staging]);

    try {
        app(ImportService::class)->confirmStaged($run);
        $this->fail('Expected a 422 abort.');
    } catch (Throwable $exception) {
        expect($exception->getStatusCode())->toBe(422);
    }

    Queue::assertNotPushed(ProcessStagedImportJob::class);
});
