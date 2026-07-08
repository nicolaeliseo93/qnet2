<?php

use App\Http\Controllers\Registries\RegistryController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Registries CRUD (spec 0020, "Anagrafiche")
|--------------------------------------------------------------------------
|
| Extracted out of routes/api.php (file-size split, engineering.md §6): a
| client/supplier record reusing the `users`/`referents` anagraphic stack
| (personal-data card + contacts + addresses) unchanged via HasPersonalData.
| No for-select (out of scope — no module selects a registry in this spec).
| Authorization (registries.view/create/update/delete) is enforced
| server-side in RegistryController via RegistryPolicy on every endpoint.
| Required from routes/api.php INSIDE the existing `auth:sanctum` group, so
| every route below inherits that same middleware/prefix context.
*/

Route::middleware('throttle:60,1')->group(function () {
    Route::get('registries/{registry}', [RegistryController::class, 'show']);
    Route::post('registries', [RegistryController::class, 'store']);
    Route::match(['put', 'patch'], 'registries/{registry}', [RegistryController::class, 'update']);
    Route::delete('registries/{registry}', [RegistryController::class, 'destroy']);
});
