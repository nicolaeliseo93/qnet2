# API Contract — Per-user Table Column Preferences

> Frozen contract for per-user column preferences on the generic Table framework.
> Companion of `docs/adr/0004-user-table-column-preferences.md` and
> `docs/api/0002-generic-tables.md`.
> Owner: Backend (implementation) / Architect (contract).

---

## Overview

Each user can persist, **per table domain**, their column **order**, **width**,
and **visibility**. The layout is stored as a **sparse delta** over the domain's
PHP default schema (only the properties that deviate), computed server-side, and
merged back into `GET /api/tables/{domain}/columns`.

| Purpose | Method + Path | Authorization | Body | Response |
|---|---|---|---|---|
| Save layout | `POST /api/tables/{domain}/preferences` | definition `viewAny` | column state | `ok()`, `data` = merged config |
| Reset layout | `DELETE /api/tables/{domain}/preferences` | definition `viewAny` | — | `204 No Content` |
| Read (merged) | `GET /api/tables/{domain}/columns` | definition `viewAny` | — | see `0002` (preferences merged in) |

All behind `auth:sanctum` + `throttle:60,1`. Preferences are **self-scoped by
construction**: the endpoints always operate on the authenticated user
(`auth()->id()`); the client never supplies a `user_id`, so there is no
cross-user access path. The only gate is the table's own `viewAny` — you can
personalize only a table you may view.

### Error contract

| Condition | Status |
|---|---|
| `{domain}` not registered in `config/tables.php` | **404** |
| Caller lacks the definition's `viewAny` | **403** |
| Unknown column `id` or out-of-whitelist property / bounds | **422** |
| Unauthenticated | **401** |

---

## 1. Save — `POST /api/tables/{domain}/preferences`

Upserts the current user's layout for `{domain}` (idempotent on
`(user_id, domain)`). The frontend sends the **full current column state**; the
backend diffs it against the PHP default and persists **only deviations**.

### Request

```json
{
  "columns": [
    { "id": "email", "visible": false, "order": 1 },
    { "id": "name",  "width": 400,     "order": 2 },
    { "id": "id",    "order": 3 }
  ]
}
```

Validation (`TablePreferencesRequest`, against the resolved definition):

- `columns`: required, non-empty array.
- `columns.*.id`: required; must be a real column id of the definition
  (`in:` the definition's columns) — unknown id → `422`.
- `columns.*.visible`: optional boolean.
- `columns.*.width`: optional integer, **50–1000** px — out of range → `422`.
- `columns.*.order`: optional integer ≥ 0.
- Only `visible` / `width` / `order` are accepted. Structural / security
  properties (`sortable`, `filterable`, `type`, `options`) are **never** accepted
  or persisted, so a user can never widen the SSRM sort/filter whitelist
  (`0002`) through their preferences.

### Storage (sparse delta)

For the request above, against the `users` default (where `name`'s default order
is already `2`), the persisted `preferences` JSON is:

```json
{
  "email": { "visible": false, "order": 1 },
  "name":  { "width": 400 },
  "id":    { "order": 3 }
}
```

`name` keeps only `width` because its `order` equals the default — deviations
only. The PHP definition stays the single source of truth; adding/removing/
renaming a column needs no migration of stored preferences.

### Response

`ok()` envelope; `data` is the freshly **merged** config (same shape as
`GET /columns`, see `0002`), so the frontend can confirm the canonical state.

---

## 2. Reset — `DELETE /api/tables/{domain}/preferences`

Deletes the current user's stored row for `{domain}`, returning the table to the
PHP default on the next config load. Responds `204 No Content`.

This is the **only** way preferences are cleared — they are never auto-deleted; a
normal save just overwrites them. It is an explicit user action ("reset to
default" / "set columns to default").

---

## 3. Merge rules (applied on `GET /columns`)

Per column, by `id`:

| Case | Result |
|---|---|
| column in definition, absent from delta | default kept |
| column in definition **and** delta | delta overrides `visible` / `width` / `order` |
| delta entry for a column **not** in the definition | ignored (no error) |
| unrecognized property in the delta | ignored |

After merge, columns are returned **sorted by their effective `order`**. Only
`visible` / `width` / `order` are ever taken from the delta.

The merged config also carries a top-level **`customized: boolean`** — `true` when
the user has a saved layout for this table, `false` otherwise. The frontend uses
it to offer "reset to default" only when there is something to reset.

---

## 4. Authorization model

| Level | Server-side check (domain `users`) |
|---|---|
| Read / save / reset preferences | definition `viewAny` (`users.viewAny`) |
| User scope | always `auth()->id()`; never client-supplied |

No dedicated Policy: the resource is self-scoped and gated by the table's
`viewAny` — there is no cross-user boundary to police (ADR-0004). The model is
intentionally **not** activity-logged (high-churn UI state, no audit value).

---

## 5. Routes

```php
Route::middleware(['auth:sanctum', 'throttle:60,1'])->group(function () {
    Route::post('tables/{domain}/preferences',   [TableController::class, 'savePreferences']);
    Route::delete('tables/{domain}/preferences', [TableController::class, 'resetPreferences']);
});
```

---

## 6. Adding a domain — no contract change

Preferences ride the same `{domain}` registry resolution as `0002`. A new domain
(e.g. `products`) gets per-user column preferences automatically once its
`TableDefinition` is registered — no new table, endpoint, route, or migration.
The persistence layer is fully domain-agnostic.
