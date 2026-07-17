<?php

use App\Http\Controllers\LeadStatuses\LeadStatusController;
use App\Http\Controllers\LeadStatuses\LeadStatusForSelectController;
use App\Http\Controllers\Sectors\SectorController;
use App\Http\Controllers\Sectors\SectorForSelectController;
use App\Http\Controllers\Sources\SourceController;
use App\Http\Controllers\Sources\SourceForSelectController;
use App\Http\Controllers\Tags\TagController;
use App\Http\Controllers\Tags\TagForSelectController;
use App\Http\Controllers\VatRates\VatRateController;
use App\Http\Controllers\VatRates\VatRateForSelectController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Standalone lookup-table routes
|--------------------------------------------------------------------------
|
| Extracted out of routes/api.php (file-size split, engineering.md §6):
| Sources (spec 0018), Tags (spec 0019), Sectors (spec 0018) and Lead
| statuses (spec 0029) are all thin CRUD (+ for-select where applicable)
| wrappers over the generic table/authorization framework, sharing no state
| with the rest of the API. Required from routes/api.php INSIDE the existing
| `auth:sanctum` group, so every route below inherits that same
| middleware/prefix context.
*/

// Sources CRUD (spec 0018): a standalone lookup used to classify the
// provenance of registry records ("Anagrafiche"). Authorization
// (sources.view/create/update/delete) is enforced server-side in
// SourceController via SourcePolicy on every endpoint.
// Minimal searchable/paginated source list for entity-backed selects
// (for-select standard, ADR 0011). Declared ABOVE sources/{source} so
// the literal `for-select` segment wins over the bound wildcard.
// Gated by sources.viewAny server-side in SourceForSelectController.
Route::get('sources/for-select', SourceForSelectController::class);

Route::get('sources/{source}', [SourceController::class, 'show']);
Route::post('sources', [SourceController::class, 'store']);
Route::match(['put', 'patch'], 'sources/{source}', [SourceController::class, 'update']);
Route::delete('sources/{source}', [SourceController::class, 'destroy']);

// Tags CRUD (spec 0019): a reusable, polymorphic lookup attached to any
// entity via the `taggables` pivot (first producer: Referents).
// Authorization (tags.view/create/update/delete) is enforced
// server-side in TagController via TagPolicy on every endpoint.
// Minimal searchable/paginated tag list for entity-backed selects
// (for-select standard, ADR 0011). Declared ABOVE tags/{tag} so the
// literal `for-select` segment wins over the bound wildcard. Gated
// by tags.viewAny server-side in TagForSelectController.
Route::get('tags/for-select', TagForSelectController::class);

Route::get('tags/{tag}', [TagController::class, 'show']);
Route::post('tags', [TagController::class, 'store']);
Route::match(['put', 'patch'], 'tags/{tag}', [TagController::class, 'update']);
Route::delete('tags/{tag}', [TagController::class, 'destroy']);

// Sectors: CRUD + the dedicated tree view (spec 0018) — a lookup used to
// classify Anagrafiche (spec 0020, "Settore EA / Competenze", multi via
// sector_registry). `tree` and `for-select` are declared ABOVE the plain
// `{sector}` show route so their literal segments win over the bound
// wildcard. Authorization (sectors.view/create/update/delete/viewAny) is
// enforced server-side in SectorController/SectorForSelectController via
// SectorPolicy.
Route::get('sectors/tree', [SectorController::class, 'tree']);

// Minimal searchable/paginated sector list for entity-backed selects
// (for-select standard, ADR 0011, spec 0020 — first producer: the
// Registries form). Gated by sectors.viewAny server-side in
// SectorForSelectController.
Route::get('sectors/for-select', SectorForSelectController::class);

Route::get('sectors/{sector}', [SectorController::class, 'show']);
Route::post('sectors', [SectorController::class, 'store']);
Route::match(['put', 'patch'], 'sectors/{sector}', [SectorController::class, 'update']);
Route::delete('sectors/{sector}', [SectorController::class, 'destroy']);

// Lead statuses CRUD (spec 0029): the Lead working-state pick-list (BR-3
// delete-guard lives in LeadStatusService). Authorization
// (lead-statuses.view/create/update/delete) is enforced server-side in
// LeadStatusController via LeadStatusPolicy.
// Minimal searchable/paginated list for entity-backed selects (ADR 0011).
// Declared ABOVE lead-statuses/{leadStatus} so the literal `for-select`
// segment wins over the bound wildcard. Gated by lead-statuses.viewAny
// server-side in LeadStatusForSelectController.
Route::get('lead-statuses/for-select', LeadStatusForSelectController::class);

// Custom-row resequencing (spec 0039, D-5): `sort_order` is server-managed,
// this is the only way to change it. Declared ABOVE the bound wildcard for
// the same literal-segment reason as `for-select`. Gated on
// lead-statuses.update directly in LeadStatusController::reorder.
Route::post('lead-statuses/reorder', [LeadStatusController::class, 'reorder']);

Route::get('lead-statuses/{leadStatus}', [LeadStatusController::class, 'show']);
Route::post('lead-statuses', [LeadStatusController::class, 'store']);
Route::match(['put', 'patch'], 'lead-statuses/{leadStatus}', [LeadStatusController::class, 'update']);
Route::delete('lead-statuses/{leadStatus}', [LeadStatusController::class, 'destroy']);

// VAT rates CRUD: a standalone lookup used to assign a VAT percentage to a
// Product. Authorization (vat-rates.view/create/update/delete) is enforced
// server-side in VatRateController via VatRatePolicy on every endpoint.
// Minimal searchable/paginated list for entity-backed selects (for-select
// standard, ADR 0011). Declared ABOVE vat-rates/{vatRate} so the literal
// `for-select` segment wins over the bound wildcard. Gated by
// vat-rates.viewAny server-side in VatRateForSelectController.
Route::get('vat-rates/for-select', VatRateForSelectController::class);

Route::get('vat-rates/{vatRate}', [VatRateController::class, 'show']);
Route::post('vat-rates', [VatRateController::class, 'store']);
Route::match(['put', 'patch'], 'vat-rates/{vatRate}', [VatRateController::class, 'update']);
Route::delete('vat-rates/{vatRate}', [VatRateController::class, 'destroy']);
