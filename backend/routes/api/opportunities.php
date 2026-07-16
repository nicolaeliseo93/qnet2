<?php

use App\Http\Controllers\Opportunities\OpportunityController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Opportunities (spec 0040)
|--------------------------------------------------------------------------
|
| Extracted out of routes/api.php (file-size split, engineering.md §6 — the
| host file is already near the 500-line hard limit). Required from
| routes/api.php INSIDE the existing `auth:sanctum` group, so every route
| below inherits that same middleware/prefix context.
|
| No for-select for the opportunities resource itself (out of scope, spec
| 0040): no other module consumes an Opportunity as a select.
*/

// Opportunities CRUD. Authorization (opportunities.view/create/update/
// delete) is enforced server-side in OpportunityController via
// OpportunityPolicy on every endpoint.
Route::get('opportunities/{opportunity}', [OpportunityController::class, 'show']);
Route::post('opportunities', [OpportunityController::class, 'store']);
Route::match(['put', 'patch'], 'opportunities/{opportunity}', [OpportunityController::class, 'update']);
Route::delete('opportunities/{opportunity}', [OpportunityController::class, 'destroy']);
