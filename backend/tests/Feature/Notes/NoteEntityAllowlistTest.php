<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;

// D-9 allow-list boundary: an unregistered `entity_type` is rejected by
// FormRequest validation (Rule::in(NoteEntityRegistry::registeredTypes()))
// BEFORE the controller/service ever resolves a model — no query on the
// indicated class ever runs (AC-022).

uses(RefreshDatabase::class);

it('GET /api/notes with an unregistered entity_type -> 422 (AC-022)', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/notes?'.http_build_query(['entity_type' => 'leads', 'entity_id' => 1]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('entity_type');
});

it('POST /api/notes with an unregistered entity_type -> 422 (AC-022)', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->postJson('/api/notes', [
        'entity_type' => 'App\\Models\\User',
        'entity_id' => 1,
        'body' => 'hello',
    ])->assertStatus(422)->assertJsonValidationErrors('entity_type');
});

it('GET /api/notes/mentionable-users with an unregistered entity_type -> 422 (AC-022)', function () {
    Sanctum::actingAs(User::factory()->create());

    $this->getJson('/api/notes/mentionable-users?'.http_build_query(['entity_type' => 'leads', 'entity_id' => 1]))
        ->assertStatus(422)
        ->assertJsonValidationErrors('entity_type');
});
