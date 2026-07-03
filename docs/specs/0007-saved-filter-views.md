# Spec 0007 — Saved filter views (private / shared)

> Status: FROZEN CONTRACT. Builds on the per-user filter persistence
> (`user_table_filters`). Full-stack; backend and frontend implement against the
> shapes below without changing them.

## Goal

A user can save the current AG Grid filter set as a **named view** and re-apply it
later instead of re-entering the same filters. A view is either **private** (only
the owner) or **shared** (visible/appliable by every user who can view that table).
Only the owner can edit or delete a view.

## Domain model

Table `table_filter_views`:

| column | type | notes |
|---|---|---|
| id | bigint pk | |
| user_id | fk → users, cascadeOnDelete | owner |
| domain | string | TableRegistry domain key (e.g. `users`) |
| name | string(80) | view label |
| filters | json | AG Grid filterModel, keyed by column id |
| visibility | string | `private` \| `shared`, default `private` |
| timestamps | | |

Constraints: `unique(user_id, domain, name)`; index `(domain, visibility)`.

Enum `App\Enums\FilterViewVisibility` (string-backed): `Private = 'private'`,
`Shared = 'shared'`. Model casts `visibility => FilterViewVisibility::class`,
`filters => 'array'`.

Model `App\Models\TableFilterView`: `$fillable = ['user_id','domain','name','filters','visibility']`,
`user()` belongsTo `User`. This IS backed by a Policy (unlike `UserTablePreference`)
because shared views create a real cross-user access surface.

## Authorization

- **List / create**: gated by the table definition's `authorizeViewAny($actor)`
  (same as every `tables/{domain}` endpoint). Unknown domain → 404.
- **Update / delete**: owner only, via `TableFilterViewPolicy` (`update`/`delete`
  return `$view->user_id === $actor->id`). A bound `{filterView}` whose `domain`
  does not match the route `{domain}` → 404 (never 403), so views never leak
  across domains.
- Security: `filters` keys are restricted to `definition->filterableColumnIds()`
  on **write** (422 on any key outside the allow-list, identical to
  `TableRowsRequest::withValidator`) and re-filtered on **read**, so a stored view
  can never widen the SSRM filter allow-list.

## API (envelope `{ success, message, data }`; auth:sanctum + throttle:60,1)

### Resource shape — `TableFilterViewResource`
```json
{
  "id": 12,
  "name": "Active admins",
  "filters": { "roles": { "filterType": "set", "values": ["admin"] } },
  "visibility": "shared",
  "owned": true,
  "owner_name": null
}
```
- `owned`: `view.user_id === auth id`.
- `owner_name`: the owner's display name, present **only** when the view is shared
  and NOT owned by the actor (so the UI can show "shared by X"); `null` otherwise.
  Never expose owner email/PII — display name only.

### Endpoints
1. `GET /api/tables/{domain}/filter-views` → `data: TableFilterViewResource[]`
   The actor's own views (private + shared) for the domain **plus** other users'
   `shared` views for the domain. Order: owned first, then shared-by-others; each
   group by `name` asc.
2. `POST /api/tables/{domain}/filter-views` (201) → `data: TableFilterViewResource`
   Body `{ name, filters, visibility }`. Owned by the actor.
3. `PUT /api/tables/{domain}/filter-views/{filterView}` → `data: TableFilterViewResource`
   Body `{ name, filters, visibility }` (full object). Owner only.
4. `DELETE /api/tables/{domain}/filter-views/{filterView}` → 204. Owner only.

### Validation (`TableFilterViewRequest`, used by store + update)
- `name`: `required|string|max:80`, unique per `(user_id, domain)` ignoring the
  current view id on update.
- `visibility`: `required|in:private,shared`.
- `filters`: `present|array`; every key ∈ `filterableColumnIds()` (else 422 at
  `filterModel`-style path), via `withValidator`.

## Frontend contract

- `features/table/types.ts`:
  ```ts
  export type FilterViewVisibility = 'private' | 'shared'
  export interface TableFilterView {
    id: number
    name: string
    filters: Record<string, unknown>
    visibility: FilterViewVisibility
    owned: boolean
    owner_name: string | null
  }
  export interface FilterViewInput {
    name: string
    filters: Record<string, unknown>
    visibility: FilterViewVisibility
  }
  ```
- `features/table/filter-views-api.ts`: `listFilterViews(domain)`,
  `createFilterView(domain, input)`, `updateFilterView(domain, id, input)`,
  `deleteFilterView(domain, id)`.
- `features/table/use-filter-views.ts`: query key
  `filterViewKeys.list(domain) = ['table', domain, 'filter-views']`;
  `useFilterViews(domain)`, `useCreateFilterView(domain)`,
  `useUpdateFilterView(domain)`, `useDeleteFilterView(domain)` (mutations
  invalidate the list key).
- `features/table/filter-views-control.tsx`: toolbar control built on the existing
  `dropdown-menu` — lists views grouped "My views" / "Shared", applies a view on
  select via `onApply(filters)`, shows a trash affordance on owned views (delete
  mutation), and a "Save current filter…" item that opens the save form.
- Save form on the existing `sheet` (NO new ui primitive): `input` (name) +
  `select` (visibility: Private/Shared) + `button`. Submits the CURRENT filter
  model (passed in as a prop) through the create mutation.
- Wire into `table-view.tsx`: the control sits in the toolbar. `onApply(filters)` →
  `gridApi.setFilterModel(filters)` (this reuses the existing `onFilterChanged`
  auto-persist + SSRM refetch). `currentFilters` for the save form is
  `gridApi?.getFilterModel() ?? {}`. Gate the whole control on `gridApi` being
  ready and `config` loaded.
- i18n (en + it) under `table.*`: `savedFilters`, `saveCurrentFilter`,
  `viewNamePlaceholder`, `visibility`, `visibilityPrivate`, `visibilityShared`,
  `myViews`, `sharedViews`, `sharedBy` (`{{name}}`), `applyView`, `deleteView`,
  `save`, `cancel`, `viewSaved`, `viewSaveError`, `viewDeleted`, `viewDeleteError`,
  `duplicateViewName`, `noSavedViews`.

## Tests (Definition of Done — run them)

- Backend Pest `tests/Feature/Table/TableFilterViewsTest.php`: list (own + others'
  shared, excludes others' private), create (owned, 201), unique-name 422,
  non-filterable key 422, update by owner ok / by non-owner 403, delete by owner /
  non-owner 403, cross-domain bound id 404, unknown domain 404, unauth 401, missing
  viewAny 403. Coverage ≥90% on the new files.
- Frontend Vitest: `filter-views-api.test.ts` (URLs/payloads/envelope) and a
  `use-filter-views` / control test as feasible. `tsc --noEmit`, ESLint clean.
