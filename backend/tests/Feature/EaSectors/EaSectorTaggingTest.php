<?php

use App\Models\EaSector;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('eaSectorUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function eaSectorUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("ea-sectors.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("ea-sectors.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-008 — create with tag_ids
// ---------------------------------------------------------------------------

it('create: with tag_ids attaches the pivot rows and EaSectorResource returns tags', function () {
    $actor = eaSectorUserWith(['create']);
    $tags = Tag::factory()->count(2)->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson('/api/ea-sectors', [
        'name' => 'Energy',
        'tag_ids' => $tags->pluck('id')->all(),
    ])->assertCreated();

    $sectorId = $response->json('data.id');
    expect(collect($response->json('data.tags'))->pluck('id')->all())->toEqualCanonicalizing($tags->pluck('id')->all())
        ->and($response->json('data.tag_ids'))->toEqualCanonicalizing($tags->pluck('id')->all());

    foreach ($tags as $tag) {
        $this->assertDatabaseHas('taggables', [
            'tag_id' => $tag->id,
            'taggable_id' => $sectorId,
            'taggable_type' => 'ea_sector',
        ]);
    }
});

it('create: without tag_ids returns an empty tags array', function () {
    $actor = eaSectorUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/ea-sectors', ['name' => 'Energy'])
        ->assertCreated()
        ->assertJsonPath('data.tags', [])
        ->assertJsonPath('data.tag_ids', []);
});

it('create: 422 when a tag_id does not exist', function () {
    $actor = eaSectorUserWith(['create']);
    Sanctum::actingAs($actor);

    $this->postJson('/api/ea-sectors', ['name' => 'Energy', 'tag_ids' => [999999]])
        ->assertStatus(422)->assertJsonValidationErrors('tag_ids.0');
});

// ---------------------------------------------------------------------------
// AC-008 — update with tag_ids (full replace via sync)
// ---------------------------------------------------------------------------

it('update: tag_ids replaces the current tag set', function () {
    $actor = eaSectorUserWith(['update']);
    $sector = EaSector::factory()->create();
    $oldTag = Tag::factory()->create();
    $newTag = Tag::factory()->create();
    $sector->tags()->attach($oldTag->id);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/ea-sectors/{$sector->id}", ['tag_ids' => [$newTag->id]])
        ->assertOk()
        ->assertJsonPath('data.tags.0.id', $newTag->id)
        ->assertJsonPath('data.tag_ids', [$newTag->id]);

    $this->assertDatabaseMissing('taggables', ['tag_id' => $oldTag->id, 'taggable_id' => $sector->id]);
    $this->assertDatabaseHas('taggables', ['tag_id' => $newTag->id, 'taggable_id' => $sector->id, 'taggable_type' => 'ea_sector']);
});

it('update: omitting tag_ids leaves the current tags untouched', function () {
    $actor = eaSectorUserWith(['update']);
    $sector = EaSector::factory()->create(['name' => 'Old']);
    $tag = Tag::factory()->create();
    $sector->tags()->attach($tag->id);
    Sanctum::actingAs($actor);

    $this->patchJson("/api/ea-sectors/{$sector->id}", ['name' => 'New'])
        ->assertOk()
        ->assertJsonPath('data.name', 'New')
        ->assertJsonPath('data.tags.0.id', $tag->id);

    $this->assertDatabaseHas('taggables', ['tag_id' => $tag->id, 'taggable_id' => $sector->id]);
});

// ---------------------------------------------------------------------------
// AC-008 — deleting an ea sector detaches its tags (no orphan pivot rows)
// ---------------------------------------------------------------------------

it('delete: removes the ea sector and detaches its tags', function () {
    $actor = eaSectorUserWith(['delete']);
    $sector = EaSector::factory()->create();
    $tag = Tag::factory()->create();
    $sector->tags()->attach($tag->id);
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/ea-sectors/{$sector->id}")->assertNoContent();

    $this->assertDatabaseMissing('taggables', ['taggable_id' => $sector->id, 'taggable_type' => 'ea_sector']);
    $this->assertDatabaseHas('tags', ['id' => $tag->id]);
});
