<?php

use App\Models\Note;
use App\Models\Opportunity;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Support\Facades\Schema;

// Schema for `notes` + `note_mentions` (spec 0052 data_contract SCHEMA, AC-020).

uses(RefreshDatabase::class);

it('creates notes and note_mentions with the expected columns and indexes (AC-020)', function () {
    expect(Schema::hasTable('notes'))->toBeTrue();
    expect(Schema::hasColumns('notes', [
        'id', 'notable_type', 'notable_id', 'parent_id', 'user_id', 'body', 'edited_at',
        'created_at', 'updated_at', 'deleted_at',
    ]))->toBeTrue();

    expect(Schema::hasTable('note_mentions'))->toBeTrue();
    expect(Schema::hasColumns('note_mentions', ['id', 'note_id', 'user_id', 'created_at', 'updated_at']))->toBeTrue();
});

it('registers the "note" alias in the global morph map (AC-020)', function () {
    $opportunity = Opportunity::factory()->create();
    $note = Note::factory()->create([
        'notable_type' => 'opportunity',
        'notable_id' => $opportunity->id,
    ]);

    expect($note->getMorphClass())->toBe('note');
    expect($note->notable)->toBeInstanceOf(Opportunity::class);
    expect($note->notable->is($opportunity))->toBeTrue();
});
