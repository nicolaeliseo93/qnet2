<?php

use App\Http\Controllers\RequestManagement\RequestManagementController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Request Management work panel (spec 0049)
|--------------------------------------------------------------------------
|
| Dedicated show/update endpoints for the operative "Gestione Richieste"
| panel: the record IS an Opportunity (D-1), but authorization/scoping run
| through `request-management.*` (RequestManagementPolicy/Scope), never the
| opportunities.* CRUD (PATCH /api/opportunities/{id}). Required from routes/api.php
| INSIDE the existing `auth:sanctum` group, so both routes inherit that
| middleware/prefix context. No list/export/activity route here: those are
| served by the generic tables/exports/activity-log framework (ADR 0002).
*/
Route::get('request-management/{opportunity}', [RequestManagementController::class, 'show']);
Route::match(['put', 'patch'], 'request-management/{opportunity}', [RequestManagementController::class, 'update']);
