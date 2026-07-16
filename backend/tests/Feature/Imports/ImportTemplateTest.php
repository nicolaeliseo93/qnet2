<?php

use App\Models\User;
use Illuminate\Foundation\Testing\RefreshDatabase;
use Laravel\Sanctum\Sanctum;
use Spatie\Permission\Models\Permission;
use Tests\Stubs\StubImportDefinition;

uses(RefreshDatabase::class);

if (! function_exists('stubImportActorWith')) {
    /**
     * @param  array<int, string>  $abilities
     * @param  array<int, string>  $importRunAbilities  the `import-runs.*` MODULE
     *                                                  abilities (spec 0034), independent of the
     *                                                  domain `business-functions.*` ones above
     */
    function stubImportActorWith(array $abilities, array $importRunAbilities = []): User
    {
        foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import'] as $ability) {
            Permission::findOrCreate("business-functions.{$ability}");
        }

        $user = User::factory()->create();

        foreach ($abilities as $ability) {
            $user->givePermissionTo("business-functions.{$ability}");
        }

        grantImportRunsPermissions($user, $importRunAbilities);

        return $user;
    }
}

if (! function_exists('registerStubImportDomain')) {
    function registerStubImportDomain(): void
    {
        config(['imports.definitions' => ['stub-widgets' => StubImportDefinition::class]]);
    }
}

// ---------------------------------------------------------------------------
// AC-006 — GET /api/imports/{domain}/template
// ---------------------------------------------------------------------------

it('downloads a CSV template with the header = declared columns, in order', function () {
    registerStubImportDomain();
    $actor = stubImportActorWith(['import'], ['create']);
    Sanctum::actingAs($actor);

    $response = $this->get('/api/imports/stub-widgets/template')->assertOk();

    expect($response->headers->get('Content-Type'))->toContain('text/csv')
        ->and($response->headers->get('Content-Disposition'))->toContain('stub-widgets-import-template.csv')
        ->and($response->streamedContent())->toBe("name,type\n");
});

it('403 without {resource}.import', function () {
    registerStubImportDomain();
    $actor = stubImportActorWith([]);
    Sanctum::actingAs($actor);

    $this->get('/api/imports/stub-widgets/template')->assertForbidden();
});

it('404 for an unregistered domain', function () {
    registerStubImportDomain();
    $actor = stubImportActorWith(['import']);
    Sanctum::actingAs($actor);

    $this->get('/api/imports/unknown-domain/template')->assertNotFound();
});
