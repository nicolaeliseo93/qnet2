<?php

use App\Http\Controllers\EaSectors\EaSectorController;
use App\Http\Controllers\EaSectors\EaSectorForSelectController;
use App\Http\Controllers\Sources\SourceController;
use App\Http\Controllers\Sources\SourceForSelectController;
use App\Http\Controllers\Tags\TagController;
use App\Http\Controllers\Tags\TagForSelectController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Standalone lookup-table routes
|--------------------------------------------------------------------------
|
| Extracted out of routes/api.php (file-size split, engineering.md §6):
| Sources (spec 0018), Tags (spec 0019) and EA sectors (spec 0018) are all
| thin CRUD (+ for-select where applicable) wrappers over the generic
| table/authorization framework, sharing no state with the rest of the API.
| Required from routes/api.php INSIDE the existing `auth:sanctum` group, so
| every route below inherits that same middleware/prefix context.
*/

// Sources CRUD (spec 0018): a standalone lookup used to classify the
// provenance of registry records ("Anagrafiche"). Authorization
// (sources.view/create/update/delete) is enforced server-side in
// SourceController via SourcePolicy on every endpoint.
Route::middleware('throttle:60,1')->group(function () {
    // Minimal searchable/paginated source list for entity-backed selects
    // (for-select standard, ADR 0011). Declared ABOVE sources/{source} so
    // the literal `for-select` segment wins over the bound wildcard.
    // Gated by sources.viewAny server-side in SourceForSelectController.
    Route::get('sources/for-select', SourceForSelectController::class);

    Route::get('sources/{source}', [SourceController::class, 'show']);
    Route::post('sources', [SourceController::class, 'store']);
    Route::match(['put', 'patch'], 'sources/{source}', [SourceController::class, 'update']);
    Route::delete('sources/{source}', [SourceController::class, 'destroy']);
});

// Tags CRUD (spec 0019): a reusable, polymorphic lookup attached to any
// entity via the `taggables` pivot (first producer: Referents).
// Authorization (tags.view/create/update/delete) is enforced
// server-side in TagController via TagPolicy on every endpoint.
Route::middleware('throttle:60,1')->group(function () {
    // Minimal searchable/paginated tag list for entity-backed selects
    // (for-select standard, ADR 0011). Declared ABOVE tags/{tag} so the
    // literal `for-select` segment wins over the bound wildcard. Gated
    // by tags.viewAny server-side in TagForSelectController.
    Route::get('tags/for-select', TagForSelectController::class);

    Route::get('tags/{tag}', [TagController::class, 'show']);
    Route::post('tags', [TagController::class, 'store']);
    Route::match(['put', 'patch'], 'tags/{tag}', [TagController::class, 'update']);
    Route::delete('tags/{tag}', [TagController::class, 'destroy']);
});

// EA sectors: CRUD + the dedicated tree view (spec 0018) — a lookup used to
// classify Anagrafiche (spec 0020, "Settore EA / Competenze", multi via
// ea_sector_registry). `tree` and `for-select` are declared ABOVE the plain
// `{eaSector}` show route so their literal segments win over the bound
// wildcard. Authorization (ea-sectors.view/create/update/delete/viewAny) is
// enforced server-side in EaSectorController/EaSectorForSelectController via
// EaSectorPolicy.
Route::middleware('throttle:60,1')->group(function () {
    Route::get('ea-sectors/tree', [EaSectorController::class, 'tree']);

    // Minimal searchable/paginated EA-sector list for entity-backed selects
    // (for-select standard, ADR 0011, spec 0020 — first producer: the
    // Registries form). Gated by ea-sectors.viewAny server-side in
    // EaSectorForSelectController.
    Route::get('ea-sectors/for-select', EaSectorForSelectController::class);

    Route::get('ea-sectors/{eaSector}', [EaSectorController::class, 'show']);
    Route::post('ea-sectors', [EaSectorController::class, 'store']);
    Route::match(['put', 'patch'], 'ea-sectors/{eaSector}', [EaSectorController::class, 'update']);
    Route::delete('ea-sectors/{eaSector}', [EaSectorController::class, 'destroy']);
});
