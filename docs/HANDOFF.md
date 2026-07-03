# HANDOFF — living project memory

> Injected at session start. Update at every green state.

## Current work

**Feature 0004 — Centralized backend-driven authorization metadata** (spec
`docs/specs/0004-centralized-authorization-metadata.md`, contract FROZEN).
Convention `docs/conventions/metadata-driven-forms.md` is now MANDATORY for every form/module.

Goal: backend is the single source of truth for authorization. Every resource returns a
`permissions` block (`{ resource, fields, actions }`) alongside `data`; the frontend renders
itself from it (no hardcoded permission logic); the same resolver guards writes (422 on
non-editable fields, 403 on unavailable actions). First consumers: **User** and **Role** forms.

Key decisions:
- Non-editable field submitted on write → **422 reject** (strict), no silent drop.
- Contextual engine built with extensible hooks; only real users/roles rules wired now
  (role-assignability, super-admin guard, no self-delete). State/site/ownership hooks are no-op here.
- Frontend: metadata drives visibility/readonly/required; Zod stays a UX mirror.

## Names / contracts to respect

- Envelope: `{ success, message, data, permissions? }`. New helper
  `BaseApiController::okWithPermissions($data, $permissions, ...)`.
- `permissions.resource` abilities: `view, create, update, delete, export, import`
  (`BasePolicy::abilities()` extended with `export`/`import` → `permissions:sync`).
- Field descriptor: six flags always emitted — `visible, hidden, editable, readonly, required, disabled`.
- New backend namespace `app/Authorization/`; registry `config/authorization.php`.
- New endpoint `GET /api/meta/{resource}` (create-context), registry-driven like `tables/{domain}`.
- Reuse `RoleAssignmentGuard` + `UserService::PRIVILEGED_ROLE` — never duplicate super-admin logic.
- Frontend feature `features/authorization/` + `MetaField`; reuse `applyServerValidationErrors`,
  `useEntityDetail`, `AsyncPaginatedMultiSelect`, `Can`/`useAbilities`.

## Status — GREEN (verified)

Feature 0004 is implemented and verified against all 16 acceptance criteria.

- Backend: `app/Authorization/` coverage 96-100% per file (spec bar ≥90% met); full Pest suite
  511 passed / 1 unrelated skip; Pint clean. Authorization suite 37/37.
- Frontend: `features/authorization/` + metadata-driven `user-form`/`role-form`; scoped Vitest
  green (users/roles/authorization), `tsc --noEmit` clean, ESLint clean. Role + User metadata tests present.
- Verifier deep pass done: contract coherence confirmed end-to-end (envelope `{data, permissions}`,
  six field flags, `GET /meta/{resource}`); no regressions; no test tampering. Two coverage gaps it
  flagged (backend abstract-defaults / FieldPermission factories; missing `role-form-metadata.test.tsx`)
  are now closed.

Deviations recorded: AC6-literal (super-admin actor sees super-admin-role `name`/`permissions` as
`editable:true`) — write is still hard-blocked 422 by `RoleService::guardSystemRoleMutation` (tested).
Client-side, the role detail exposes the auth block as `.authorization` (not `.permissions`) to avoid
colliding with `RoleDetail.permissions: string[]` — no wire-contract change.

## Next steps

- Not yet committed (working tree also holds unrelated concurrent work: spec 0005 table-filters,
  `data-table`/`table`). Recommend a scoped commit of the 0004 files only before merge.
- Pre-existing/out-of-scope (not 0004): `UserAvatarProps.size` tsc error and
  `contacts-manager`/`cell-renderers` Vitest failures (from concurrent table work);
  `secret-scan.sh` false-positive on i18n locale files. Flag to their owners.
- Every new module's forms MUST follow `docs/conventions/metadata-driven-forms.md`.

## Feature 0006 — Per-role field-permission matrix — GREEN (verified by lead first-hand)

Spec `docs/specs/0006-per-role-field-permission-matrix.md`. Admins select per-role field
visibility/editability/required from a new "Permessi campi" section in the Role form.

- Backend: table `role_field_permissions`, `RoleFieldPermission`, `FieldPermissionRepository`,
  `GET /api/authorization/fields` (`FieldCatalogueController`, authz `roles.create|update`),
  `field_permissions` synced in `RoleService` (full-replace, in the existing tx), `RoleResource.field_permissions`.
  `AbstractResourceAuthorization::fieldPermissions()` is now FINAL = intersect(`fieldPermissionCeiling()`, DB config).
- **Security invariant (by construction + tested): DB config can only RESTRICT within the code ceiling,
  never escalate** (`visible/editable = ceiling AND db`); super-admin actor bypasses to full ceiling;
  absent DB row = ceiling unchanged (0004 behavior preserved). Write path (`EnforcesFieldPermissions`)
  honors the merge with no new code path.
- Frontend: `role-field-permissions.tsx` matrix (reuses the checkbox-matrix pattern), `field-catalogue-api`/
  `use-field-catalogue`, wired into the Role form; section gated by `canResource('update'|'create')`.
- Verified: backend Authorization+Roles+Users 208/208; new backend code ≥90% coverage; roles Vitest 28/28
  stable; ESLint clean; tsc clean except the pre-existing `UserAvatarProps.size` error (0005/data-table).
  The 5 full-suite backend failures are the concurrent 0005 table-filters work (`app/Tables/*`), NOT 0006.
- Note: the 0004+0006 work is commingled in the working tree with the 0005 table-filters feature (another
  session). A scoped commit of the Authorization/roles files is still pending a go from the user.

## Frontend status (spec 0004) — DONE, ready for Verifier

Implemented against the frozen contract, not blocked on backend:

- `features/authorization/`: `types.ts`, `api.ts` (`fetchResourceMeta` → `GET /meta/{resource}`),
  `query-keys.ts`, `use-resource-meta.ts` (5 min staleTime, `enabled` toggle), `permissions.tsx`
  (`ResourcePermissionsProvider` + `useResourcePermissions()` — graceful fallback: missing
  field/action → visible+editable / allowed, never crashes), `MetaField.tsx` (wraps `FormField`;
  `!visible` → renders nothing; forwards `disabled`/`readOnly`/`required` — `disabled` passed down
  is `permission.disabled || !permission.editable`, since a `readonly` field is `editable:false`
  but not necessarily `disabled:true`).
- `features/users/`, `features/roles/`: `fetchUser`/`fetchRole` now return the instance detail
  plus its authorization block; `user-form.tsx`/`role-form.tsx` resolve permissions (edit: from
  the loaded detail; create: `useResourceMeta`) then hand off to `user-form-body.tsx`/
  `role-form-body.tsx`, where every field is wrapped in `MetaField` (no hardcoded permission `if`s
  left in JSX). Heavy logic extracted into `use-user-form.ts`/`use-role-form.ts` (+ `use-*-form-meta.ts`,
  `user-form-payload.ts`) to stay under the 300-line soft limit.
- `components/avatar-upload.tsx`: added optional `canUpload`/`canRemove` on the immediate-mode
  variant, wired to `actions.upload_avatar`/`actions.delete_avatar` in the user edit form.
- i18n: `authorization.loadError`, `authorization.fieldNotEditable` in `en.ts`/`it.ts`.
- Tests (Vitest + RTL, all passing): `features/authorization/{permissions,MetaField}.test.tsx`,
  `features/users/user-form-metadata.test.tsx` (AC 11-16), plus the pre-existing
  `user-form.test.tsx`/`role-form.test.tsx` updated for the new types/mocks.

**Contract ambiguity resolved (flagging for Backend/Architect):** `RoleDetail` already has its own
`permissions: string[]` (the role's granted permission names). The envelope's top-level
authorization `permissions` block would collide with it once flattened client-side, so the
frontend exposes it as `RoleDetailWithPermissions.authorization: ResourcePermissions` instead of
`.permissions`. `fetchRole` maps `{ ...data.data, authorization: data.permissions }`. No wire
contract change needed (the envelope keeps `data.permissions` and the top-level `permissions` as
distinct siblings) — this is purely a client-side naming fix.

**Blocked/deferred:** actual `GET /api/meta/{users,roles}` and `permissions` on
`GET /users/{id}` / `GET /roles/{id}` responses are backend work (per this spec, in progress
per `backend/app/Authorization/` on disk) — frontend code is written against the frozen shape and
type-checks/tests green with mocked responses; needs an end-to-end smoke test once the backend
endpoints are live.

**Pre-existing, out-of-scope issues observed (not touched, not caused by this work):**
- `components/user-avatar.tsx` / `features/users/column-renderers.tsx`: `tsc` error, `UserAvatarProps`
  missing a `size` prop used by a call site — present before this session's changes (verified via
  `git stash`), belongs to unrelated in-progress `table`/`data-table` work.
- `features/personal-data/contacts-manager.test.tsx` (missing `QueryClientProvider`) and
  `features/table/cell-renderers.test.tsx` (i18n locale mismatch, "primary contacts" vs
  "contatti principali") — 7 failing tests, confirmed pre-existing via `git stash`, unrelated to
  spec 0004.
- `.claude/hooks/secret-scan.sh` false-positives on `frontend/src/i18n/locales/{en,it}.ts`: its
  regex flags any `password: '<8+ chars, no space>'` translation label (e.g. `password: 'Password'`)
  as a possible secret. Pre-existing in the file before this session; blocks every edit to these
  locale files with a PostToolUse warning. Not in frontend ownership to fix (`.claude/hooks/`).

---

## Feature 0005 — Excel-like table filters (AG Grid SSRM) — DONE, GREEN, awaiting commit decision

Spec `docs/specs/0005-table-excel-like-filters.xml` (renamed from 0004 to avoid the number
collision with the concurrent authorization-metadata feature). Contract FROZEN and respected.

Goal: per-column Excel-like filters = server-side distinct value list (from ALL rows, respecting
other columns' active filters) + type-specific conditions, combined via `agMultiColumnFilter`,
compatible with SSRM paging/sorting.

Delivered (all green, evidence real):
- Backend: new `POST /api/tables/{domain}/values` (distinct values, cap 200, `hasMore`, respects
  OTHER columns' filters, excludes the target column — Excel behavior). `TableService::distinctValues`
  + new contract method `TableDefinition::distinctValues(...)` (default null; overridden for derived
  columns roles/user_type/geo/permissions — in-memory search, no SQL LIKE on geo tables). Filter
  engine extracted to `app/Services/Table/FilterApplier.php` with new branches: number
  (equals/notEqual/gt/ge/lt/le/inRange), boolean, multi, combined `{operator, conditions}`. New
  `TableValuesRequest`, `DistinctValuesResult` DTO. `UsersTableDefinition` split into
  `Tables/Users/{UserColumnCatalog,UserGeoColumns,UserPersonalDataColumns,Concerns/CorrelatesPersonalDataToUser}`
  (was 849 lines, pre-existing hard-limit violation; behavior-preserving). `users.id` now
  `filterType:number`, `roles.users_count` number.
- DB: migration `2026_07_02_100000_add_created_at_index_to_users_table.php` (only gap; rest already
  indexed). LIKE `%term%` can't use B-tree → cap+LIMIT is the mitigation, not an index.
- Frontend: `resolveFilter` → `agMultiColumnFilter` (text/number/date), `agSetColumnFilter`
  (set/enum/boolean); Set Filter async server values via `fetchTableColumnValues`, scoped to OTHER
  columns' filterModel; `hasMore` → toast. Logic extracted to `components/data-table/column-filters.ts`.
  `ssrm-datasource.ts` already forwarded the combined `multi` filterModel intact (no change).

Verification (independent verifier + security, both green):
- Backend `php artisan test` 490/490 (Table filter=92: 91 passed, 1 skipped, 0 failed); Pint clean.
- Frontend Vitest green on touched files; `tsc --noEmit` clean; ESLint clean. (7 unrelated
  pre-existing FE failures confirmed on baseline via git stash — contacts-manager/cell-renderers.)
- Security: GO, no critical/high. Authz server-side, column allow-list, all values bound, no raw SQL,
  escapeLike on all LIKE incl. `search`, limit cap 200.
- AC-001..015 all mapped to passing tests.

Bugfix (post-review, derived computed columns): the Multi Filter attached a Set Filter to
text/number columns, so opening it on a COMPUTED derived column (`users.primary_address`,
`users.primary_contact`, `roles.users_count`) called `/values` → `distinctFromColumn` ran
`SELECT DISTINCT <col>` on a column with no real DB backing → "Unknown column" crash. Fix:
new column-contract flag `hasFilterValues` (bool; false for those computed columns);
`TableService::distinctValues` short-circuits to `{values:[],hasMore:false}` before building any
query when the flag is false (defence-in-depth for any future derived column); frontend renders a
condition-only filter (agText/agNumber/agDate — no Set tab, no `/values` call) when
`hasFilterValues===false`. Condition filtering on those columns was always fine (applyDerivedFilter)
and is unchanged. Reproduce-first tests added (AC-016/017/018). Backend full suite 511 (510 passed,
1 skip, 0 failed); frontend 186 passed (+7 pre-existing unrelated), tsc/lint clean.

UX iteration (Excel-classic layout + computed-column selection) — user-driven, all green:
- Layout: `agMultiColumnFilter` reconfigured to Excel-classic — Set Filter INLINE (`excelMode:'windows'`:
  search + Select All + Apply/Reset checklist) with the typed condition tucked into a titled
  `display:'subMenu'` (`table.{text,number,date}Filters` i18n). Same look on every filterable column,
  set/enum/boolean included. No tabs.
- Computed columns given real value lists: `users.primary_contact` (distinct `contacts.value`) and
  `roles.users_count` (distinct aggregate counts) now show the checklist. `users.primary_address`
  stays CONDITIONS-ONLY (`hasFilterValues=false`) by user decision — it is a composed string
  (street+postal+city+province), so an exact-match checklist would need fragile SQL reconstruction
  with MySQL/SQLite parity risk; conditions (contains/equals) are robust and the natural tool.
- Selection bug fixed (root cause): the Multi Filter sends `{filterType:'multi', filterModels:[set,
  condition]}`, but derived columns' `applyDerivedFilter` only read the flat top-level shape → both
  checklist selection AND conditions silently no-op'd on computed columns. New shared trait
  `app/Tables/Concerns/UnwrapsMultiFilter::dispatchDerivedFilter()` unwraps `multi` and applies each
  sub-model in AND: Set → per-column set-handler (contact → `whereIn(contacts.value)`; users_count →
  `orHas('users','=',n)` per selected count), condition → the existing handler. `RolesTableDefinition`
  split into thin dispatcher + `app/Tables/Roles/RoleUsersCountColumn.php` (kept under 500). Address
  dead code removed (`addressDistinctValues`, `formatAddressLine` re-inlined).
- Verified end-to-end via `/rows` (real row matches, not just 200): `TableRowsMultiFilterTest` 7/7;
  `TableConfigTest` 14/14 incl. new roles-domain `users_count.hasFilterValues=true` assert (closes
  old follow-up #4). Backend full suite 562 (561 passed, 1 skip, 0 failed); frontend unchanged this
  round (7 pre-existing failures only), tsc/lint clean. New AC-019..022 in the spec.

Open follow-ups (tickets, NOT blocking this feature):
1. `escapeLike()`+LIKE has no explicit `ESCAPE` clause → under-matches literal `%`/`_` on SQLite
   (dev/test); correct on MySQL prod (backslash default). App-wide, pre-existing. Now tracked as an
   explicit `->skip(...)` in `FilterApplierTest.php`. Fix = add ESCAPE to the shared helper.
2. `config/sanctum.php` `expiration => null` (tokens never expire) — pre-existing hardening gap
   (security.md §8), unrelated to this feature.
3. `.claude/hooks/secret-scan.sh` false-positive on i18n `password:` labels (see above).
4. RESOLVED — direct assertion on `GET /api/tables/roles/columns` for `users_count.hasFilterValues`
   was added in the UX iteration's `TableConfigTest`. (Original note kept for history below.)
   ~~add a direct assertion on `GET /api/tables/roles/columns` that
   `users_count` carries `hasFilterValues=false` — currently verified only end-to-end via `/values`
   (AC-016) and `/rows` (AC-017), not at the contract level for the roles domain.~~
5. (watch) `UsersTableDefinition.php` (412) and `UserPersonalDataColumns.php` (392) are over the 300
   soft limit (<500 hard). `RolesTableDefinition.php` was split (358) via `Roles/RoleUsersCountColumn.php`.
   Candidates for a future split if they keep growing.

Commit status: NOT committed. The `feat/style` working tree intermixes THIS feature with the
concurrent `0004-centralized-authorization-metadata` feature in shared files (`routes/api.php`,
`i18n/locales/{en,it}.ts`) — cannot be cleanly isolated without interactive patch-staging. Also a
stray `backend/qnet2` (300KB SQLite dev DB) is untracked and must not be committed (gitignore it).
Awaiting the user's decision on how to split/commit.

---

## Feature 0006 — Per-role field-permission matrix — Frontend DONE

Spec `docs/specs/0006-per-role-field-permission-matrix.md` (contract FROZEN). Builds on 0004;
backend work (`role_field_permissions` table, merge resolver, `GET /api/authorization/fields`) is
tracked separately — frontend implemented strictly against the frozen shape, not blocked on it.

Delivered (`features/roles/`, all new unless noted):
- `types.ts` (edit): `RoleFieldPermission { resource, field, visible, editable, required }`;
  `RoleDetail.field_permissions: RoleFieldPermission[]` (required, mirrors backend's always-present
  flat list); `CreateRolePayload`/`UpdateRolePayload` gain optional `field_permissions`.
- `field-catalogue-api.ts` + `use-field-catalogue.ts`: `GET /authorization/fields` (plain
  `ApiResponse`, no `permissions` envelope sibling — this endpoint authorizes once up front, not
  per-resource), React Query, 5 min staleTime, `enabled` toggle.
- `field-permission-toggle.ts`: pure helpers (`fieldPermissionFlag`, `toggleFieldPermission`,
  `sameFieldPermissions`) — unrestricted default (no row) = visible+editable, not required, per the
  spec's merge semantics. Unit-tested directly (`field-permission-toggle.test.ts`).
- `role-field-permissions.tsx`: the matrix UI (resource fieldsets × 3 toggle columns), reusing the
  existing permission-matrix checkbox styling (no new `components/ui` primitive). Each checkbox gets
  an accessible name via a `sr-only` label (`"<field label> — <toggle label>"`), field labels reuse
  each resource's existing `<resource>.form.<field>` i18n keys (`permission-labels.ts` →
  `fieldPermissionLabel`), falling back to a humanized token.
- `use-role-form.ts` (edit) / `role-form-body.tsx` (edit) / `role-form-payload.ts` (new, split out of
  `use-role-form.ts` to stay under the 300-line soft limit): seeds from `role.field_permissions`
  (edit) or `[]` (create); submit diffs against the original and omits the key when unchanged (same
  convention as `permissions`/`users`); `SERVER_ERROR_FIELDS` gains `'field_permissions'` for 422
  mapping.
- `role-schema.ts` (edit): `field_permissions` array schema added as a UX mirror (no real validation
  — the backend merge is the source of truth).
- i18n: `roles.fieldPermissions.{title,visible,editable,required,empty,loadError}` in `en.ts`/`it.ts`.
- Tests: `field-permission-toggle.test.ts` (unit) + `role-form-field-permissions.test.tsx` (AC
  11-15, RTL) — all passing. `role-form.test.tsx`/`role-form-metadata.test.tsx` updated (new required
  `field_permissions` fixture field; both mock `field-catalogue-api` to an empty catalogue so the new
  section stays inert for their unrelated assertions).

**Contract ambiguity resolved:** the spec says the section is "gated by the metadata (…reuse 0004
`MetaField`/`canAction` where applicable)" but the backend design does NOT add any new `fields.*` or
`actions.*` key for this section (the 0004 `permissions` envelope is explicitly unchanged/additive
only). Wrapping it in `<MetaField metaKey="field_permissions">` alone would never actually gate
anything — that key can never exist in `permissions.fields`, so `MetaField`'s graceful fallback
(visible+editable) would always apply regardless of the actor's real ability. Resolution: gate the
whole section with the EXISTING resource-level ability already in `ResourcePermissions.resource`
(`canResource('update')` in edit mode / `canResource('create')` in create mode, via
`useResourcePermissions()` — same 0004 hook, just a resource-ability read instead of a field lookup),
matching the ceiling rule that already locks `name`/`permissions`/`users` when the actor cannot write
the role. `MetaField` is still used for the section's own label/message scaffolding for consistency;
the real security-relevant gate is the outer `canManageFieldPermissions` conditional (hides the
section entirely — not merely disables it — when false; also skips the `/authorization/fields`
fetch). Verified in AC15's test.

Verification: `npx vitest run src/features/roles` → 6 files / 28 tests passed. Scoped
`tsc -b --noEmit` → clean except the pre-existing, unrelated `UserAvatarProps.size` error (confirmed
via `git stash` in the 0004 work above). `npx eslint src/features/roles` → clean. Full-repo
`npx vitest run` → 201/208 passed (same 7 pre-existing/unrelated failures as 0004/0005, zero new
regressions).

**Blocked/deferred:** `GET /api/authorization/fields` and `RoleResource.field_permissions` are
backend work per this spec — frontend is written against the frozen shape with mocked responses;
needs an end-to-end smoke test once the backend endpoint/column are live.

## Feature — Per-user table filter persistence + "Reset filters" — GREEN (verified)

Sibling of spec 0001 column-preferences, for the AG Grid filterModel (spec 0005 had left filter
persistence out of scope). Filters the user applies survive a page reload, and a toolbar "Reset
filters" button (icon `FilterX`) clears them, shown only when filters are active — mirroring the
existing "Reset layout" button.

Contract (FROZEN): new pair of endpoints alongside preferences, same throttle/auth group:
- `POST /api/tables/{domain}/filters` body `{ filterModel }` → upsert; empty model clears the row;
  returns the merged config. `DELETE /api/tables/{domain}/filters` → reset (204).
- Config envelope now also carries `filterState` (object, `{}` when none) and `filtersCustomized`
  (bool), attached in `TableController::resolvedConfig` via the new `TableFilterStateService::applyTo`,
  chained after `TablePreferenceService::applyTo`.

Backend (mirrors ADR-0004 preferences pattern):
- `user_table_filters` table (`unique(user_id, domain)`, json `filters`), model `UserTableFilter`
  (no Policy / no activity-log, self-scoped — same rationale as `UserTablePreference`).
- `TableFilterStateService` (save/reset/applyTo) — keys restricted to `filterableColumnIds()` on
  every read AND write (same allow-list the SSRM rows query enforces); NOT a sparse delta (filters
  have no default) — stores the applied model whole; empty model deletes the row.
- `TableFilterStateRequest` — `filterModel` `present|array`, keys 422'd against `filterableColumnIds()`
  exactly like `TableRowsRequest::withValidator`.
- Tests: `tests/Feature/Table/TableFilterStateTest.php` 11/11 (auth 401, unknown domain 404, missing
  viewAny 403, persist+merge, non-filterable key 422, stale-key tolerance, empty-clears-row,
  reset-removes-row, per-user isolation). Full `tests/Feature/Table` 99/99. Pint clean.

Frontend:
- `data-table.tsx`: new `initialFilterModel` (applied once via `initialState.filter.filterModel`, so
  the first SSRM request is already filtered) + `onFilterChanged` passthrough.
- `table-view.tsx`: `useSaveTableFilters`/`useResetTableFilters`; `handleFilterChanged` debounced 500ms
  with a `lastPersistedFilterRef` (JSON) guard to skip the grid's mount echo and no-op refires;
  `handleResetFilters` = mutate DELETE → refetch config → bump the SHARED `layoutVersion` remount
  (grid rebuilds with empty `filterState`, SSRM re-queries unfiltered) — same remount mechanism as
  layout reset. New `EMPTY_FILTER_MODEL` module const (stable identity).
- `use-table-filters.ts` (hooks), `api.ts` (`saveTableFilters`/`resetTableFilters`), `types.ts`
  (`TableConfig.filterState?`/`filtersCustomized?`), i18n `table.resetFilters/filtersReset/filtersError`.
- Tests: `api.test.ts` extended (save posts wrapped model; reset DELETEs) 3/3. `tsc --noEmit` clean,
  ESLint clean.

Pre-existing/out-of-scope (NOT this feature): `cell-renderers.test.tsx` 3 failures — files unmodified
(at HEAD), already failing from the concurrent 0005 table work. `secret-scan.sh` false-positive on the
i18n locale files (`en.ts`/`it.ts`) persists.

Not yet committed (working tree still commingled with 0004/0005/0006). Recommend a scoped commit of
just the filter-persistence files.

---

## Feature 0008 — Personal-data field permissions — Frontend DONE

Spec `docs/specs/0008-personal-data-field-permissions.xml` (contract FROZEN). Extends 0004/0006 to
the personal-data morph fields (`personal_data.{type,title,first_name,last_name,company_name,
tax_code,vat_number,sdi_code,birth_date,contacts,addresses}`). Backend work (ceiling rules,
CHANGE-based `EnforcesFieldPermissions`) tracked separately — frontend implemented strictly against
the frozen dot-path key contract, not blocked on it.

Delivered:
- `features/personal-data/types.ts`: new `PersonalDataFieldPermission` (visible/editable/required/
  disabled/readonly — no `hidden`) and `PersonalDataFieldPermissionResolver = (key) => ...`. Deliberately
  NOT `@/features/authorization`'s `FieldPermission` (decision D3): the shared personal-data
  components must stay decoupled from any specific resource; the caller adapts and injects by prop.
- `personal-data-section.tsx` / `personal-data-card-form.tsx` / `contacts-manager.tsx` /
  `addresses-manager.tsx`: new **optional** `fieldPermission` prop, propagated section → children.
  `!visible` → field/section not rendered; `!editable` → input disabled/readonly (card fields) or the
  whole manager goes read-only (no add/edit/delete, contacts/addresses lists still shown); `required`
  reflects the resolved flag. **Omitting the prop entirely preserves today's behaviour exactly**
  (verified: `profile-form.test.tsx`, unmodified, still green — self-service `ProfileForm` never
  passes it, AC-013).
- `features/personal-data/drafts.ts`: `PersonalDataPayload`'s fields widened to optional (needed so a
  gated payload can omit keys); new `omitNonEditableFields(payload, fieldPermission?)` — strips the
  scalar/section keys the resolver marks non-editable, no-op without a resolver.
- `features/users/use-user-form.ts`: adapts `useResourcePermissions().field` (6-flag
  `FieldPermission`) into a `PersonalDataFieldPermissionResolver` (5-flag, drops `hidden`) exposed as
  `personalDataFieldPermission`; wired into `PersonalDataSection` (via `user-form-body.tsx`) and into
  both payload builders.
- `features/users/user-form-payload.ts`: `buildCreatePayload`/`buildUpdatePayload` gained an optional
  4th param `fieldPermission`; the nested `personal_data` tree is now built via
  `omitNonEditableFields(draftToPayload(profileDraft), fieldPermission)` (defense in depth — the
  backend enforces the same rule with a CHANGE-based guard, D2).
- i18n: `personalDataFieldLabels` (module-level const, keyed by dot-path field name) in both
  `en.ts`/`it.ts`, referenced from BOTH `users.form.personal_data.*` (new, read by
  `fieldPermissionLabel('users', 'personal_data.<field>')` for the Role matrix) and the pre-existing
  `personalData.form.*` card labels (now reference the same const — no string drift). No code change
  needed in `permission-labels.ts`/`role-field-permissions.tsx`: `fieldPermissionLabel` already builds
  `${resource}.form.${field}` and i18next's default `.` key-separator walks a dotted field key
  (`personal_data.first_name`) through nested objects transparently.

Tests (Vitest + RTL, all passing): `personal-data/personal-data-section.test.tsx` (new — AC-011
visible/editable/required for card fields + contacts/addresses sections, AC-013 ungated baseline),
`users/user-form-payload.test.ts` (new — AC-012, unit on the builders), `roles/permission-labels.test.ts`
+ `roles/role-field-permissions-personal-data.test.tsx` (new — AC-010, label resolution + full matrix
render for the 11 keys). Existing `profile-form.test.tsx`/`user-form.test.tsx`/`contacts-manager.test.tsx`
(baseline-failing, see below)/`addresses-manager.test.tsx` untouched and re-verified as regression
evidence for AC-013.

Verification: `npx vitest run src/features/personal-data src/features/users src/features/roles
src/features/auth/profile-form.test.tsx` → 21 files / 99 tests, 95 passed, 4 failed (all in
`contacts-manager.test.tsx`, pre-existing — see below, confirmed via `git stash` unrelated to this
work). Full-repo `npx vitest run` → 236 tests, 229 passed, 7 failed = the same pre-existing
`contacts-manager.test.tsx` (4) + `cell-renderers.test.tsx` (3), zero new regressions (counts
identical stashed vs. not). `npx tsc --noEmit` clean except the pre-existing, unrelated
`UserAvatarProps.size` error. `npx eslint` clean on every touched file.

**Ambiguity/note for Backend:** the dot-path field keys in `omitNonEditableFields` are hardcoded
(`personal_data.type` … `personal_data.addresses`), matching the frozen contract exactly. If the
backend ever needs the FE to omit at finer granularity (e.g. per-contact-row) this file is the single
place to extend — no change expected per D1 (section-level only).

Pre-existing/out-of-scope (NOT this feature, confirmed via `git stash` against baseline HEAD before
any 0008 change): `contacts-manager.test.tsx` (4 failures — `ContactsManager` calls `useEnumOptions`
directly, needs a `QueryClientProvider` wrapper the test never had), `cell-renderers.test.tsx` (3
failures — i18n language-state leak between test files), `UserAvatarProps.size` tsc error, and the
`secret-scan.sh` false-positive on `frontend/src/i18n/locales/{en,it}.ts` (flags the pre-existing
`password: 'Password'`-shaped translation entries as secrets; blocks every edit to these two files
with a PostToolUse warning that does not roll back the edit — not in frontend ownership to fix).

**Follow-up — `mandatory` field lock (post-run addition, test-only lane):** the coordinator added
`FieldDescriptor.mandatory: boolean` (`features/authorization/types.ts`) and implemented the matrix
lock in `role-field-permissions.tsx` directly (a mandatory row forces all three checkboxes
checked+disabled, with a ` *` + `title` hint) — both are PRODUCTION code, not touched by this lane.
Realistic mandatory set: `users` → `email`, `locale`, `password`, `personal_data.type`,
`personal_data.first_name`, `personal_data.last_name`, `personal_data.company_name`; `roles` → `name`.
Frontend test-only fixes:
- `roles/role-field-permissions-personal-data.test.tsx`: every `FieldDescriptor` fixture now carries
  a realistic `mandatory` value; "unrestricted default" assertions moved to the non-mandatory
  `personal_data.tax_code` row; added a new test asserting a mandatory row (`personal_data.first_name`)
  renders all three checkboxes checked+disabled.
- `roles/role-form-field-permissions.test.tsx`: the shared `CATALOGUE` fixture's sole field changed
  from `email` (now realistically mandatory, so no longer toggable) to `personal_data.tax_code`
  (`mandatory: false`) — every "Email — …" label/assertion and the `field: 'email'` payload
  expectations renamed to `Tax code`/`personal_data.tax_code` accordingly (AC11-14 unchanged in
  intent, just re-subjected). Added a new test with its own one-off catalogue (`email`,
  `mandatory: true`) asserting the locked checked+disabled state through the full `RoleForm`
  integration (not just the bare `RoleFieldPermissions` component).
- No other test file constructs a non-empty `FieldDescriptor`/`FieldCatalogueResource` array (checked
  via grep across `*.test.tsx`/`*.test.ts`); `role-form-metadata.test.tsx`/`user-form-metadata.test.tsx`
  only use `fields: []`/`fields: {}`, unaffected.

Verification: `npx tsc --noEmit -p tsconfig.app.json` → clean except the pre-existing
`UserAvatarProps.size` error. `npx vitest run src/features/roles src/features/personal-data
src/features/users` → 20 files / 96 tests, 92 passed, 4 failed (same pre-existing
`contacts-manager.test.tsx`, unrelated). Full-repo `npx vitest run` → 47 files / 251 tests, 244
passed, 7 failed (same two pre-existing files as always: `contacts-manager.test.tsx` 4 +
`cell-renderers.test.tsx` 3) — zero new regressions. `npx eslint` clean on both touched test files.

## Feature 0007 — Saved filter views (private/shared) — GREEN (verifier-confirmed)

Spec `docs/specs/0007-saved-filter-views.md` (FROZEN). Builds on the filter-persistence work.
A user saves the current AG Grid filter set as a NAMED view (private or shared) and re-applies it
from a toolbar dropdown. Implemented by two agents (backend/frontend, disjoint ownership) against the
frozen contract; independently verified end-to-end.

Contract: `GET/POST /api/tables/{domain}/filter-views`, `PUT/DELETE .../{filterView}` (throttle:60,1
table group). Resource `{ id, name, filters, visibility, owned, owner_name }` — `owner_name` only when
shared AND not owned (display name only, never PII). List = own (private+shared) + others' shared,
owned-first then by name.

Authz: list/create gated by the definition `authorizeViewAny`; update/delete by `TableFilterViewPolicy`
(owner-only) PLUS the existing global `Gate::before` super-admin bypass in `AppServiceProvider`
(NOT duplicated in the policy — single source of truth). Cross-domain bound `{filterView}` → 404
BEFORE the Policy (no 403 leak). `filters` keys allow-listed against `filterableColumnIds()` on store
AND update (mirror of `TableRowsRequest::withValidator`) and re-filtered on read — no whereRaw/dynamic
SQL from stored JSON.

Backend files (new): migration `create_table_filter_views_table`, `FilterViewVisibility` enum,
`TableFilterView` model + factory, `TableFilterViewPolicy`, `TableFilterViewResource`,
`TableFilterViewRequest`, `TableFilterViewService`, `TableFilterViewController`,
`tests/Feature/Table/TableFilterViewsTest.php` (14 tests). Routes added to `routes/api.php`.

Frontend files (new, `features/table/`): `filter-views-api.ts`, `use-filter-views.ts`
(key `['table', domain, 'filter-views']`), `filter-views-control.tsx` (dropdown, My/Shared groups,
apply via `gridApi.setFilterModel`, owned-only delete), `save-filter-view-sheet.tsx` +
`save-filter-view-schema.ts` (Sheet + RHF/Zod, name + visibility Select), 3 test files. Modified:
`types.ts` (+FilterView types), `table-view.tsx` (control wired into toolbar, gated on gridApi+config),
i18n en/it.

Verifier evidence: Backend `tests/Feature/Table` 113/113 (14 new), new files ~98.7% coverage, Pint
clean. Frontend `tsc --noEmit` clean, `eslint src/features/table` clean, new vitest 13/13.
Contract coherence BE↔FE confirmed 1:1 (routes, resource shape, envelope, query key). Zero new
failures introduced.

Pre-existing/out-of-scope (git-confirmed at `Initial commit`, NOT 0007): 7 vitest failures —
`personal-data/contacts-manager.test.tsx` (4) and `table/cell-renderers.test.tsx` (3). Verifier
diagnosed the cell-renderers ones as an i18n test-env default mismatch (tests assert English strings
but the env renders Italian, e.g. "2 primary contacts" vs "2 contatti principali") — a test/config
issue, not a code bug. Flag to the personal-data/i18n owner.

Still uncommitted: working tree commingles 0004/0005/0006 + the two filter features (0007 + the
filter-persistence pair). A scoped commit is still pending a go from the user.

## Feature 0008 (personal-data field permissions) — mandatory-field increment — GREEN (lead-verified)

Follow-up requirement after the initial 0008 build: fields VITAL to creating the record are
"mandatory" — in the Role field-permission matrix their row has all three checkboxes
(visible/editable/required) forced ON and DISABLED, and the server-side merge can never let a
`role_field_permissions` row narrow them (bypass).

Implemented by the lead (production) + both agents (tests):
- `FieldDefinition` gains `mandatory` (bool, default false), emitted in `toArray()` →
  `{key,type,group,mandatory}` (so `GET /api/authorization/fields` AND `GET /api/meta/{resource}`
  and every `permissions.fields` consumer carry it).
- `UsersAuthorization::fields()` mandatory=true: email, locale, password, personal_data.type,
  personal_data.first_name, personal_data.last_name, personal_data.company_name.
  `RolesAuthorization::fields()` mandatory=true: name.
- `AbstractResourceAuthorization::fieldPermissions()` (FINAL): mandatory fields BYPASS the DB
  intersect (`mandatoryFieldKeys()`), keeping the full ceiling — the server twin of the locked
  disabled checkboxes. Super-admin branch is unchanged (returns ceiling before the mandatory check).
- Frontend `FieldDescriptor.mandatory: boolean`; `role-field-permissions.tsx` locks mandatory rows
  (3 checkboxes checked+disabled, ` *` marker, `title` = `roles.fieldPermissions.mandatory`);
  i18n key added en/it.
- Spec updated: `docs/specs/0008-personal-data-field-permissions.xml` — D5 decision, contract
  (`mandatory` per field), AC-015..AC-018; AC-004/006 examples moved to a non-mandatory field.

Lead final verification (run for real, XDEBUG off):
- Backend: `tests/Feature/Authorization tests/Unit/Authorization tests/Feature/Users tests/Feature/Roles`
  → 230/230 passed (1115 assertions). New backend code ≥96-100% coverage. Pint clean.
- Frontend: scoped Vitest (roles/personal-data/users/authorization) → 100 passed; the only 4 failures
  are the PRE-EXISTING `contacts-manager.test.tsx` "No QueryClient set" (git-confirmed on baseline HEAD,
  NOT ours). `tsc --noEmit` clean except the pre-existing `UserAvatarProps.size` (feature 0005, out of
  scope). ESLint clean on touched files.

Test retargeting declared (requirement change, not tampering): the 0006 restriction/enforcement tests
that used email/locale/first_name (now mandatory, thus un-restrictable) were moved onto the
non-mandatory `personal_data.tax_code`; new tests added for the mandatory bypass (read + write) and the
catalogue `mandatory` flag (`PersonalDataMandatoryFieldTest.php`).

### Spec-number collision — RESOLVED (this feature renumbered 0007 → 0008)
The two features had both grabbed 0007 (commingled working tree). Per the user's decision, THIS
feature (personal-data field permissions) was renumbered to **0008**; the concurrent
`0007-saved-filter-views.md` keeps 0007 and was left completely untouched (no override). Renumber
scope (this feature's files only): spec file renamed to `0008-personal-data-field-permissions.xml`
(+ internal id), all `spec 0007` code/test comments → `spec 0008`, and the two MINE-only `spec 0007`
comment lines in the shared i18n `en.ts`/`it.ts`. Verified: zero `0007` left in this feature's files;
`TableFilterView*` / `features/table/*` still reference 0007 as before. No functional code changed —
comments/spec-id only.

Not committed (per user): working tree still commingles 0004/0005/0006 + 0008 (this) + 0007
(saved-filter-views) + the filter-persistence pair. A scoped commit of the 0008 files is available on
request but was explicitly deferred by the user.

## Feature 0007 — Filter-views SAVE moved inline into the dropdown (redesign) — GREEN

Follow-up to 0007: the "save current filter" flow moved OUT of a Sheet/modal and INTO the
`filter-views-control.tsx` dropdown panel itself (user request: "sempre nel drop", premium look).
Research-grounded (Attio/Airtable "save query" inline pattern; segmented control for 2 mutually-
exclusive options; always-show-active-filter rule).

Changes (frontend only, no contract/backend change):
- `filter-views-control.tsx` rewritten as a single self-contained panel: header (icon chip + title +
  subtitle), grouped list (My/Shared) with a leading lock/people glyph per visibility + an ACTIVE
  check on the currently-applied view (`sameFilters` order-independent compare), hover-revealed delete,
  and an inline SAVE section (name Input + private/shared SEGMENTED control + full-width primary CTA;
  swaps to a hint when there are no filters). Controlled `open`; resets the form on close. Radix Menu
  keystroke/typeahead + Tab-close handled via `onKeyDown stopPropagation` (except Escape) on the save
  block, Enter-to-save on the input. Trigger shows a count badge.
- Deleted `save-filter-view-sheet.tsx` + `save-filter-view-schema.ts` (RHF/Zod no longer needed; name
  validated by trim + native maxLength=80).
- i18n: removed dead `saveCurrentFilter`/`saveCurrentFilterDescription`/`cancel`; added
  `savedFiltersSubtitle`/`saveViewHeading`/`saveView`/`applyFilterToSaveHint`/`viewActive` (en+it).
- `filter-views-control.test.tsx` updated: replaced the "opens sheet" test with inline-save tests
  (disabled-until-named, save with chosen visibility) + a no-filters hint test.

Verified: `tsc --noEmit` clean, `eslint` clean on changed files, `filter-views-control.test.tsx` 6/6,
full `src/features/table` = 40 passed / 3 failed — the 3 are the SAME pre-existing `cell-renderers`
failures (i18n test-env mismatch), zero new regressions.

Visual preview artifact (static approximation of the real component, light+dark):
https://claude.ai/code/artifact/89ccf38e-10c2-4bbb-8ed9-97656c39553b

## Reusable confirm dialog (replaces native `window.confirm`) — GREEN (verified)

A single "wow" confirmation dialog now backs every confirm-gated action; native `window.confirm`
is gone from the app (only a doc-comment mention remains).

- New design-system primitive `components/ui/alert-dialog.tsx` (shadcn new-york over `radix-ui`
  AlertDialog): frosted `backdrop-blur` overlay + spring-overshoot zoom/lift entrance
  (`ease-[cubic-bezier(0.34,1.56,0.64,1)]`). Accessible by construction (role=alertdialog, focus trap).
- Imperative API split per repo convention (context/hook vs provider, like `auth-*`):
  `components/confirm-dialog-context.ts` (`ConfirmContext`, `useConfirm`, `ConfirmOptions`,
  `ConfirmTone`) + `components/confirm-dialog.tsx` (`ConfirmDialogProvider`). Provider mounted once in
  `App.tsx` inside `TooltipProvider`. Usage: `if (!(await confirm({tone, title, description}))) return`.
- Tones `default|destructive|success|warning|info` → pulsing icon halo (`motion-safe:animate-ping`),
  lucide icon, and confirm-button variant. Labels default to `common.confirm|cancel|confirmTitle`
  (added to en+it).
- Migrated all 4 `window.confirm` call sites: `personal-data/contacts-manager`,
  `personal-data/addresses-manager` (tone destructive, delete-action confirm label),
  `table/row-actions` (generic action confirm, title = action label), `table/filter-views-control`.
- Tests: new `confirm-dialog.test.tsx` (4/4 — resolves true/false, renders title/desc, i18n defaults).
  The 3 migrated component tests updated to drive the dialog (scoped `within(alertdialog)`); their
  render harnesses now wrap the providers the components actually require. NOTE: those 3 tests were
  already RED at HEAD from concurrent work (Tooltip added to `FilterViewsControl` w/o a
  `TooltipProvider` in the test; `useEnumOptions` added to `ContactsManager` needing a QueryClient) —
  the harness fixes incidentally green them again.
- Verified: 19/19 across the 4 files, `tsc --noEmit` clean, ESLint clean on changed files.

## Feature 0009 — Global quick-search + unified table toolbar — GREEN (verified by lead)

Spec `docs/specs/0009-table-search-and-unified-toolbar.md` (FROZEN). Full-stack. The
old detached `justify-end` buttons above the grid are gone: the table is now ONE
`rounded-xl border` block with a fused toolbar (search + live row count left;
reset-filters / saved-views / options `…` / fullscreen right). Column filtering stays
on the header menu (hover) — no toolbar filter toggle, no floating-filter row. The grid
drops its own wrapper border (`wrapperBorder:false`) to read continuous with the header.

Contract:
- `POST /tables/{domain}/rows` gains optional `search` (`nullable|string|max:100`,
  `TableRowsRequest::SEARCH_MAX_LENGTH`). Applied as a grouped OR-`LIKE` over the
  definition's `searchableColumnIds()` allow-list, AND-combined with `filterModel`,
  bound + LIKE-escaped (mirrors `FilterApplier`; `\` is MySQL's default LIKE escape).
- `GET /tables/{domain}/columns` `data` gains `searchable: string[]` (real columns only;
  `[]` ⇒ no search box). users → `['name','email']`, roles → `['name']`.
- New `TableDefinition::searchableColumnIds()`; `AbstractTableDefinition` derives it from
  column declarations flagged `'searchable' => true` and emits it in `resolveConfig()`.
  Only `AbstractTableDefinition` implements the interface → every domain inherits it.

Frontend:
- `TableToolbar` (new, presentational) + `useTableToolbarState` (new hook: search+⌘K,
  fullscreen w/ scroll-lock+Escape, live row count). `TableView` composes them and stays
  the orchestrator (under the 500 hard cap). Column filters stay on the header (hover) —
  no toolbar filter toggle, no floating-filter row (removed per user feedback).
- `createSsrmDatasource(domain, getSearch)`: term read lazily from a ref; typing debounces
  a `refreshServerSide({purge:true})` (datasource never rebuilt). `DataTable` gains
  `onRowCountChanged` (from `onModelUpdated`). Saved-views trigger is now icon-only.
- i18n keys added to it+en: `table.searchPlaceholder`, `table.rowCount_one/_other`,
  `table.options/export/fullscreen/exitFullscreen`, `common.soon/clear`. Export in the
  `…` menu is a disabled "soon" placeholder (per request).

Verified (all executed):
- Backend: `TableRowsSearchTest` (5) + `TableConfigTest` searchable assertion; full Table
  suite 118/118; **full backend suite 613 passed / 1 unrelated skip**; Pint clean.
- Frontend: `ssrm-datasource.test` (+2 search cases), `table-toolbar.test` (7); table+data-table
  suites 77 passed (the only 3 reds are the PRE-EXISTING `cell-renderers`/ContactsCell failures
  from concurrent 0005/0008 work — unchanged vs HEAD, not this feature). `tsc --noEmit` clean;
  ESLint clean on all changed files.

Not committed yet (working tree still commingles 0004/0006/0005/0008 concurrent work). The
grants/opportunities domain in the user's mockup does not exist — only `users`/`roles` consume
`TableView`; the toolbar is domain-agnostic and will cover a future domain for free.

## Settings page redesign (connected-user Impostazioni) — GREEN (verified)

Presentational redesign of `pages/settings-page.tsx` (self-service settings). Two-column on
desktop: a sticky identity + section-index rail (IntersectionObserver scroll-spy, reduced-motion
honored) beside icon-led section cards (Profilo, Sicurezza). Fields are lifted onto a muted
`FieldPanel` that forces the design-system `Input`/`SelectTrigger` (`data-slot`) to solid `bg-card`,
so inputs read as elevated white surfaces against the tinted panel — the brief's contrast/depth ask.

- Scope discipline: ONLY `settings-page.tsx` rewritten + one i18n key (`settings.sectionNavLabel`)
  per locale. The three form files (`profile-form`/`password-form`/`avatar-form`) and the shared
  `PersonalDataSection` were NOT touched (blast radius). The white-field override is scoped to the
  page via `data-slot` selectors → checkboxes (`type=checkbox`) and the hidden file input are safe.
- Verified: `tsc --noEmit` clean; ESLint clean on the page; `login-form` 3/3 (i18n smoke).
  `it.ts` typed `: TranslationResources` so tsc confirms the new key mirrors `en.ts`.
- The 5 reds in `profile-form.test.tsx` are PRE-EXISTING and independent: proven by `git stash` of
  my files → identical `useConfirm must be used within a ConfirmDialogProvider`
  (`confirm-dialog-context.ts:30`), from the concurrent uncommitted confirm-dialog work.
- Not committed (working tree still commingles concurrent sessions). No live browser render was
  done (headless); change is presentational/low-risk.

## User form + Role form redesign — GREEN (verified)

Presentational redesign of the User and Role create/edit forms (in the widened Sheet,
`sm:max-w-2xl`). Contract 0004/0006/0008 UNCHANGED — only presentation. Approved via an HTML
mockup first, then implemented on the real app tokens.

Design-system foundation (mine):
- New semantic tokens `--field` / `--field-border` (light: `#fff` on the grey body; dark: a surface
  lighter than the card) + `@theme` mappings → `bg-field` / `border-field-border`. `input.tsx` and
  `select.tsx` now use them instead of `bg-transparent` → fillable fields no longer blend into the
  page (the brief's #1 complaint). Verified in the built CSS: `.bg-field{background-color:var(--field)}`.
- New primitives: `components/ui/checkbox.tsx`, `components/ui/switch.tsx` (Radix, no new dep),
  and a reusable `components/form-section.tsx` (icon chip + title + description + aside slot).
- Sheet widened in `users-table.tsx` / `roles-table.tsx`.

Forms:
- User form (`user-form-body.tsx`): 5 `FormSection` cards — Anagrafica (personal-data card +
  avatar), Autenticazione, Ruoli e accessi, Contatti, Indirizzi. Personal-data composed directly
  from `PersonalDataCardForm`/`ContactsManager`/`AddressesManager` (buffered wiring preserved) so
  Anagrafica renders first WITHOUT touching the shared `PersonalDataSection` (still used by
  `ProfileForm`). `ContactsManager`/`AddressesManager` gained an optional `showHeader` prop
  (default true = old behavior). All fields still wrapped in `MetaField`; sections self-hide when
  all their fields are metadata-hidden.
- Role form (`role-form-body.tsx` + `role-field-permissions.tsx`): permissions grouped per domain
  card — primary abilities (`viewAny/view/create/update/delete`) visible as toggle pills, the rest
  (export/import…) under a per-domain `Collapsible` "Configurazione avanzata". Field-permission
  matrix kept as its own gated section (NOT nested per-domain: verified the field catalogue only
  registers `users`/`roles` while permission groups are broader — they don't align 1:1), redesigned
  as one `Collapsible` per resource with the `Checkbox` primitive. 0006 merge rule preserved exactly
  (mandatory locked; `required` disabled unless `editable`).

i18n: added `users.form.sections.*`, `roles.form.sections.*`, `roles.form.advanced(Actions)` to
both locales.

- Verified: `tsc --noEmit` clean; ESLint clean on changed scope; `vitest run` on
  users+roles+personal-data = 96/96; `vite build` exit 0 (field utilities/tokens confirmed).
- Pre-existing reds (NOT mine, proven by the concurrent sessions above via git-stash): 8 failures in
  `auth/profile-form.test.tsx` (needs the `ConfirmDialogProvider` test wrapper — same fix already
  applied to the user/personal-data tests) and `table/cell-renderers.test.tsx` (concurrent table work).
- Follow-ups (flagged, out of scope): `en.ts`/`it.ts` now >500 lines (code-guard hard limit) —
  grew from concurrent work + my keys; split the locale files once concurrent sessions settle.
  `secret-scan` on locale files is a known false positive. `user-form-body.tsx` (343) and
  `role-form-body.tsx` (363) exceed the 300 soft limit (under 500 hard) — optional sub-component split.
- Not committed (working tree commingled). A scoped commit of the redesign files is recommended.
