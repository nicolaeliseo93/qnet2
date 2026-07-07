<?php

use App\Http\Controllers\Addresses\AddressController;
use App\Http\Controllers\Attachments\AttachmentController;
use App\Http\Controllers\Attributes\AttributeController;
use App\Http\Controllers\Auth\AuthController;
use App\Http\Controllers\Authorization\FieldCatalogueController;
use App\Http\Controllers\BusinessFunctions\BusinessFunctionController;
use App\Http\Controllers\BusinessFunctions\BusinessFunctionForSelectController;
use App\Http\Controllers\Companies\CompanyController;
use App\Http\Controllers\Companies\CompanyForSelectController;
use App\Http\Controllers\CompanySites\CompanySiteController;
use App\Http\Controllers\Config\ConfigController;
use App\Http\Controllers\Contacts\ContactController;
use App\Http\Controllers\Export\ExportController;
use App\Http\Controllers\Geo\GeoController;
use App\Http\Controllers\Import\ImportController;
use App\Http\Controllers\Meta\MetaController;
use App\Http\Controllers\Migration\MigrationController;
use App\Http\Controllers\Navigation\NavigationController;
use App\Http\Controllers\Notifications\NotificationController;
use App\Http\Controllers\OperationalSites\OperationalSiteController;
use App\Http\Controllers\OperationalSites\OperationalSiteForSelectController;
use App\Http\Controllers\PersonalData\PersonalDataController;
use App\Http\Controllers\ProductCategories\ProductCategoryController;
use App\Http\Controllers\Products\ProductController;
use App\Http\Controllers\Referents\ReferentController;
use App\Http\Controllers\ReferentTypes\ReferentTypeController;
use App\Http\Controllers\ReferentTypes\ReferentTypeForSelectController;
use App\Http\Controllers\Roles\RoleController;
use App\Http\Controllers\Roles\RoleForSelectController;
use App\Http\Controllers\Table\TableController;
use App\Http\Controllers\Table\TableFilterViewController;
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

        // Distinct values for a single column (Excel-like set filter, spec
        // 0004): allow-list columnId + filterModel keys, cap N, cross-column
        // filters applied. See TableValuesRequest / TableService::distinctValues.
        Route::post('tables/{domain}/values', [TableController::class, 'values']);

        // Generic bulk-delete: best-effort delete of many rows by id. Baseline
        // authorization is the same definition viewAny as every other
        // tables/{domain}/* endpoint; the per-row 'delete' ability and domain
        // delete guards (e.g. last-super-admin) are enforced PER ID by
        // TableBulkDeleteService, never fatal to the rest of the batch.
        Route::post('tables/{domain}/bulk-delete', [TableController::class, 'bulkDelete']);

        // Per-user column preferences (order/width/visibility): self-scoped to the
        // authenticated user, gated by the same definition viewAny. Save upserts a
        // sparse delta; delete resets to the PHP default. See ADR-0004 /
        // docs/api/0003-table-preferences.md.
        Route::post('tables/{domain}/preferences', [TableController::class, 'savePreferences']);
        Route::delete('tables/{domain}/preferences', [TableController::class, 'resetPreferences']);

        // Per-user filter state (the applied AG Grid filterModel): self-scoped,
        // gated by the same definition viewAny, keys restricted to filterable
        // columns. Save upserts the applied model; delete resets it. Mirrors the
        // preferences pair so filters survive a page reload.
        Route::post('tables/{domain}/filters', [TableController::class, 'saveFilters']);
        Route::delete('tables/{domain}/filters', [TableController::class, 'resetFilters']);

        // Saved filter views (spec 0007): named, savable AG Grid filter sets per
        // domain, private or shared. List/create are gated by the same
        // definition viewAny; update/delete are gated by TableFilterViewPolicy
        // (owner only — a shared view is a real cross-user access surface). A
        // bound {filterView} whose domain does not match {domain} 404s (never
        // 403), so views never leak across domains.
        Route::get('tables/{domain}/filter-views', [TableFilterViewController::class, 'index']);
        Route::post('tables/{domain}/filter-views', [TableFilterViewController::class, 'store']);
        Route::put('tables/{domain}/filter-views/{filterView}', [TableFilterViewController::class, 'update'])
            ->scopeBindings();
        Route::delete('tables/{domain}/filter-views/{filterView}', [TableFilterViewController::class, 'destroy'])
            ->scopeBindings();
    });

    // Generic, domain-driven CSV import engine (spec 0012), mirroring
    // tables/{domain}: one controller serves every domain; {domain} resolves
    // the ImportDefinition (config/imports.php), unknown → 404. Authorization
    // (the definition's {resource}.import ability) is enforced server-side in
    // ImportController on every action; a bound {importRun} that does not
    // belong to the actor OR whose resource != {domain} 404s. Upload/confirm
    // are rate-limited tighter (throttle:10,1) as costly/queue-dispatching
    // writes; template/show/errors stay at the standard throttle:60,1.
    Route::middleware('throttle:60,1')->group(function () {
        Route::get('imports/{domain}/template', [ImportController::class, 'template']);
        Route::get('imports/{domain}/{importRun}', [ImportController::class, 'show']);
        Route::get('imports/{domain}/{importRun}/errors', [ImportController::class, 'errors']);
    });

    Route::middleware('throttle:10,1')->group(function () {
        Route::post('imports/{domain}', [ImportController::class, 'upload']);
        Route::post('imports/{domain}/{importRun}/confirm', [ImportController::class, 'confirm']);
    });

    // Generic, domain-driven export engine (spec 0014), mirroring
    // tables/{domain} / imports/{domain}: one controller serves every domain
    // with a registered TableDefinition (config/tables.php) — no per-domain
    // export definition needed, unlike imports. {domain} resolves via
    // TableRegistry (unknown → 404). Authorization (the definition's
    // modelClass() `{domain}.export` ability) is enforced server-side in
    // ExportController on every action; a bound {exportRun} that does not
    // belong to the actor OR whose resource != {domain} 404s. Store is
    // rate-limited tighter (throttle:10,1) as a costly/queue-dispatching
    // write; show/download stay at the standard throttle:60,1.
    Route::middleware('throttle:60,1')->group(function () {
        Route::get('exports/{domain}/{exportRun}', [ExportController::class, 'show'])->scopeBindings();
        Route::get('exports/{domain}/{exportRun}/download', [ExportController::class, 'download'])->scopeBindings();
    });

    Route::middleware('throttle:10,1')->group(function () {
        Route::post('exports/{domain}', [ExportController::class, 'store']);
    });

    // Generic, registry-driven external-data migration engine (spec 0013),
    // mirroring tables/{domain} / imports/{domain}: one controller serves
    // every source; {source} resolves the MigrationSource (config/
    // migrations.php), unknown → 404. Authorization is a SINGLE hard gate for
    // the whole group — the `super-admin` middleware alias (EnsureSuperAdmin,
    // fail-closed via UserService::PRIVILEGED_ROLE): 401 anonymous, 403
    // non-super-admin. A bound {migrationRun} that does not belong to the
    // actor OR whose source != {source} 404s (never 403). Preview/import call
    // the external system, so they get their own (tighter) throttles.
    Route::middleware('super-admin')->group(function () {
        Route::middleware('throttle:60,1')->group(function () {
            Route::get('migrations', [MigrationController::class, 'index']);
            Route::get('migrations/{source}/columns', [MigrationController::class, 'columns']);
            Route::get('migrations/{source}/runs/{migrationRun}', [MigrationController::class, 'run']);
        });

        Route::middleware('throttle:30,1')->group(function () {
            Route::get('migrations/{source}/preview', [MigrationController::class, 'preview']);
        });

        Route::middleware('throttle:30,1')->group(function () {
            Route::post('migrations/{source}/import', [MigrationController::class, 'import']);
        });
    });

    // Centralized, resource-driven authorization metadata (spec 0004): one
    // generic endpoint per resource, registry-driven (config/authorization.php),
    // mirroring the tables/{domain} pattern. Authorization ({resource}.viewAny)
    // is enforced server-side in MetaController; unknown {resource} → 404.
    Route::middleware('throttle:60,1')->group(function () {
        Route::get('meta/{resource}', [MetaController::class, 'show']);
    });

    // Field catalogue for the Role form's field-permission matrix section
    // (spec 0006): the static fields() of every registered resource.
    // Authorization (roles.create OR roles.update) is enforced server-side in
    // FieldCatalogueController.
    Route::middleware('throttle:60,1')->group(function () {
        Route::get('authorization/fields', [FieldCatalogueController::class, 'index']);
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

    // Business functions CRUD backing the table row-actions (view/edit/delete)
    // + create (spec 0010). Authorization (business-functions.view/create/
    // update/delete) is enforced server-side in BusinessFunctionController via
    // BusinessFunctionPolicy on every endpoint.
    Route::middleware('throttle:60,1')->group(function () {
        // Minimal searchable/paginated business-function list for entity-backed
        // selects (for-select standard, ADR 0011), feeding the spec 0015 user-
        // form "function" select. Declared ABOVE business-functions/{businessFunction}
        // so the literal `for-select` segment wins over the bound wildcard.
        // Gated by business-functions.viewAny server-side in
        // BusinessFunctionForSelectController.
        Route::get('business-functions/for-select', BusinessFunctionForSelectController::class);

        Route::get('business-functions/{businessFunction}', [BusinessFunctionController::class, 'show']);
        Route::post('business-functions', [BusinessFunctionController::class, 'store']);
        Route::match(['put', 'patch'], 'business-functions/{businessFunction}', [BusinessFunctionController::class, 'update']);
        Route::delete('business-functions/{businessFunction}', [BusinessFunctionController::class, 'destroy']);
    });

    // Companies CRUD backing the table row-actions (view/edit/delete) + create
    // (spec 0010). Authorization (companies.view/create/update/delete) is
    // enforced server-side in CompanyController via CompanyPolicy on every
    // endpoint.
    Route::middleware('throttle:60,1')->group(function () {
        // Minimal searchable/paginated company list for entity-backed selects
        // (for-select standard, ADR 0011), feeding the spec 0015 user-form
        // "company" select. Declared ABOVE companies/{company} so the literal
        // `for-select` segment wins over the bound wildcard. Gated by
        // companies.viewAny server-side in CompanyForSelectController.
        Route::get('companies/for-select', CompanyForSelectController::class);

        Route::get('companies/{company}', [CompanyController::class, 'show']);
        Route::post('companies', [CompanyController::class, 'store']);
        Route::match(['put', 'patch'], 'companies/{company}', [CompanyController::class, 'update']);
        Route::delete('companies/{company}', [CompanyController::class, 'destroy']);
    });

    // Operational sites CRUD backing the table row-actions (view/edit/delete)
    // + create (spec 0011). Authorization (operational-sites.view/create/
    // update/delete) is enforced server-side in OperationalSiteController via
    // OperationalSitePolicy on every endpoint.
    Route::middleware('throttle:60,1')->group(function () {
        // Minimal searchable/paginated operational-site list for entity-backed
        // selects (for-select standard, ADR 0011), feeding the spec 0015 user-
        // form "site" select. Declared ABOVE operational-sites/{operationalSite}
        // so the literal `for-select` segment wins over the bound wildcard.
        // Gated by operational-sites.viewAny server-side in
        // OperationalSiteForSelectController.
        Route::get('operational-sites/for-select', OperationalSiteForSelectController::class);

        Route::get('operational-sites/{operationalSite}', [OperationalSiteController::class, 'show']);
        Route::post('operational-sites', [OperationalSiteController::class, 'store']);
        Route::match(['put', 'patch'], 'operational-sites/{operationalSite}', [OperationalSiteController::class, 'update']);
        Route::delete('operational-sites/{operationalSite}', [OperationalSiteController::class, 'destroy']);
    });

    // Referent types CRUD (spec 0016): full-managed lookup feeding the
    // `referents` module's "Referent type" select. Authorization
    // (referent-types.view/create/update/delete) is enforced server-side in
    // ReferentTypeController via ReferentTypePolicy on every endpoint.
    Route::middleware('throttle:60,1')->group(function () {
        // Minimal searchable/paginated referent-type list for entity-backed
        // selects (for-select standard, ADR 0011), feeding the referent-form
        // "Referent type" select. Declared ABOVE referent-types/{referentType}
        // so the literal `for-select` segment wins over the bound wildcard.
        // Gated by referent-types.viewAny server-side in
        // ReferentTypeForSelectController.
        Route::get('referent-types/for-select', ReferentTypeForSelectController::class);

        Route::get('referent-types/{referentType}', [ReferentTypeController::class, 'show']);
        Route::post('referent-types', [ReferentTypeController::class, 'store']);
        Route::match(['put', 'patch'], 'referent-types/{referentType}', [ReferentTypeController::class, 'update']);
        Route::delete('referent-types/{referentType}', [ReferentTypeController::class, 'destroy']);
    });

    // Sources / Tags / EA sectors: standalone lookup-table CRUD, extracted
    // into routes/api/lookups.php (file-size split, engineering.md §6) so
    // this file stays within the 500-line hard limit. Required INSIDE this
    // auth:sanctum group so every route there inherits the same context.
    require __DIR__.'/api/lookups.php';

    // Referents CRUD (spec 0016): a contact person/entity reusing the `users`
    // anagraphic stack (personal-data card + contacts + addresses) unchanged
    // via HasPersonalData. No for-select (no module selects a referent in this
    // spec). Authorization (referents.view/create/update/delete) is enforced
    // server-side in ReferentController via ReferentPolicy on every endpoint.
    Route::middleware('throttle:60,1')->group(function () {
        Route::get('referents/{referent}', [ReferentController::class, 'show']);
        Route::post('referents', [ReferentController::class, 'store']);
        Route::match(['put', 'patch'], 'referents/{referent}', [ReferentController::class, 'update']);
        Route::delete('referents/{referent}', [ReferentController::class, 'destroy']);
    });

    // Attributes CRUD (spec 0017): the global, reusable dynamic-attribute
    // catalogue assignable to product categories. Authorization
    // (attributes.view/create/update/delete) is enforced server-side in
    // AttributeController via AttributePolicy on every endpoint.
    Route::middleware('throttle:60,1')->group(function () {
        Route::get('attributes/{attribute}', [AttributeController::class, 'show']);
        Route::post('attributes', [AttributeController::class, 'store']);
        Route::match(['put', 'patch'], 'attributes/{attribute}', [AttributeController::class, 'update']);
        Route::delete('attributes/{attribute}', [AttributeController::class, 'destroy']);
    });

    // Product categories: CRUD + the dedicated tree view + the product form's
    // effective-attributes lookup (spec 0017). `tree` and
    // `{productCategory}/effective-attributes` are declared ABOVE the plain
    // `{productCategory}` show route so their literal segments win over the
    // bound wildcard. Authorization (product-categories.view/create/update/
    // delete, plus the effective-attributes cross-resource rule) is enforced
    // server-side in ProductCategoryController via ProductCategoryPolicy.
    Route::middleware('throttle:60,1')->group(function () {
        Route::get('product-categories/tree', [ProductCategoryController::class, 'tree']);
        Route::get('product-categories/{productCategory}/effective-attributes', [ProductCategoryController::class, 'effectiveAttributes']);

        Route::get('product-categories/{productCategory}', [ProductCategoryController::class, 'show']);
        Route::post('product-categories', [ProductCategoryController::class, 'store']);
        Route::match(['put', 'patch'], 'product-categories/{productCategory}', [ProductCategoryController::class, 'update']);
        Route::delete('product-categories/{productCategory}', [ProductCategoryController::class, 'destroy']);
    });

    // Products CRUD (spec 0017): generic fields + category-driven dynamic
    // attribute values (EAV, see ProductService). Authorization
    // (products.view/create/update/delete) is enforced server-side in
    // ProductController via ProductPolicy on every endpoint.
    Route::middleware('throttle:60,1')->group(function () {
        Route::get('products/{product}', [ProductController::class, 'show']);
        Route::post('products', [ProductController::class, 'store']);
        Route::match(['put', 'patch'], 'products/{product}', [ProductController::class, 'update']);
        Route::delete('products/{product}', [ProductController::class, 'destroy']);
    });

    // Company sites CRUD + logo/set-default (spec 0020). Authorization
    // (company-sites.view/create/update/delete) is enforced server-side in
    // CompanySiteController via CompanySitePolicy on every endpoint. No
    // for-select in this slice. The `set-default`/`logo` literal segments are
    // declared ABOVE the plain show/update/destroy routes, mirroring the
    // for-select precedent, so a literal segment never risks losing to the
    // bound {companySite} wildcard.
    Route::middleware('throttle:60,1')->group(function () {
        Route::post('company-sites/{companySite}/set-default', [CompanySiteController::class, 'setDefault']);
        Route::post('company-sites/{companySite}/logo', [CompanySiteController::class, 'uploadLogo']);
        Route::delete('company-sites/{companySite}/logo', [CompanySiteController::class, 'deleteLogo']);

        Route::get('company-sites/{companySite}', [CompanySiteController::class, 'show']);
        Route::post('company-sites', [CompanySiteController::class, 'store']);
        Route::match(['put', 'patch'], 'company-sites/{companySite}', [CompanySiteController::class, 'update']);
        Route::delete('company-sites/{companySite}', [CompanySiteController::class, 'destroy']);
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
