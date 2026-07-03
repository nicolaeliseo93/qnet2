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
