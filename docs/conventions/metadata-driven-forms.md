# Convention — Metadata-driven forms (MANDATORY for every module)

> Status: ACTIVE · Applies to: every form and resource-bound view in the app, present and future.
> Origin: spec `docs/specs/0004-centralized-authorization-metadata.md`.
> Rule of thumb: **the backend is the single source of truth for authorization; the frontend
> renders itself from the metadata and contains no hardcoded permission logic.**

Every new module (bookings, orders, products, …) MUST follow this pattern. A form that hardcodes
which fields/actions are visible/editable, or that enforces permissions only client-side, is a
defect — not a style choice.

---

## The rule

For **every** resource that has a form or an authorization-bearing view:

1. **Backend declares a `ResourceAuthorization`** in `app/Authorization/` and registers it in
   `config/authorization.php` (`'<resource>' => XxxAuthorization::class`). Adding a resource =
   one class + one line. No ad-hoc per-form permission code.
2. The resolver computes, per **actor + resource instance + business rules**:
   - `resource` — `view, create, update, delete, export, import` (from the `{resource}.{ability}`
     Policy convention via `BasePolicy`);
   - `fields` — for every field the six flags `visible, hidden, editable, readonly, required, disabled`;
   - `actions` — availability of every domain action (`approve, reject, cancel, refund, duplicate,
     change_status, export_pdf, send_email, assign_responsible, add_note, upload_document, …`).
3. **The backend returns the `permissions` block alongside the resource** (top-level sibling of
   `data`), and exposes create-context metadata at `GET /api/meta/{resource}`.
4. **The same resolver guards every write.** Non-editable submitted fields → **422**; unavailable
   actions → **403**. A manipulated frontend cannot bypass this. Reuse existing domain guards
   (e.g. `RoleAssignmentGuard`) — never duplicate a security control.
5. **The frontend builds the form from the metadata**: `MetaField` per field (hides hidden,
   disables/reads-only non-editable, marks required), action buttons gated by `actions`. Zod stays a
   **UX mirror** only — it never replaces server validation.

Result: authorization logic changes live in the backend resolver; the frontend adapts automatically,
with no per-form edits.

---

## Backend checklist (per resource)

- [ ] `XxxAuthorization implements ResourceAuthorization` (extends `AbstractResourceAuthorization`).
- [ ] Registered in `config/authorization.php`.
- [ ] `fields()` catalogue (key + form `type` + optional `group`).
- [ ] `actions()` catalogue; `actionPermissions()` gates each (contextual where needed).
- [ ] `fieldPermissions()` overrides only the fields with non-default rules; reuse existing guards.
- [ ] `show` / `store` / `update` return `permissions` via `okWithPermissions(...)`.
- [ ] Write requests use the `EnforcesFieldPermissions` concern (422 on non-editable fields).
- [ ] Action endpoints authorize via the resolver (403 when unavailable).
- [ ] Pest: resource/field/action shape, contextual rules, write-path enforcement (≥90% on the resolver).

## Frontend checklist (per form)

- [ ] Edit-mode consumes `detail.permissions`; create-mode uses `useResourceMeta('<resource>')`.
- [ ] Every field wrapped in `MetaField` (no field rendered outside it).
- [ ] Action buttons/menus gated by `canAction(...)`.
- [ ] No hardcoded `if (role === …)` / `if (permission)` in JSX — read metadata only.
- [ ] Zod schema present as UX mirror; server 422 mapped inline via `applyServerValidationErrors`.
- [ ] Graceful fallback when a field is missing from metadata (visible+editable).
- [ ] Vitest: hidden field absent, readonly not editable, required label, action gated, 422 inline.

---

## Reference implementation

`UsersAuthorization` / `RolesAuthorization` (backend) and `user-form.tsx` / `role-form.tsx`
(frontend). Copy their shape for new modules. The contextual hooks (resource state, ownership,
location/site) are already present in `AbstractResourceAuthorization` — wire them when the module's
data supports them (e.g. a booking's `paid`/`cancelled` state, a refund day-window, an operator's
site scope).
