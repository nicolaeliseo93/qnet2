# API Contract — Generic Domain-driven Tables (AG Grid SSRM)

> Frozen contract for the generic, domain-driven Table framework. Linked to
> `docs/adr/0002-generic-domain-driven-table-registry.md`.
> Owner: Backend (implementation) / Architect (contract).
> Backend ↔ frontend boundary: this document is the shared source of truth.
> Supersedes `docs/api/0001-users-datatable.md`.

---

## Overview

**One pair of endpoints serves every domain.** The `{domain}` path segment
selects a server-side `TableDefinition` resolved from the `TableRegistry`
(`config/tables.php`). The only domain implemented today is `users`.

| Purpose | Method + Path | Authorization | Body | Response |
|---|---|---|---|---|
| Table config | `GET /api/tables/{domain}/columns` | definition `viewAny` | — | `ok()` envelope, `data` = config |
| Rows (SSRM) | `POST /api/tables/{domain}/rows` | definition `viewAny` | SSRM payload | `paginatedResponse()` envelope |

Both behind `auth:sanctum` + `throttle:60,1`. Server-side authorization is
mandatory: each endpoint calls the resolved definition's `viewAny`. For `users`
that is `UserPolicy::viewAny` (`users.viewAny`).

### Error contract

| Condition | Status |
|---|---|
| `{domain}` not registered in `config/tables.php` | **404** (`fail()`) |
| Caller lacks the definition's `viewAny` | **403** (`fail()`) |
| Malformed SSRM payload / out-of-whitelist `colId` or filter key | **422** |
| Unauthenticated | **401** |

`{domain}` is user-controlled: only config-mapped domains resolve; anything else
is `404` (no definition is reachable unless explicitly registered).

---

## 1. Config endpoint — `GET /api/tables/{domain}/columns`

Returns the resolved table schema for the current user. The `TableDefinition`
declares the catalogue; the generic resolver filters columns/actions by the
caller's permissions (as `NavigationService` filters navigation), and resolves
dynamic options (e.g. `users.roles`).

The returned schema is the **default** layout **with the caller's saved column
preferences (order / width / visibility) merged in** — see
`docs/api/0003-table-preferences.md` and ADR-0004. With no saved preferences the
response is the pure default. Columns are returned ordered by their effective
`order`.

Wrapped in `ok()` → `{ success, message, data }`. `data` for `domain=users`
(default, no saved preferences):

```json
{
  "resource": "users",
  "columns": [
    { "id": "id",         "label": "users.columns.id",         "type": "number",   "visible": false, "width": null, "order": 1, "sortable": true,  "filterable": false, "filterType": null },
    { "id": "name",       "label": "users.columns.name",       "type": "text",     "visible": true,  "width": null, "order": 2, "sortable": true,  "filterable": true,  "filterType": "text" },
    { "id": "roles",      "label": "users.columns.roles",      "type": "tags",     "visible": true,  "width": null, "order": 4, "sortable": false, "filterable": true,  "filterType": "set",  "options": ["admin","editor"] },
    { "id": "locale",     "label": "users.columns.locale",     "type": "enum",     "visible": false, "width": null, "order": 5, "sortable": true,  "filterable": true,  "filterType": "set",  "options": ["en","it"] },
    { "id": "created_at", "label": "users.columns.created_at", "type": "datetime", "visible": true,  "width": null, "order": 6, "sortable": true,  "filterable": true,  "filterType": "date" },

    { "id": "user_type",  "label": "users.columns.user_type",  "type": "badge",    "visible": true,  "width": null, "order": 7, "sortable": true,  "filterable": true,  "filterType": "set",  "options": ["individual","company"],
      "badges": [
        { "value": "individual", "label": "Individual", "color": "blue",   "icon": "user",     "is_default": true,  "hidden_on_form": false },
        { "value": "company",    "label": "Company",    "color": "violet", "icon": "building", "is_default": false, "hidden_on_form": false }
      ] },
    { "id": "country",    "label": "users.columns.country",    "type": "text",     "visible": false, "width": null, "order": 9, "sortable": true,  "filterable": true,  "filterType": "set",  "options": ["France","Italy"] }
  ],
  "filters": [
    { "columnId": "name",       "type": "text" },
    { "columnId": "roles",      "type": "set",  "options": ["admin","editor"] },
    { "columnId": "locale",     "type": "set",  "options": ["en","it"] },
    { "columnId": "created_at", "type": "date" },
    { "columnId": "user_type",  "type": "set",  "options": ["individual","company"] },
    { "columnId": "country",    "type": "set",  "options": ["France","Italy"] }
  ],
  "actions": [
    { "key": "view",   "label": "actions.view",   "icon": "eye",    "type": "link",   "confirm": false },
    { "key": "edit",   "label": "actions.edit",   "icon": "pencil", "type": "link",   "confirm": false },
    { "key": "delete", "label": "actions.delete", "icon": "trash",  "type": "danger", "confirm": true  }
  ],
  "defaultSort":       [ { "columnId": "created_at", "direction": "desc" } ],
  "defaultPagination": { "limit": 25 }
}
```

### Field semantics (domain-agnostic)

- **`resource`** — the `{domain}` key; lets the frontend pick the per-domain
  client-side renderer registry.
- **`columns[].id`** — stable key = real DB column (or derived field, e.g.
  `roles`). Frontend may map `id → custom renderer`.
- **`columns[].type`** — `text | number | datetime | enum | tags | badge`. Drives
  the default cell rendering/formatting. `badge` renders a colored badge from
  `columns[].badges` (see below).
- **`columns[].visible`** — visibility (default, or the user's saved override).
- **`columns[].width`** — column width in px, or `null` when the definition sets
  no explicit default (the frontend applies its own). User-overridable.
- **`columns[].order`** — stable 1-based sort key for column ordering; columns are
  returned sorted by it. User-overridable.
- **`columns[].sortable` / `filterable`** — **server-side whitelist**: the rows
  endpoint accepts sort/filter ONLY on columns flagged `true` here.
- **`columns[].filterType`** — `text | number | date | set` (or `null` when the
  column carries no filter). Drives the frontend filter widget **independently**
  of the render `type`, so a `text`/`badge`-rendered column can advertise a `set`
  filter (e.g. the geo columns above are text but filter as a set). When `null`
  the frontend falls back to inferring the widget from `type`.
- **`columns[].options`** — enum/set values; **dynamically resolved** when the
  definition declares an `optionsResolver` (e.g. `users.roles` reflects the
  assignable Spatie roles for the caller, never a hardcoded list). For a `set`
  filter these are the accepted filter tokens.
- **`columns[].badges`** — present ONLY on `badge` columns: per-value metadata
  (`value`, `label`, `color`, `icon`, `is_default`, `hidden_on_form`) sourced
  from a domain enum (see ADR-0007 / ADR-0015). The frontend maps a row's value
  to its entry to render the label/color/icon, so it never hardcodes the
  value→label/color mapping. `options` still carries the plain value tokens used
  by the set filter.
- **`filters[]`** — filter catalogue + AG Grid type (`text | number | date |
  set`). Set options are resolved like columns.
- **`actions[]`** — action catalogue (how to render). `type`: `link | action |
  danger`; `confirm`: requires UI confirmation. An action whose `permission` gate
  the caller lacks is omitted from the catalogue (UX gate). The 3 standard keys
  (`view/edit/delete`) are extensible with custom keys per definition.
- **`defaultSort` / `defaultPagination`** — initial grid state.

> For `users`, only real `User` fields are exposed (`id, name, email, locale,
> created_at`) plus derived `roles`. `password`/`remember_token` are `hidden` and
> never exposed. Other domains expose only their own real fields.

---

## 2. Rows endpoint (SSRM) — `POST /api/tables/{domain}/rows`

### Why POST (not GET)

SSRM sends nested `sortModel[]` / `filterModel{}`. A pure `GET` would force
fragile query-string serialization of nested structures and complicate
FormRequest validation. `POST` with a structured body preserves the exact SSRM
semantics and is read-only by nature (no side effects). See ADR-0002.

### Request (AG Grid SSRM payload)

Validated by the generic `TableRowsRequest` against **the resolved definition's**
whitelist:

```json
{
  "startRow": 0,
  "endRow": 25,
  "sortModel": [
    { "colId": "created_at", "sort": "desc" }
  ],
  "filterModel": {
    "name":  { "filterType": "text", "type": "contains", "filter": "ann" },
    "roles": { "filterType": "set",  "values": ["admin"] }
  }
}
```

Validation:
- `startRow`: `integer|min:0`.
- `endRow`: `integer`; `endRow > startRow`; `endRow - startRow ≤ MAX_LIMIT` (100).
- `sortModel[].colId`: `in:` the definition's **sortable** column ids; `sort`:
  `in:asc,desc`.
- `filterModel` keys: `in:` the definition's **filterable** column ids; values
  validated by filter type.
- Any non-whitelisted `colId` / key → `422`, never reaches the query builder.
- Unknown `{domain}` → `404` (resolved before validation).

### Server-side translation (reused envelope)

| SSRM request | Server |
|---|---|
| `startRow` | `offset` |
| `endRow - startRow` | `limit` (cap `MAX_LIMIT = 100`) |
| `sortModel` | whitelisted `ORDER BY` |
| `filterModel` | whitelisted `WHERE` (bound params; `LIKE` wildcards escaped) |

The generic `TableService` runs the definition's `baseQuery()` (with eager loads,
no N+1), applies whitelisted filters/sort, paginates, maps each row via
`definition->mapRow()`, and attaches per-row `actions[]` via
`definition->actionsFor()`.

### Response (`paginatedResponse()` envelope) — `domain=users`

```json
{
  "items": [
    {
      "id": 12,
      "name": "Anna Rossi",
      "email": "anna@example.com",
      "roles": ["admin"],
      "locale": "it",
      "created_at": "2026-05-30T09:12:00+00:00",
      "actions": ["view", "edit", "delete"]
    },
    {
      "id": 13,
      "name": "Marco Bianchi",
      "email": "marco@example.com",
      "roles": ["editor"],
      "locale": "en",
      "created_at": "2026-05-28T14:03:00+00:00",
      "actions": ["view"]
    }
  ],
  "export_link": null,
  "pagination": { "total": 137, "offset": 0, "limit": 25, "total_pages": 6 }
}
```

Row fields are domain-defined (`definition->mapRow()`); `actions: string[]` is the
per-row whitelist of allowed action keys. The envelope shape is identical for
every domain.

### Response → AG Grid (frontend datasource)

```text
rowData  = response.items
rowCount = response.pagination.total
// lastRow derivable: startRow + items.length >= total → lastRow = total
params.success({ rowData, rowCount })
```

---

## 3. Row actions

- Each row exposes **`actions: string[]`** = action keys allowed **for that row**,
  computed server-side via the domain Policy. For `users`:
  - `view`   ⇐ `UserPolicy::view`   (`users.view`)
  - `edit`   ⇐ `UserPolicy::update` (`users.update`)
  - `delete` ⇐ `UserPolicy::delete` (`users.delete`; also forbids self-delete)
- The **catalogue** (`config.actions[]`) describes how to render each key
  (label/icon/type/confirm). The frontend renders, per row, only the actions whose
  `key` is in `row.actions`.
- Custom keys (e.g. `"impersonate"`) are added to the definition's action
  catalogue + included in `actionsFor()` when permitted.
- **UI-invoked actions hit their own endpoints** (e.g. `DELETE /api/users/{user}`)
  which **re-authorize** server-side. `row.actions` is affordance, not final
  authorization. Those CRUD endpoints are **out of scope** of this contract and
  unchanged.

---

## 4. Authorization model

| Level | Server-side check (domain `users`) |
|---|---|
| Table access (columns + rows) | `users.viewAny` (`UserPolicy::viewAny`) |
| Row action `view` | `users.view` |
| Row action `edit` | `users.update` |
| Row action `delete` | `users.delete` (+ no self-delete) |

For any domain, the enforced check is the resolved definition's `viewAny`. No
frontend-supplied value (filters, sort, actions) is trusted — all are re-validated
/ re-authorized server-side.

---

## 5. Routes

```php
Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::get('tables/{domain}/columns', [TableController::class, 'columns']);
    Route::post('tables/{domain}/rows',   [TableController::class, 'rows']);
});
```

The previous `users/table/config` + `users/table/rows` routes are **removed**
(no back-compat — skeleton stage). The Users **CRUD** routes
(`/api/users/{user}`, `POST /api/users`) backing the row-actions are
**unchanged**.

---

## 6. Adding a domain (e.g. `products`) — no contract change

1. Backend: `ProductsTableDefinition extends AbstractTableDefinition`; add
   `'products' => ProductsTableDefinition::class` to `config/tables.php`.
   Endpoints `GET/POST /api/tables/products/columns|rows` work immediately.
2. Frontend: register a `products` entry in the client-side renderer registry;
   mount the generic table with `domain="products"`.

No new endpoint, route, controller, service, request or resource.

### Security note (mandatory for every definition)

A new `TableDefinition` MUST honour these fail-safe invariants — the generic
engine cannot enforce them inside the definition's own hooks:

- **`authorizeViewAny`** MUST reflect a Policy. By default
  `AbstractTableDefinition` derives it from the declared `modelClass()` via
  `Gate::allows('viewAny', modelClass())` (fail-closed: no Policy/permission ⇒
  denied). Do NOT override it to return a hardcoded `true`, and never weaken it
  below the model's `viewAny` Policy.
- **`mapRow`** MUST expose only real, non-`hidden` fields (plus derived fields
  the definition intends to publish). Never emit a key present in the model's
  `$hidden` (e.g. `password`, `remember_token`). A contract test asserts
  `mapRow keys ∩ model->getHidden() = ∅` for every registered definition.
- **`applyDerivedFilter`** MUST use only bound parameters / `whereHas` for any
  user-supplied input. Never build SQL from request values via `whereRaw`,
  string concatenation, or interpolation; cap cardinality of `IN (…)` lists as
  defence in depth (see `UsersTableDefinition` for the `roles` filter).
