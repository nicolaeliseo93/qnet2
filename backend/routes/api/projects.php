<?php

use App\Http\Controllers\Campaigns\CampaignController;
use App\Http\Controllers\Geo\StateForSelectController;
use App\Http\Controllers\ProductCategories\ProductCategoryForSelectController;
use App\Http\Controllers\Projects\ProjectController;
use App\Http\Controllers\Projects\ProjectForSelectController;
use App\Http\Controllers\ProjectStatuses\ProjectStatusController;
use App\Http\Controllers\ProjectStatuses\ProjectStatusForSelectController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Projects / Campaigns / Project statuses (spec 0023)
|--------------------------------------------------------------------------
|
| Extracted out of routes/api.php (file-size split, engineering.md §6 — the
| host file is already at the 500-line hard limit). Required from
| routes/api.php INSIDE the existing `auth:sanctum` group, so every route
| below inherits that same middleware/prefix context.
|
| Also carries product-categories/for-select and states/for-select: both are
| lookups introduced BY this same spec (0023) for the Project/Campaign forms;
| their host blocks (product-categories CRUD, geo lookups) already live in
| routes/api.php and had no line budget left. Registering the literal
| for-select route here — since this file is required BEFORE those blocks
| further down routes/api.php — still guarantees it wins over their bound
| wildcard, exactly as if declared inline.
*/

// Project statuses CRUD (BR-4 delete-guard lives in ProjectStatusService).
// Authorization (project-statuses.view/create/update/delete) is enforced
// server-side in ProjectStatusController via ProjectStatusPolicy.
Route::middleware('throttle:60,1')->group(function () {
    // Minimal searchable/paginated list for entity-backed selects (ADR 0011).
    // Declared ABOVE project-statuses/{projectStatus} so the literal
    // `for-select` segment wins over the bound wildcard. Gated by
    // project-statuses.viewAny server-side in ProjectStatusForSelectController.
    Route::get('project-statuses/for-select', ProjectStatusForSelectController::class);

    Route::get('project-statuses/{projectStatus}', [ProjectStatusController::class, 'show']);
    Route::post('project-statuses', [ProjectStatusController::class, 'store']);
    Route::match(['put', 'patch'], 'project-statuses/{projectStatus}', [ProjectStatusController::class, 'update']);
    Route::delete('project-statuses/{projectStatus}', [ProjectStatusController::class, 'destroy']);
});

// Projects CRUD (BR-1 code generation, BR-5 delete-guard, BR-7 budget
// aggregates all live in ProjectService). Authorization (projects.view/
// create/update/delete) is enforced server-side in ProjectController via
// ProjectPolicy.
Route::middleware('throttle:60,1')->group(function () {
    // Minimal searchable/paginated list for entity-backed selects (ADR 0011),
    // carrying the Campaign form's default `meta`. Declared ABOVE
    // projects/{project} so the literal `for-select` segment wins over the
    // bound wildcard. Gated by projects.viewAny server-side in
    // ProjectForSelectController.
    Route::get('projects/for-select', ProjectForSelectController::class);

    Route::get('projects/{project}', [ProjectController::class, 'show']);
    Route::post('projects', [ProjectController::class, 'store']);
    Route::match(['put', 'patch'], 'projects/{project}', [ProjectController::class, 'update']);
    Route::delete('projects/{project}', [ProjectController::class, 'destroy']);
});

// Campaigns CRUD (BR-1 code generation, BR-2 classification derivation, BR-3
// budget guard all live in CampaignService). No for-select (not consumed by
// any other module in this spec). Authorization (campaigns.view/create/
// update/delete) is enforced server-side in CampaignController via
// CampaignPolicy.
Route::middleware('throttle:60,1')->group(function () {
    Route::get('campaigns/{campaign}', [CampaignController::class, 'show']);
    Route::post('campaigns', [CampaignController::class, 'store']);
    Route::match(['put', 'patch'], 'campaigns/{campaign}', [CampaignController::class, 'update']);
    Route::delete('campaigns/{campaign}', [CampaignController::class, 'destroy']);
});

// Product categories / states for-select (spec 0023) — see file docblock for
// why they are declared here instead of inline in their own blocks.
Route::middleware('throttle:60,1')->group(function () {
    Route::get('product-categories/for-select', ProductCategoryForSelectController::class);
    Route::get('states/for-select', StateForSelectController::class);
});
