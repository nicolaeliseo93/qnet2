<?php

use App\Models\Attachment;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('userWithAttachmentAbilities')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function userWithAttachmentAbilities(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete'] as $ability) {
            Permission::findOrCreate("attachments.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("attachments.{$ability}");
        }

        return $user;
    }
}

beforeEach(function () {
    Storage::fake('local');
});

// ---------------------------------------------------------------------------
// index — GET /api/attachments
// ---------------------------------------------------------------------------

it('index: 200 lists only the given owner\'s attachments in the given collection, newest first', function () {
    $actor = userWithAttachmentAbilities(['viewAny']);
    Sanctum::actingAs($actor);

    $owner = User::factory()->create();
    $otherOwner = User::factory()->create();

    $older = Attachment::factory()->for($owner, 'attachable')->create(['collection' => 'documents', 'created_at' => now()->subMinute()]);
    $newer = Attachment::factory()->for($owner, 'attachable')->create(['collection' => 'documents', 'created_at' => now()]);
    Attachment::factory()->for($owner, 'attachable')->create(['collection' => 'scans']); // different collection
    Attachment::factory()->for($otherOwner, 'attachable')->create(['collection' => 'documents']); // different owner

    $response = $this->getJson('/api/attachments?'.http_build_query([
        'attachable_type' => 'user',
        'attachable_id' => $owner->id,
        'collection' => 'documents',
    ]))->assertOk()->assertJsonPath('success', true);

    $ids = collect($response->json('data'))->pluck('id')->all();
    expect($ids)->toBe([$newer->id, $older->id]);
});

it('index: 200 with an empty list when the owner has no attachments', function () {
    $actor = userWithAttachmentAbilities(['viewAny']);
    Sanctum::actingAs($actor);
    $owner = User::factory()->create();

    $this->getJson('/api/attachments?'.http_build_query([
        'attachable_type' => 'user',
        'attachable_id' => $owner->id,
    ]))->assertOk()->assertJsonPath('data', []);
});

it('index: 403 without attachments.viewAny', function () {
    $actor = userWithAttachmentAbilities([]);
    Sanctum::actingAs($actor);
    $owner = User::factory()->create();

    $this->getJson('/api/attachments?'.http_build_query([
        'attachable_type' => 'user',
        'attachable_id' => $owner->id,
    ]))->assertForbidden();
});

it('index: 422 for a non-allowlisted attachable_type', function () {
    $actor = userWithAttachmentAbilities(['viewAny']);
    Sanctum::actingAs($actor);

    $this->getJson('/api/attachments?'.http_build_query([
        'attachable_type' => 'role',
        'attachable_id' => 1,
    ]))->assertUnprocessable()->assertJsonValidationErrors('attachable_type');
});

// ---------------------------------------------------------------------------
// view — GET /api/attachments/{attachment}/view
// ---------------------------------------------------------------------------

it('view: 200 streams the file inline (not a forced download)', function () {
    $actor = userWithAttachmentAbilities(['create', 'view']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/attachments', [
        'file' => UploadedFile::fake()->create('report.pdf', 16, 'application/pdf'),
    ])->assertCreated();

    $attachment = Attachment::firstOrFail();

    $response = $this->get("/api/attachments/{$attachment->id}/view")->assertOk();

    expect($response->headers->get('Content-Disposition'))->toContain('inline');
});

it('view: 403 without attachments.view', function () {
    $actor = userWithAttachmentAbilities([]);
    Sanctum::actingAs($actor);
    $attachment = Attachment::factory()->create();

    $this->get("/api/attachments/{$attachment->id}/view")->assertForbidden();
});
