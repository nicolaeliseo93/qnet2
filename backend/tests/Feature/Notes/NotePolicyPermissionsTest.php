<?php

use Illuminate\Foundation\Testing\RefreshDatabase;
use Spatie\Permission\Models\Permission;

// D-6: NotePolicy::abilities() is REDUCED to ['create'] — BasePolicy::
// permissions() is late-static-bound (static::abilities()), so overriding
// abilities() this way is enough for SyncPermissions to derive ONLY
// `notes.create`, never the 8 standard viewAny/view/update/delete/export/
// import/viewActivity (unused: reads are gated by the host entity via
// NoteEntityRegistry, writes by ownership — see NotePolicy::update/delete).

uses(RefreshDatabase::class);

it('permissions:sync creates ONLY notes.create, none of the 8 standard abilities (D-6)', function () {
    $this->artisan('permissions:sync')->assertSuccessful();

    expect(Permission::where('name', 'notes.create')->exists())->toBeTrue();

    foreach (['viewAny', 'view', 'update', 'delete', 'export', 'import', 'viewActivity'] as $ability) {
        expect(Permission::where('name', "notes.{$ability}")->exists())->toBeFalse();
    }
});
