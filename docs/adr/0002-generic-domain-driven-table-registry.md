# Architecture Decision Record

> ADR generato dal template `templates/architecture-decision.md`.
> Owner: Architect Agent.

---

## ADR ID

0002

## Title

Generic Domain-driven Table Registry (one pair of endpoints for every domain)

## Status

ACCEPTED

> Supersedes ADR-0001 (Backend-driven DataTable, AG Grid SSRM). ADR-0001 stays as
> historical record; its endpoints (`/api/users/table/*`) and its users-specific
> backend/frontend classes are replaced by the generic framework defined here.

## Date

2026-06-12

---

## Context

ADR-0001 introduced a backend-driven DataTable for the **Users** page: a config
endpoint (`GET /api/users/table/config`) + an SSRM data endpoint
(`POST /api/users/table/rows`), backed by a users-specific
`UserTableService` / `UserTableController` / `UserTableRowsRequest` /
`UserTableRowResource` + `config/tables/users.php`. The SSRM translation
(whitelisted sort/filter, `escapeLike`, `MAX_LIMIT`, per-row `actions[]` via
Policy) is already implemented and validated by Security/QA.

ADR-0001 itself anticipated this moment (its Consequences note: «quando arriverà
la seconda tabella, valutare l'estrazione di una base condivisa»). The product now
needs the same table behavior for **multiple domains** (users, and next products,
…). Duplicating a Controller + Service + Request + Resource + config per domain
would:

- duplicate the **security-critical** SSRM logic (anti-injection whitelist,
  `escapeLike`, `MAX_LIMIT`, per-row authorization) N times — every copy a place a
  regression can hide;
- violate DRY (`coding-standards.md`) and Reuse Before Build
  (`decision-making.md`);
- bloat the frontend with one near-identical feature folder per domain.

The trigger condition for ADR-0001's deferred abstraction is met. We now have a
**real second consumer on the horizon**, so extracting a generic framework is no
longer premature abstraction — it is the documented next step.

Constraints (non-negotiable):

- **No regression** on the guarantees already validated: sort/filter whitelist,
  `escapeLike`, `MAX_LIMIT`, per-row `actions[]` via Policy, no leak of `hidden`
  fields, throttle, assignable-roles coherence. The generic must **centralize**
  the exact same logic, not reimplement it loosely.
- Only **one real domain exists today: `users`** (real `User` fields only). The
  pattern must be visibly extensible to `products` etc. without inventing
  domains/fields now.
- Server-side authorization remains mandatory on every endpoint
  (`security-standards.md` → Authorization First).
- This refactor covers **only the table read-path** (config + rows). The Users
  **CRUD** (`UserController`, `UserService`, `Store/UpdateUserRequest`,
  `/api/users/{user}`) that backs the row-actions is **out of scope and stays
  unchanged**. Row-actions keep hitting those endpoints.
- Skeleton stage: the user asked to "redo", so **backward compatibility is not
  required**. The old `/api/users/table/*` routes are removed, not kept.

---

## Decision

Replace the users-specific table stack with a **generic, domain-driven Table
framework** resolved by a **registry**. One pair of endpoints serves any domain:

```
GET  /api/tables/{domain}/columns   → resolved table config for {domain}
POST /api/tables/{domain}/rows      → SSRM page of rows + total for {domain}
```

`{domain}` is a route segment (e.g. `users`). The backend resolves a
**TableDefinition** for that domain from a **registry**; unknown domain → `404`.
The frozen contract lives in `docs/api/0002-generic-tables.md`.

### 1. TableDefinition contract (the per-domain unit)

Each domain implements one class describing **everything** about its table.
Concrete domains extend an abstract base that fills in the standard CRUD-table
shape:

```php
namespace App\Tables;

interface TableDefinition
{
    /** Permission prefix / domain key, e.g. "users". */
    public function domain(): string;

    /** Authorize table access (viewAny). Throws/false → 403. */
    public function authorizeViewAny(User $user): bool;

    /** Base Eloquent query (with eager loads, no N+1). */
    public function baseQuery(): Builder;

    /** Column catalogue: id,label,type,visible,sortable,filterable,filterType,options(/Resolver). */
    public function columns(): array;

    /** Filter catalogue (columnId,type,options/optionsResolver). */
    public function filters(): array;

    /** Action catalogue (key,label,icon,type,confirm, optional permission gate). */
    public function actions(): array;

    /** Default sort + default pagination. */
    public function defaultSort(): array;
    public function defaultPagination(): array;

    /** Map one model → row array (real fields only, hidden never exposed). */
    public function mapRow(User $actor, Model $row): array;

    /** Allowed action keys for THIS row, via the domain Policy. */
    public function actionsFor(User $actor, Model $row): array;
}
```

`AbstractTableDefinition` implements the cross-cutting parts (column/filter/action
resolution and permission filtering, `sortableColumns()`, `filterableColumns()`)
exactly as today's `UserTableService::config()` does, so a concrete definition
only declares its model, columns, filters, actions and the two row-level hooks.

`UsersTableDefinition extends AbstractTableDefinition` is the **only** concrete
definition for now. It carries what `config/tables/users.php` +
`UserTableService` users-specific bits carry today: real `User` columns
(`id, name, email, roles(derived), locale, created_at`), the `roles` set filter
with **dynamic options** resolved via `UserService::assignableRoleNames($actor)`
(an `optionsResolver` closure, replacing the `optionsSource` marker),
`mapRow()` returning the same fields, and `actionsFor()` calling `UserPolicy`
(`view→view`, `update→edit`, `delete→delete`). `authorizeViewAny()` →
`Gate::allows('viewAny', User::class)`.

> Declarative-vs-class: today's schema is a config **array** + a Service that
> resolves it. The dynamic pieces (`roles` options, per-row Policy calls,
> `baseQuery` eager loads, `mapRow`) are **already PHP**, not declarable in a
> static config file. Folding the whole definition into one class per domain (a)
> keeps the static catalogue and its dynamic resolution **co-located**, (b)
> removes the `config/tables/*.php` ↔ Service split that already exists for
> users, (c) is the standard Laravel way to register behavior. The static parts
> (columns/filters/actions arrays) stay literal arrays **inside** the class, so
> the declarative readability of `config/tables/users.php` is preserved.

### 2. TableRegistry (domain → definition)

A `TableRegistry` maps `domain string → TableDefinition`. Registration is via an
explicit **config map** + container binding (chosen below):

```php
// config/tables.php
return [
    'definitions' => [
        'users' => App\Tables\UsersTableDefinition::class,
        // 'products' => App\Tables\ProductsTableDefinition::class,
    ],
];
```

`TableRegistry::resolve(string $domain): TableDefinition` looks up the class,
resolves it from the container (so dependencies like `UserService` are injected),
and throws `ModelNotFoundException` (→ `404` via `BaseApiController`) for an
unknown domain. **Adding a domain = write one `XxxTableDefinition` + add one line
to `config/tables.php`.** No new Controller/Service/Request/Resource/route.

**Registration strategy — decision.** Three options weighed against
`decision-making.md` (Simplicity First, Prefer Known Solutions):

| Option | Verdict |
|---|---|
| **Config map** (chosen) | Explicit, greppable, trivial to read/test, zero magic. One line per domain. |
| Auto-discovery (scan `App\Tables\*`) | Rejected: filesystem scanning, hidden registration, harder to test, surprises on rename. |
| Service-provider `bind()` calls | Rejected: spreads the domain list across boot code; a config array is the same binding with less ceremony. |

Config map wins on simplicity and explicitness. The registry still resolves
through the container, so DI is preserved.

### 3. Generic Controller + generic Service

- **`TableController`** (thin, replaces `UserTableController`): `columns()` and
  `rows()`. Both resolve the definition via the registry from the `{domain}`
  route param, call `definition->authorizeViewAny($user)` (→ `403` on deny), and
  delegate to `TableService`. Same try/catch + `handleControllerException`
  envelope discipline as today.
- **`TableService`** (replaces `UserTableService`'s generic half): holds the
  **single copy** of the SSRM engine — `startRow/endRow → offset/limit` with
  `MAX_LIMIT` cap, `applyFilters`/`applySorting` against the definition's
  whitelist, `escapeLike`, role-filter cardinality cap, `mapRow` + `actionsFor`
  per row. It operates **only through the resolved `TableDefinition`** (its
  `baseQuery`, `columns`, `mapRow`, `actionsFor`). The security-critical code that
  Security/QA validated moves here **verbatim**, parameterized by the definition
  instead of hardcoding `User`. No behavioral change — same whitelisting, same
  bound parameters, same caps.

This is the crux: the anti-injection + per-row-auth logic lives in **exactly one
place** and every domain inherits it identically.

### 4. Generic validation (domain not known at boot)

`TableRowsRequest` (replaces `UserTableRowsRequest`) validates the SSRM payload
against the whitelist **of the resolved definition**. The domain is unknown when
the FormRequest is constructed, so it is resolved **inside the request** from the
route parameter:

```php
$domain = $this->route('domain');
$definition = app(TableRegistry::class)->resolve($domain); // 404 if unknown
$sortable   = $definition->sortableColumnIds();
$filterable = $definition->filterableColumnIds();
```

`rules()` + `withValidator()` then apply the **same** checks as today
(`startRow ≥ 0`; `endRow > startRow`; block ≤ `MAX_LIMIT`; `sortModel.*.colId
in: sortable`; `sort in: asc,desc`; every `filterModel` key `in: filterable`),
but sourced from the definition instead of `config('tables.users')`. Unknown
domain surfaces as `404` before validation; out-of-whitelist key still `422`.
`authorize()` stays `true` (authorization remains in the controller via the
definition's `viewAny`).

### 5. Generic Resource

`TableRowResource` (replaces `UserTableRowResource`) shapes the **already-mapped**
row array produced by `definition->mapRow()` (which includes `actions[]`). It does
not know domain fields and does not recompute permissions — it passes the
associative array straight through (`fn ($req) => $this->resource`). One resource
for all domains; the definition owns the row shape.

### 6. Routes (replace, not add)

```php
Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::get('tables/{domain}/columns', [TableController::class, 'columns']);
    Route::post('tables/{domain}/rows',   [TableController::class, 'rows']);
});
```

The old `users/table/config` + `users/table/rows` routes are **removed**. The
Users **CRUD** routes (`/api/users/{user}`, `POST /api/users`) are **untouched**.

### 7. Frontend — generic feature keyed by domain

The already-agnostic `components/data-table/*` (DataTable wrapper) **stays**. The
users-specific feature is generalized into `features/table/*` (generic), and
`features/users/*` shrinks to a thin domain adapter:

- `features/table/api.ts` — `fetchTableConfig(domain)` → `GET /tables/{domain}/columns`;
  `fetchTableRows(domain, payload)` → `POST /tables/{domain}/rows`.
- `features/table/types.ts` — the contract types (renamed `UsersTable*` →
  `Table*`), generic over domain.
- `features/table/use-table-config.ts` — `useTableConfig(domain)` (query key
  includes domain).
- `features/table/data-table-page.tsx` (or `table-container.tsx`) — generic
  component: loads config for a `domain`, wires the SSRM datasource
  (`createSsrmDatasource(domain)`), looks up the **client-side renderer registry**
  by domain for `columnId → CellRenderer` + custom row-action handling.
- `features/table/renderer-registry.ts` — `domain → { cellRenderers, ... }` map.
  `users` registers its `column-renderers` (roles/email/created_at) + row-action
  CRUD wiring. A new domain registers its own entry; the generic container is
  untouched.

`features/users/*` keeps **only** what is genuinely users-specific: the CRUD forms
/ sheets / schemas / column-renderers / row-action handlers, plus a thin
`UsersPage` that mounts the generic table with `domain="users"`. The
`ssrm-datasource` becomes parameterized by `domain` (drop the hardcoded
`fetchUsersTableRows` default).

### How a new domain is added (design illustration, not implemented)

1. Backend: `class ProductsTableDefinition extends AbstractTableDefinition`
   (model `Product`, its real columns/filters/actions, `mapRow`, `actionsFor` via
   `ProductPolicy`); add `'products' => ProductsTableDefinition::class` to
   `config/tables.php`. Done — endpoints `GET/POST /api/tables/products/*` work.
2. Frontend: add a `products` entry to `renderer-registry.ts` (its custom cell
   renderers + row-action handlers); mount `<DataTablePage domain="products" />`.
   No new endpoint, route, controller, service, request or resource.

---

## Alternatives Considered

- **Keep per-domain Controller/Service/Request/Resource (status quo, copy for
  products)** — rejected: duplicates security-critical SSRM logic per domain;
  every copy is a regression surface; violates DRY / Reuse Before Build.
- **Pure declarative config files per domain (`config/tables/{domain}.php`) with
  no class** — rejected: the dynamic parts (dynamic `roles` options, per-row
  Policy `actionsFor`, `baseQuery` eager loads, `mapRow`) are inherently PHP
  behavior, not static data. A config file alone cannot express them; we'd need a
  resolver Service anyway — i.e. today's split, generalized but still two pieces
  per domain. One definition class co-locates static + dynamic with less surface.
- **Auto-discovery / service-provider registration** — rejected for the registry:
  less explicit and harder to test than a config map (see table above).
- **`GET /api/tables/{domain}/` for data (user's first proposal)** — rejected for
  the data endpoint: SSRM sends nested `sortModel[]` / `filterModel{}`; a pure GET
  would force fragile query-string serialization of nested structures and
  complicate FormRequest validation. We keep **`POST /api/tables/{domain}/rows`**
  to preserve the exact SSRM semantics already implemented (consistent with
  ADR-0001's POST rationale). `columns` stays a side-effect-free **GET**. This is
  a deliberate, documented divergence from "GET pura".
- **Keeping old `/users/table/*` routes for back-compat** — rejected: skeleton
  stage, user asked to redo; dual routes would duplicate wiring and invite drift.

---

## Trade-offs

- **Advantages**
  - Security-critical SSRM logic (whitelist, `escapeLike`, `MAX_LIMIT`, per-row
    auth) centralized in **one** `TableService` → one place to audit, zero
    duplication, no per-domain regression surface.
  - Adding a domain = one definition class + one config line (+ one frontend
    registry entry). No new endpoint/route/controller.
  - Frontend `DataTable` wrapper stays untouched; the generic feature is reused
    across domains.
- **Disadvantages**
  - The `TableDefinition` abstraction adds one indirection layer vs. a single
    hardcoded table. Justified now that a second consumer is real (ADR-0001's
    deferral condition is met).
  - The generic `TableService` must stay strictly definition-driven; a careless
    future change there affects **every** domain — raises the bar on review/tests
    for that one file (acceptable: it's the same code, just shared).
- **What we give up**
  - Per-domain freedom to diverge the data endpoint shape. Intentional: uniform
    SSRM contract is the point.

---

## Consequences

- **Positive**: a single, audited table read-path for the whole app; `products`
  and future domains are cheap and uniform; the contract is one document
  (`docs/api/0002-generic-tables.md`).
- **Negative**: a migration cost now (convert users stack → generic), and AG Grid
  Enterprise licensing from ADR-0001 still applies (unchanged).
- **Tracked tech debt**: `AbstractTableDefinition` + generic `TableService` are
  the new shared core; their tests must cover the security invariants (whitelist,
  `escapeLike`, `MAX_LIMIT`, per-row auth, hidden-field non-leak) so the shared
  code can never silently regress for all domains at once.

---

## Affected Agents

- **Backend Agent** (owner): create `TableDefinition` / `AbstractTableDefinition`
  / `UsersTableDefinition` / `TableRegistry` / `TableController` / `TableService`
  / `TableRowsRequest` / `TableRowResource` / `config/tables.php`; delete the
  users-specific table stack + old routes. CRUD untouched.
- **Frontend Agent**: generalize `features/users` table into `features/table`
  (generic) + client-side renderer registry; users becomes a thin adapter.
- **Security Agent**: re-validate that the centralized `TableService` preserves
  the whitelist / `escapeLike` / `MAX_LIMIT` / per-row-auth / no-hidden-leak
  guarantees with **no behavioral change** vs ADR-0001.
- **Reviewer / QA**: regression on users table (config + rows + actions) through
  the new endpoints; verify old routes are gone.

---

## Risks

- **Security regression during the lift-and-shift**: the SSRM engine moves from
  `UserTableService` to a generic `TableService`. Risk that whitelist binding,
  `escapeLike`, or per-row auth subtly change. Mitigation: move the logic
  verbatim, parameterized only by the definition; carry over (and generalize) the
  existing tests; Security sign-off required before merge.
- **Validation timing**: `TableRowsRequest` resolves the definition from the route
  param — an unknown domain must `404` **before** validation runs, not produce a
  confusing `422`. Mitigation: registry `resolve()` throws `ModelNotFoundException`
  on lookup inside the request; controller path also resolves first.
- **Hidden-field leak via `mapRow`**: each definition's `mapRow` must expose only
  real, non-hidden fields (never `password`/`remember_token`). Mitigation: define
  `mapRow` explicitly per domain (as `UsersTableDefinition` does today); review
  gate.
- **Authorization coverage**: every endpoint must call the definition's
  `viewAny`; the generic controller is the single enforcement point — a bug there
  affects all domains. Mitigation: feature tests assert `403` without `viewAny`
  for the users domain.
- **Unknown/spoofed domain enumeration**: `{domain}` is user-controlled. Only
  config-mapped domains resolve; everything else `404`. No definition is reachable
  unless explicitly registered.

---

## References

- `docs/api/0002-generic-tables.md` — frozen generic contract (this ADR's
  companion).
- `docs/adr/0001-backend-driven-datatable-ag-grid-ssrm.md` — superseded; original
  SSRM/envelope/actions design reused here.
- `docs/api/0001-users-datatable.md` — superseded by `0002-generic-tables.md`.
- Reused infra: `app/Http/Controllers/Abstract/BaseApiController.php` (envelope),
  `app/Policies/Abstracts/BasePolicy.php` + `app/Policies/UserPolicy.php`,
  `app/Services/UserService.php` (`assignableRoleNames`).
- Existing implementation being generalized: `app/Services/UserTableService.php`,
  `app/Http/Controllers/Users/UserTableController.php`,
  `app/Http/Requests/Users/UserTableRowsRequest.php`,
  `app/Http/Resources/UserTableRowResource.php`, `config/tables/users.php`.
