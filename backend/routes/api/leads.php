<?php

use App\Http\Controllers\Leads\LeadController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Leads (spec 0024)
|--------------------------------------------------------------------------
|
| Extracted out of routes/api.php (file-size split, engineering.md §6 — the
| host file is already at the 500-line hard limit). Required from
| routes/api.php INSIDE the existing `auth:sanctum` group, so every route
| below inherits that same middleware/prefix context.
|
| No for-select for the leads resource itself (out of scope, spec 0024): no
| other module consumes a Lead as a select.
*/

// Leads CRUD. Authorization (leads.view/create/update/delete) is enforced
// server-side in LeadController via LeadPolicy on every endpoint.
Route::middleware('throttle:60,1')->group(function () {
    Route::get('leads/{lead}', [LeadController::class, 'show']);
    Route::post('leads', [LeadController::class, 'store']);
    Route::match(['put', 'patch'], 'leads/{lead}', [LeadController::class, 'update']);
    Route::delete('leads/{lead}', [LeadController::class, 'destroy']);
});
