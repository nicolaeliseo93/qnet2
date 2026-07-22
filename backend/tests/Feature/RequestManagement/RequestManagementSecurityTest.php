<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

uses(RefreshDatabase::class);

// ---------------------------------------------------------------------------
// AC-002 — permissions:sync creates the 8 standard request-management.*
// permissions (from RequestManagementPolicy extends BasePolicy) plus the
// custom `viewAll` ability, derived with NO seeder rows involved.
// ---------------------------------------------------------------------------

it('permissions:sync creates all 8 request-management.* permissions plus the custom viewAll ability (AC-002)', function () {
    $this->artisan('permissions:sync')->assertSuccessful();

    foreach (['viewAny', 'view', 'create', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
        expect(Permission::where('name', "request-management.{$ability}")->exists())->toBeTrue();
    }

    // BasePolicy::permission() concatenates "{resource}.{ability}" literally
    // (no camelCase->kebab conversion — see OpportunityPolicy::viewDocuments
    // -> "opportunities.viewDocuments"), so the custom ability generates
    // "request-management.viewAll", NOT "request-management.view-all".
    expect(Permission::where('name', 'request-management.viewAll')->exists())->toBeTrue();
    expect(Permission::where('name', 'request-management.view-all')->exists())->toBeFalse();
});
