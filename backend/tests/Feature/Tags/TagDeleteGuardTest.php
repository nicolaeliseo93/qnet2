<?php

use App\Models\EaSector;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('tagUserWith')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function tagUserWith(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("tags.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("tags.{$ability}");
        }

        return $user;
    }
}

// ---------------------------------------------------------------------------
// AC-007 — delete guard: a tag attached to any record cannot be deleted
// ---------------------------------------------------------------------------

it('delete: 409 when the tag is attached to an ea sector, tag is NOT deleted', function () {
    $actor = tagUserWith(['delete']);
    $tag = Tag::factory()->create();
    $eaSector = EaSector::factory()->create();
    $eaSector->tags()->attach($tag->id);
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/tags/{$tag->id}")->assertStatus(409);

    $this->assertDatabaseHas('tags', ['id' => $tag->id]);
    $this->assertDatabaseHas('taggables', ['tag_id' => $tag->id, 'taggable_id' => $eaSector->id, 'taggable_type' => $eaSector->getMorphClass()]);
});

it('delete: 204 when the tag has no attached records', function () {
    $actor = tagUserWith(['delete']);
    $tag = Tag::factory()->create();
    Sanctum::actingAs($actor);

    $this->deleteJson("/api/tags/{$tag->id}")->assertNoContent();

    $this->assertDatabaseMissing('tags', ['id' => $tag->id]);
});
