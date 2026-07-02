<?php

use App\Http\Controllers\Addresses\AddressController;
use App\Http\Controllers\Attachments\AttachmentController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Config\ConfigController;
use App\Http\Controllers\Contacts\ContactController;
use App\Http\Controllers\Geo\GeoController;
use App\Http\Controllers\Navigation\NavigationController;
use App\Http\Controllers\Notifications\NotificationController;
use App\Http\Controllers\PersonalData\PersonalDataController;
use App\Http\Controllers\Roles\RoleController;
use App\Http\Controllers\Roles\RoleForSelectController;
use App\Http\Controllers\Table\TableController;
use App\Http\Controllers\Users\UserController;
use App\Http\Controllers\Users\UserForSelectController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API Routes
|--------------------------------------------------------------------------
|
| Backend API-only. Tutte le rotte sono prefissate con /api.
| Health check disponibile su GET /up (configurato in bootstrap/app.php).
|
*/

// PUBLIC application bootstrap (unauthenticated). Serves non-sensitive
// presentation metadata (domain enum options) the frontend needs before login.
// The exposed surface is a fixed server-side allowlist (config/config.php),
// never request input, so no arbitrary class can be reflected. Rate-limited to
// bound abuse of the public endpoint. See ADR 0008.
Route::middleware('throttle:30,1')->group(function () {
    Route::get('config', [ConfigController::class, 'index']);
});

Route::prefix('auth')->group(function () {
    Route::post('login', [AuthController::class, 'login']);

    // Public password reset flow, rate-limited against abuse.
    Route::middleware('throttle:6,1')->group(function () {
        Route::post('forgot-password', [AuthController::class, 'forgotPassword']);
        Route::post('reset-password', [AuthController::class, 'resetPassword']);
    });

    Route::middleware('auth:sanctum')->group(function () {
        Route::post('logout', [AuthController::class, 'logout']);
        Route::post('refresh', [AuthController::class, 'refresh']);
        Route::get('me', [AuthController::class, 'me']);
        Route::get('me/abilities', [AuthController::class, 'abilities']);

        // Self-service profile write (settings page). Rate-limited like the rest
        // of the authenticated write modules (throttle:60,1) to bound abuse.
        Route::middleware('throttle:60,1')->patch('me', [AuthController::class, 'updateProfile']);

        // Password change is a sensitive credential operation: a stricter limit
        // (throttle:6,1) matches the public password-reset flow above.
        Route::middleware('throttle:6,1')->put('me/password', [AuthController::class, 'updatePassword']);

        // Self-service avatar (settings page): any authenticated user manages
        // their own avatar; no extra permission required.
        Route::post('me/avatar', [AuthController::class, 'uploadAvatar']);
        Route::delete('me/avatar', [AuthController::class, 'deleteAvatar']);
    });
});

Route::middleware('auth:sanctum')->group(function () {
    // Level 0 — backend-driven navigation.
    Route::get('navigation', [NavigationController::class, 'index']);

    // Generic, domain-driven DataTable framework (AG Grid SSRM). One pair of
    // endpoints serves every domain; {domain} selects the TableDefinition from
    // the TableRegistry (config/tables.php), unknown domain → 404. See
    // docs/api/0002-generic-tables.md. Authorization (the definition's viewAny)
    // is enforced server-side in TableController on both endpoints.
    // Rate-limited to bound SSRM query load against abuse.
    Route::middleware('throttle:60,1')->group(function () {
        Route::get('tables/{domain}/columns', [TableController::class, 'columns']);
        Route::post('tables/{domain}/rows', [TableController::class, 'rows']);

        // Per-user column preferences (order/width/visibility): self-scoped to the
        // authenticated user, gated by the same definition viewAny. Save upserts a
        // sparse delta; delete resets to the PHP default. See ADR-0004 /
        // docs/api/0003-table-preferences.md.
        Route::post('tables/{domain}/preferences', [TableController::class, 'savePreferences']);
        Route::delete('tables/{domain}/preferences', [TableController::class, 'resetPreferences']);
    });

    // Users CRUD backing the table row-actions (view/edit/delete) + create.
    // Authorization (users.view/create/update/delete) is enforced server-side
    // in UserController via the UserPolicy on every endpoint.
    Route::middleware('throttle:60,1')->group(function () {
        // Minimal searchable/paginated user list for entity-backed selects
        // (for-select standard, ADR 0011). Declared ABOVE users/{user} so the
        // literal `for-select` segment wins over the bound {user} wildcard.
        // Gated by users.viewAny server-side in UserForSelectController.
        Route::get('users/for-select', UserForSelectController::class);

        Route::get('users/{user}', [UserController::class, 'show']);
        Route::post('users', [UserController::class, 'store']);
        Route::match(['put', 'patch'], 'users/{user}', [UserController::class, 'update']);
        Route::delete('users/{user}', [UserController::class, 'destroy']);

        // Admin avatar management on the user form. Gated by `users.update`
        // server-side (see UserController), same as editing the user.
        Route::post('users/{user}/avatar', [UserController::class, 'uploadAvatar']);
        Route::delete('users/{user}/avatar', [UserController::class, 'deleteAvatar']);
    });

    // Roles CRUD backing the table row-actions (view/edit/delete) + create.
    // Authorization (roles.view/create/update/delete) is enforced server-side
    // in RoleController via the RolePolicy on every endpoint. The permission
    // catalogue shown in the role form is served by the generic table config
    // (GET /api/tables/roles/columns → the `permissions` set options), so no
    // dedicated permissions endpoint is needed.
    Route::middleware('throttle:60,1')->group(function () {
        // Minimal searchable/paginated role list for entity-backed selects
        // (for-select standard, ADR 0011) — feeds the user-form role multi-select.
        // Declared ABOVE roles/{role} so the literal `for-select` segment wins
        // over the bound {role} wildcard. Gated by roles.viewAny server-side in
        // RoleForSelectController; options are actor-scoped to assignable roles.
        Route::get('roles/for-select', RoleForSelectController::class);

        Route::get('roles/{role}', [RoleController::class, 'show']);
        Route::post('roles', [RoleController::class, 'store']);
        Route::match(['put', 'patch'], 'roles/{role}', [RoleController::class, 'update']);
        Route::delete('roles/{role}', [RoleController::class, 'destroy']);
    });

    // In-app user notifications (Laravel native `database` channel). Every
    // endpoint is self-scoped by construction to the authenticated user's own
    // notifications (auth()->user()->notifications()), so a foreign/unknown id
    // resolves to 404; authorization is ownership, not a Spatie permission or a
    // Policy (see ADR-0005 / docs/api/0004-notifications.md). Rate-limited to
    // bound the frequent unread-count polling.
    Route::middleware('throttle:60,1')->group(function () {
        Route::get('notifications', [NotificationController::class, 'index']);
        Route::get('notifications/unread-count', [NotificationController::class, 'unreadCount']);
        Route::patch('notifications/{notification}/read', [NotificationController::class, 'markAsRead']);
        Route::post('notifications/read-all', [NotificationController::class, 'markAllAsRead']);
    });

    // Polymorphic file attachments: upload + metadata + authenticated download
    // + delete. Any model can own attachments (HasAttachments trait); the
    // attachable target is restricted to config('attachments.attachable_types').
    // Authorization (attachments.create/view/delete) is enforced server-side in
    // AttachmentController via the AttachmentPolicy on every endpoint. The
    // binary is never served statically — download streams through the
    // authorized endpoint only.
    Route::middleware('throttle:60,1')->group(function () {
        Route::post('attachments', [AttachmentController::class, 'store']);
        Route::get('attachments/{attachment}', [AttachmentController::class, 'show']);
        Route::get('attachments/{attachment}/download', [AttachmentController::class, 'download']);
        Route::delete('attachments/{attachment}', [AttachmentController::class, 'destroy']);
    });

    // PersonalData / Contact / Address — the reusable, polymorphic personal-data
    // module (ADR 0006). Each entity is attached to a polymorphic owner resolved
    // through a config allowlist (config/personal_data.php): the alias is the
    // security boundary, so a request can never target an arbitrary class.
    // Authorization ({resource}.view/create/update/delete) is enforced
    // server-side in each controller via its Policy on every endpoint.
    //
    // PRIVACY: these endpoints expose personal data (fiscal identifiers, birth
    // date, geolocation) and MUST NOT be released before Legal sign-off
    // (purpose, lawful basis, retention, erasure) — see ADR 0006 and the backend
    // handoff. They are wired but pending that gate.
    Route::middleware('throttle:60,1')->group(function () {
        Route::get('personal-data', [PersonalDataController::class, 'index']);
        Route::get('personal-data/{personalData}', [PersonalDataController::class, 'show']);
        Route::post('personal-data', [PersonalDataController::class, 'store']);
        Route::match(['put', 'patch'], 'personal-data/{personalData}', [PersonalDataController::class, 'update']);
        Route::delete('personal-data/{personalData}', [PersonalDataController::class, 'destroy']);

        Route::get('contacts/{contact}', [ContactController::class, 'show']);
        Route::post('contacts', [ContactController::class, 'store']);
        Route::match(['put', 'patch'], 'contacts/{contact}', [ContactController::class, 'update']);
        Route::delete('contacts/{contact}', [ContactController::class, 'destroy']);

        Route::get('addresses/{address}', [AddressController::class, 'show']);
        Route::post('addresses', [AddressController::class, 'store']);
        Route::match(['put', 'patch'], 'addresses/{address}', [AddressController::class, 'update']);
        Route::delete('addresses/{address}', [AddressController::class, 'destroy']);
    });

    // Geo reference lookups powering the address country → state → province →
    // city cascade selects (ADR 0010). Read-only reference data (Country / State
    // / Province / City): no Policy, no per-resource permission — the only gate
    // is auth:sanctum, plus a throttle (matching the rest of the module) to bound
    // the lookups. states/provinces/cities require their parent filter
    // (country_id / state_id / province_id|state_id) → 422 if absent. Each
    // endpoint is a direct, bounded Eloquent read (documented exception: no
    // Service, no business logic — see GeoController).
    Route::middleware('throttle:60,1')->group(function () {
        Route::get('countries', [GeoController::class, 'countries']);
        Route::get('states', [GeoController::class, 'states']);
        Route::get('provinces', [GeoController::class, 'provinces']);
        Route::get('cities', [GeoController::class, 'cities']);
    });
});
