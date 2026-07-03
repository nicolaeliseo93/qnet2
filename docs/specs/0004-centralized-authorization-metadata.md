# Feature Specification

> SDD spec. Owner: Architect → Backend + Frontend + Verifier.
> Frozen wire contract — backend and frontend implement against this in parallel.
> Companion convention: `docs/conventions/metadata-driven-forms.md` (mandatory for every future form).

---

## Feature Name

Centralized backend-driven authorization metadata (resource + field + action + contextual)
— first application: the **User** and **Role** forms.

## Status

APPROVED

---

## Summary

The backend becomes the single source of truth for authorization. Together with every
resource it returns a `permissions` metadata block describing what the current actor may
do with that resource: standard CRUD (+ export/import), per-field capabilities
(visible/hidden/editable/readonly/required/disabled), and per-action availability. The
metadata is contextual (computed against actor + resource instance + business rules). The
frontend renders itself from this metadata and contains **no hardcoded permission logic**.
The **same resolver** that produces the metadata also guards every write, so a manipulated
frontend cannot bypass it.

This slice delivers the reusable engine plus its first two consumers (User form, Role form).
Extensible contextual hooks (resource state, location/site, ownership, day-limits) are built
but only the rules that genuinely exist for users/roles are wired now; state/site/ownership
hooks are present and no-op here, ready for future modules (bookings, orders, …).

---

## Wire contract (FROZEN)

### The `permissions` block

Attached at the **top level** of the envelope, sibling of `data`:

```jsonc
{
  "success": true,
  "message": "OK",
  "data": { /* UserResource / RoleResource, unchanged */ },
  "permissions": {
    "resource": {
      "view": true, "create": false, "update": true,
      "delete": false, "export": true, "import": false
    },
    "fields": {
      "email":  { "visible": true, "hidden": false, "editable": true,  "readonly": false, "required": true,  "disabled": false },
      "locale": { "visible": true, "hidden": false, "editable": true,  "readonly": false, "required": true,  "disabled": false },
      "roles":  { "visible": true, "hidden": false, "editable": false, "readonly": true,  "required": false, "disabled": false },
      "password": { "visible": true, "hidden": false, "editable": true, "readonly": false, "required": false, "disabled": false }
    },
    "actions": {
      "delete": false, "export": true, "import": false,
      "upload_avatar": true, "delete_avatar": true
    }
  }
}
```

### Field permission descriptor — all six flags always emitted

The backend computes every flag; the frontend never derives authorization. Server-side
derivation rules (in `AbstractResourceAuthorization`):

| Flag       | Meaning                                             | Derivation |
|------------|-----------------------------------------------------|------------|
| `visible`  | field is rendered at all                            | primary    |
| `hidden`   | field is not rendered                               | `= !visible` |
| `editable` | value may be changed and submitted                  | primary    |
| `readonly` | shown but not changeable (contextual lock)          | `= visible && !editable && !disabled` |
| `required` | must be provided (only meaningful when `editable`)  | primary    |
| `disabled` | control hard-disabled (stronger than readonly)      | primary    |

`FieldPermission` value-object factories: `visibleEditable(required)`, `visibleReadonly()`,
`hidden()`, `disabled()`.

### Resource abilities

Fixed set, each mapped to the spatie permission `"{resource}.{ability}"` via the existing
`BasePolicy` convention: `view, create, update, delete, export, import`.
`BasePolicy::abilities()` is extended to include `export` and `import` so
`permissions:sync` generates `users.export`, `users.import`, `roles.export`, `roles.import`, …
(policies gain `export()` / `import()` → `$user->can("{resource}.{ability}")`).

### Endpoints

1. **`GET /api/meta/{resource}`** — NEW, generic, registry-driven (mirrors `tables/{domain}`).
   - Auth: `auth:sanctum`, `throttle:60,1`.
   - Authorization: `{resource}.viewAny` (via the resolved model Policy). Unknown resource → 404.
   - Response (create-context, `model = null`):
     ```jsonc
     {
       "success": true, "message": "OK",
       "data": { "fields": [ { "key": "email", "type": "email", "group": null }, … ] },
       "permissions": { "resource": {…}, "fields": {…}, "actions": {…} }
     }
     ```
   - `data.fields` = the static field catalogue (key + form `type` hint + optional `group`)
     so the frontend can build the create form skeleton; `permissions` = create-context.
2. **`GET /api/users/{user}` / `GET /api/roles/{role}` (show)** — now also return `permissions`
   (instance-contextual, edit mode). `data` unchanged.
3. **`store` / `update`** — responses also carry `permissions` (post-write re-sync). `data` unchanged.

Envelope helper: `BaseApiController::okWithPermissions($data, array $permissions, $message, $status)`
adds `permissions` alongside `data`. Existing `ok()`/`created()` unchanged.

---

## Backend design

New namespace `app/Authorization/`:

- **`FieldPermission`** — VO, six flags + named constructors + `toArray()`.
- **`FieldDefinition`** — VO: `key`, `type` (`text|email|select|multiselect|password|…`), `?group`.
- **`ResourceAuthorization`** (interface):
  - `resource(): string`
  - `fields(): FieldDefinition[]`
  - `actions(): string[]`
  - `resourcePermissions(User $actor, ?Model $model): array<string,bool>`
  - `fieldPermissions(User $actor, ?Model $model): array<string,FieldPermission>`
  - `actionPermissions(User $actor, ?Model $model): array<string,bool>`
- **`AbstractResourceAuthorization`**:
  - `resourcePermissions()` default: for each of the 6 abilities → `$actor->can("{resource}.{ability}")`.
  - `fieldPermissions()` default: every field `visibleEditable` when the actor may write
    (`create` when `$model===null`, else `update`), else `visibleReadonly`. Concrete classes override per field.
  - `actionPermissions()` default: each action gated by a mapped permission; concrete overrides for contextual rules.
  - Contextual hooks present but no-op by default: `appliesResourceState()`, `appliesOwnership()`, `appliesLocation()`.
- **`AuthorizationRegistry`** + `config/authorization.php`
  (`'definitions' => ['users' => UsersAuthorization::class, 'roles' => RolesAuthorization::class]`).
  `resolve($resource)` → unknown → `ModelNotFoundException` (404), mirroring `TableRegistry`.
- **`ResourcePermissionsBuilder`** — `build(ResourceAuthorization, User, ?Model): array` → the
  `{ resource, fields, actions }` array (serializes `FieldPermission` VOs).
- **`Meta\MetaController@show(Request, string $resource)`** — resolve, authorize `viewAny`, build create-context block. One controller for all resources.
- **`EnforcesFieldPermissions`** (FormRequest concern) — in `withValidator`: resolve the
  resource's `ResourceAuthorization`, compute `fieldPermissions($actor, $model)`, and for every
  **submitted** key that maps to a **non-editable** field add a validator error
  → **422** (`{field}` → `"field not editable"`). Strict reject (decision: no silent drop).
  This makes the resolver the single source of truth for metadata AND writes.

### `UsersAuthorization`

- Fields: `email` (email), `locale` (select), `roles` (multiselect), `password` (password).
  (`personal_data` is a nested resource with its own authorization — out of scope here, noted.)
- Field rules (reuse `RoleAssignmentGuard` / `UserService::PRIVILEGED_ROLE`, no duplication):
  - `email`, `locale`: editable when actor may write; `required` on create, `required` on update.
  - `password`: editable when actor may write; `required` on create, **not** required on update.
  - `roles`: editable only when actor may write **and** may assign roles; when the target `$model`
    is a super-admin and the actor is not super-admin → `visibleReadonly`.
- Actions: `delete` (`users.delete` and not self — mirrors `UserPolicy::delete`), `upload_avatar` /
  `delete_avatar` (`users.update`), `export` (`users.export`), `import` (`users.import`).

### `RolesAuthorization`

- Fields: `name` (text), `permissions` (multiselect), `users` (multiselect).
- Field rules:
  - When `$model` is the `super-admin` role: `name` and `permissions` → `visibleReadonly` for
    everyone (mirrors `RoleService::guardSystemRole`); `users` editable only by a super-admin actor.
  - Otherwise: editable when actor may write (`roles.create`/`roles.update`).
- Actions: `delete` (`roles.delete` and not the super-admin role — mirrors `RolesTableDefinition`),
  `export` (`roles.export`), `import` (`roles.import`).

Existing FormRequest value-level guards (`assignableRoleIdRule`, `users.*` exists, super-admin
service guards) remain and compose with the new field-level gate.

---

## Frontend design

New `features/authorization/`:

- `types.ts` — `FieldPermission`, `ResourceAbility`, `ResourcePermissions { resource, fields, actions }`,
  `FieldDescriptor { key, type, group }`, `ResourceMeta { fields, permissions }`.
- `api.ts` — `fetchResourceMeta(resource): Promise<ResourceMeta>` → `GET /api/meta/{resource}`.
- `query-keys.ts` — `metaKeys.resource(r)`.
- `use-resource-meta.ts` — `useQuery` (create-context), `staleTime` 5 min.
- `permissions.tsx` — a small provider/hook `useResourcePermissions()` exposing
  `field(name): FieldPermission`, `canAction(name): boolean`, `canResource(ability): boolean`;
  graceful fallback (visible+editable) when a field is absent from metadata.
- `MetaField.tsx` — wraps the existing `FormField`: if `!visible` render nothing; pass
  `disabled`/`readOnly`; forward `required` to `FormLabel`. UI-only.
- `meta-action.ts` / helper — hide or disable a button/menu item when `!actions[key]`.

Refactor:
- `features/users/api.ts` + `types.ts`: `fetchUser` returns `UserDetail & { permissions: ResourcePermissions }`
  (reads `response.permissions`). Same for `features/roles/api.ts`.
- `user-form.tsx` / `role-form.tsx`: edit-mode consumes `detail.permissions`; create-mode uses
  `useResourceMeta('users'|'roles')`. Each field wrapped in `MetaField`; action buttons gated via
  `canAction`. Zod schemas unchanged (UX mirror). `applyServerValidationErrors` already surfaces the
  new 422 field errors.
- i18n: add `authorization.fieldNotEditable` etc. under existing `en.ts` / `it.ts`.

---

## Acceptance criteria (→ tests, run for real)

### Backend (Pest)

**MetaEndpointTest**
1. 401 without auth; 404 unknown resource; 403 without `{resource}.viewAny`.
2. `GET /api/meta/users` with `users.viewAny` → 200, `data.fields` catalogue + full `permissions` block (create-context: `resource.create` reflects the actor).
3. `permissions.resource` reflects the actor's abilities incl. `export`/`import`.

**ResourcePermissionsShapeTest**
4. `GET /api/users/{user}` → `permissions.fields` emits all six flags per field; `hidden = !visible`, `readonly` derivation holds.
5. Non-super-admin actor editing a super-admin user → `fields.roles.editable = false`, `readonly = true`.
6. `GET /api/roles/{superAdminRole}` for non-super-admin → `fields.name.editable = false`, `fields.permissions.editable = false`; super-admin actor → editable true.
7. `actions.delete` false when acting on self (users) / on the super-admin role.

**FieldPermissionEnforcementTest** (write path)
8. Update a user submitting a field that is non-editable for the actor → **422**, error keyed on the field, **no write**.
9. Update submitting only editable fields → 200/persisted.
10. Editable-but-value-guarded field (e.g. `roles` with a non-assignable role id) → existing 422 still applies (composition, not regression).

Coverage ≥ 90% on new `app/Authorization/` code; existing user/role feature tests stay green.

### Frontend (Vitest + RTL)

11. Create form fetches meta and renders only visible fields; a `hidden` field is absent from the DOM.
12. A `readonly`/non-`editable` field renders disabled/read-only and cannot be edited.
13. `required` from metadata drives the `*` on the label.
14. An action button whose `actions[key] === false` is not shown (or disabled).
15. Edit form seeds permissions from the loaded detail; a server 422 `"field not editable"` surfaces inline.
16. Fallback: when metadata is missing a field, it renders visible+editable (no crash).

`tsc --noEmit` clean; Pint/ESLint clean.

---

## Out of scope (kills drift)

- `personal_data` nested-card field permissions (own resource; future — engine already supports it).
- Location/site (`sede`) and ownership rules and any schema change for them (hooks present, no-op now).
- Resource-state machines (paid/cancelled/refund-window) — hooks present, applied in future domains.
- Generating the Zod schema from metadata (Zod stays a static UX mirror).
- New generic UI components beyond `MetaField` (users/roles need none).
- Table/SSRM authorization (already handled by `TableDefinition`; unchanged).
