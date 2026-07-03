# Feature Specification

> SDD spec. Owner: Architect → Backend + Frontend + Verifier.
> Frozen wire contract. Builds on feature 0004 (`docs/specs/0004-centralized-authorization-metadata.md`)
> and its convention `docs/conventions/metadata-driven-forms.md`.

---

## Feature Name

Per-role field-permission matrix — admin-configurable, DB-driven field visibility/editability,
selectable from the **Role** form. Scope: the `users` and `roles` resources.

## Status

APPROVED

---

## Summary

Today field permissions are computed only in code (the 0004 resolvers). This feature adds a
**data-driven layer**: an administrator selects, per role and per resource field, whether the field
is `visible`, `editable`, and `required` — stored in DB and edited from a new "Permessi campi"
section of the Role form. The 0004 resolver reads these overrides.

**Security invariant (frozen): the code resolver is the security CEILING; the DB matrix can only
RESTRICT within it, never escalate.** A DB row that tries to make editable a field the business
rules lock (e.g. the `roles` field on a super-admin user, or any field when the actor lacks
`{resource}.update`) has no effect — the ceiling wins. The write path (`EnforcesFieldPermissions`,
422) automatically honors the merged result, so a manipulated frontend still cannot bypass it.

---

## Merge semantics (FROZEN)

For a given `actor`, `resource`, `model`, `field`:

1. **Ceiling** = the existing 0004 concrete resolver output (renamed to `fieldPermissionCeiling()`).
2. **DB config for the actor** = union (most-permissive, additive like RBAC) across the actor's
   roles of their `role_field_permissions` rows for `(resource, field)`. **Absence of any row for a
   field = full (no restriction)** for that field. If ANY of the actor's roles grants a flag, it is
   granted.
3. **Final** = intersect(ceiling, db):
   - `visible  = ceiling.visible  AND db.visible`
   - `editable = ceiling.editable AND db.editable`
   - `required = ceiling.required OR (db.required AND final.editable)` (required only when editable)
   - `hidden/readonly/disabled` derived per 0004.
4. **Privileged bypass**: if the actor holds `RoleAssignmentGuard::PRIVILEGED_ROLE` (super-admin),
   skip the DB restriction entirely (full ceiling) — consistent with `Gate::before`.

The DB matrix therefore expresses "what a role is allowed to see/edit within the security ceiling".

---

## Data model

Migration — table `role_field_permissions`:

| Column     | Type                         | Notes |
|------------|------------------------------|-------|
| `id`       | id                           | |
| `role_id`  | FK → `roles.id` cascadeOnDelete | |
| `resource` | string                       | must be a registered authorization resource |
| `field`    | string                       | must exist in that resource's field catalogue |
| `visible`  | boolean, default true        | |
| `editable` | boolean, default true        | |
| `required` | boolean, default false       | |
| timestamps |                              | |

Unique `(role_id, resource, field)`. Index `(resource, field)`.

Model `App\Models\RoleFieldPermission` — `$fillable` = `[role_id, resource, field, visible, editable, required]`,
`$casts` bool for the three flags. `Role` gains `fieldPermissions(): HasMany`.

---

## Backend design

- **`app/Authorization/FieldPermissionRepository`** — `forRoleIds(array $roleIds): Collection`
  (one query, per-request memoized), returns the union config keyed by `resource.field`.
- **`AbstractResourceAuthorization`**:
  - The interface method `fieldPermissions(User, ?Model): array` becomes **final** in the abstract
    and delegates: `final = intersect(fieldPermissionCeiling($actor,$model), dbConfig($actor,$resource))`
    with the privileged bypass.
  - New protected abstract `fieldPermissionCeiling(User, ?Model): array` — the concrete classes'
    current `fieldPermissions()` body moves here **unchanged** (it is the ceiling).
  - Inject `FieldPermissionRepository` via constructor (concretes call `parent::__construct($repo, …)`).
- **`UsersAuthorization` / `RolesAuthorization`** — rename `fieldPermissions()` → `fieldPermissionCeiling()`;
  no rule change. Constructors pass the repo up.
- **Field-catalogue endpoint** — `GET /api/authorization/fields`:
  - Auth `auth:sanctum`, `throttle:60,1`. Authorization: `roles.create` OR `roles.update`
    (you manage roles). Otherwise 403.
  - Response: `{ success, message, data: { resources: [ { resource: 'users', fields: [ { key, type, group } ] }, { resource: 'roles', fields: [...] } ] } }`.
    Resources = those registered in `config/authorization.php` (currently users, roles).
  - New `Authorization\FieldCatalogueController@index`.
- **Role flow gains `field_permissions`** (mirrors the existing `users`/`permissions` sync):
  - `StoreRoleRequest`/`UpdateRoleRequest`: `field_permissions` → `['sometimes','array']`;
    `field_permissions.*.resource` in registered resources; `field_permissions.*.field` must exist in
    that resource's catalogue (custom rule using the registry); `.visible/.editable/.required` boolean.
    Absent key = leave untouched; `[]` = clear the role's matrix.
  - `CreateRoleData`/`UpdateRoleData`: add `?array $fieldPermissions` + `hasFieldPermissions()`.
  - `RoleService::create/update`: sync `role_field_permissions` INSIDE the existing `DB::transaction`,
    after permission/user sync. Full replace of the role's rows to match the submitted set.
  - `RoleResource`: add `field_permissions` = flat list `[ { resource, field, visible, editable, required } ]`
    for the role (from the relation).

No change to the 0004 wire contract for `permissions` — this is additive.

---

## Frontend design

- `features/roles/`:
  - `types.ts`: `RoleFieldPermission { resource, field, visible, editable, required }`;
    `RoleDetail` gains `field_permissions: RoleFieldPermission[]`; payloads gain optional `field_permissions`.
  - `field-catalogue-api.ts` + `use-field-catalogue.ts`: `GET /authorization/fields` via React Query
    (`staleTime` 5 min).
  - `role-field-permissions.tsx`: a new form section rendering, per resource, a compact matrix of its
    fields × three toggles (visible / editable / required). REUSE the existing permission-matrix
    checkbox pattern (`permission-groups.ts` styling) — no new `components/ui` primitive. Labels via
    i18n; field labels reuse the existing `permissions`/humanize fallback approach.
  - Wire into `role-form-body.tsx` + `use-role-form.ts`: seed from `role.field_permissions` (edit) or
    empty (create); include `field_permissions` in the submit payload (diff/replace). The section is
    itself gated by the metadata (only shown when the role form's own `permissions`/actions allow it —
    reuse 0004 `MetaField`/`canAction` where applicable).
  - Zod: extend the role schema with `field_permissions` (array of the shape) as a UX mirror.
- i18n: add `roles.fieldPermissions.*` (section title, column headers visible/editable/required,
  per-resource headers) in `en.ts`/`it.ts`.

The Role form stays fully metadata-driven per the 0004 convention; this section is authored the same way.

---

## Acceptance criteria (→ tests, run for real)

### Backend (Pest)

1. Migration + model: a role can have `role_field_permissions`; unique `(role_id,resource,field)` enforced.
2. `GET /api/authorization/fields`: 401 without auth; 403 without `roles.create`/`roles.update`;
   200 with the catalogue for `users` and `roles` (keys match `fields()` of each resolver).
3. Create/Update role with `field_permissions` persists the rows; `[]` clears; omitted key leaves untouched.
4. Validation: unknown `resource` or a `field` not in that resource's catalogue → 422; non-boolean flag → 422.
5. **Merge — restriction works**: a role with `users.email {visible:false}` → a user holding only that
   role gets `permissions.fields.email.visible = false` on `GET /api/users/{user}` (and `hidden = true`).
6. **Merge — no escalation**: a role config setting `users.roles {editable:true}` on a super-admin
   target user is ignored — `fields.roles.editable` stays `false` (ceiling wins). Likewise a field
   marked editable in DB is NOT editable when the actor lacks `users.update`.
7. **Union across roles**: a user with role A (email hidden) + role B (email visible) → email visible.
8. **Privileged bypass**: a super-admin actor is unaffected by any restrictive DB config (full ceiling).
9. **Write path honors the matrix**: with `users.locale {editable:false}` for the actor's role, a
   `PATCH /api/users/{user}` submitting `locale` → 422 `"field not editable"`, no write (reuses
   `EnforcesFieldPermissions`, no new code path).
10. 0004 suite stays green (absence of DB config = identical behavior to today).

Coverage ≥ 90% on new backend code.

### Frontend (Vitest + RTL)

11. `GET /authorization/fields` renders the matrix: each resource's fields as rows, three toggles each.
12. Toggling a cell updates form state; submit includes the `field_permissions` array.
13. Edit mode seeds the matrix from `role.field_permissions`; unchanged submit round-trips the same set.
14. A server 422 on a `field_permissions.*` key surfaces inline (via `applyServerValidationErrors`).
15. The section is hidden/read-only when the role form metadata says so (0004 gating still applies).

`tsc --noEmit`, ESLint, Pint clean.

---

## Out of scope (kills drift)

- Resources other than `users` and `roles` (engine is generic; only these two are wired/tested now).
- Per-USER (not per-role) field overrides.
- Contextual state/site/ownership rules (still the 0004 no-op hooks).
- A new `Switch`/`Checkbox` `components/ui` primitive (reuse the existing checkbox matrix pattern).
- Changing the 0004 `permissions` envelope shape (this feature is purely additive).
