<?php

use App\Models\Attachment;
use App\Models\CompanySite;
use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

if (! function_exists('userWithCompanySiteAbilities')) {
    /**
     * @param  array<int, string>  $abilities
     */
    function userWithCompanySiteAbilities(array $abilities): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("company-sites.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("company-sites.{$ability}");
        }

        return $user;
    }
}

beforeEach(function () {
    Storage::fake('local');
});

it('company-sites/{id}/logo: uploads the logo and exposes its data-URI', function () {
    $actor = userWithCompanySiteAbilities(['update']);
    $target = CompanySite::factory()->create();
    Sanctum::actingAs($actor);

    $response = $this->postJson("/api/company-sites/{$target->id}/logo", [
        'logo' => UploadedFile::fake()->image('logo.png', 200, 200),
    ])->assertOk()->assertJsonPath('success', true);

    $url = $response->json('data.logo_url');
    expect($url)->toStartWith('data:image/')->and($url)->toContain(';base64,');

    expect($target->fresh()->logo)->not->toBeNull();
    Storage::disk('local')->assertExists($target->fresh()->logo->path);
});

it('company-sites/{id}/logo: replacing keeps a single logo (old file removed)', function () {
    $actor = userWithCompanySiteAbilities(['update']);
    $target = CompanySite::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson("/api/company-sites/{$target->id}/logo", ['logo' => UploadedFile::fake()->image('one.png')])->assertOk();
    $first = $target->fresh()->logo;

    $this->postJson("/api/company-sites/{$target->id}/logo", ['logo' => UploadedFile::fake()->image('two.png')])->assertOk();

    expect(Attachment::where('attachable_id', $target->id)->where('collection', CompanySite::LOGO_COLLECTION)->count())->toBe(1);
    Storage::disk('local')->assertMissing($first->path);
});

it('company-sites/{id}/logo: rejects a non-image file', function () {
    $actor = userWithCompanySiteAbilities(['update']);
    $target = CompanySite::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson("/api/company-sites/{$target->id}/logo", [
        'logo' => UploadedFile::fake()->create('document.pdf', 16, 'application/pdf'),
    ])->assertUnprocessable()->assertJsonValidationErrors('logo');
});

it('company-sites/{id}/logo: 403 without company-sites.update', function () {
    $actor = userWithCompanySiteAbilities([]);
    $target = CompanySite::factory()->create();
    Sanctum::actingAs($actor);

    $this->postJson("/api/company-sites/{$target->id}/logo", ['logo' => UploadedFile::fake()->image('x.png')])
        ->assertForbidden();
});

it('company-sites/{id}/logo: removes the logo', function () {
    $actor = userWithCompanySiteAbilities(['update']);
    $target = CompanySite::factory()->create();
    Sanctum::actingAs($actor);
    $this->postJson("/api/company-sites/{$target->id}/logo", ['logo' => UploadedFile::fake()->image('logo.png')])->assertOk();
    $path = $target->fresh()->logo->path;

    $this->deleteJson("/api/company-sites/{$target->id}/logo")
        ->assertOk()
        ->assertJsonPath('data.logo_url', null);

    expect($target->fresh()->logo)->toBeNull();
    Storage::disk('local')->assertMissing($path);
});
