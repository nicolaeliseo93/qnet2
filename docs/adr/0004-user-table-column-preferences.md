# Architecture Decision Record

> ADR generato dal template `templates/architecture-decision.md`.
> Owner: Architect Agent.

---

## ADR ID

0004

## Title

Per-user table column preferences (domain-agnostic persistence over the generic Table Registry)

## Status

ACCEPTED

> Open questions from the first draft are now resolved (see *Decision* and
> *Resolved Decisions*): no activity log, no dedicated Policy, server-side sparse
> diff, `POST` upsert + explicit-only reset. Product scope confirmed by the
> companion feature spec `docs/specs/0001-user-table-column-preferences.md`.
> Security validation remains a downstream implementation gate (see *Affected
> Agents*), as for ADR-0002.

> Extends — does not supersede — ADR-0002 (Generic Domain-driven Table Registry).
> The generic `TableDefinition` / `TableRegistry` / `TableService` and the
> `GET /api/tables/{domain}/columns` contract stay the source of truth for the
> **default** table schema. This ADR adds a per-user **override** layer on top of
> that schema.

## Date

2026-06-12

---

## Context

ADR-0002 made every table backend-driven: `GET /api/tables/{domain}/columns`
returns the **default** column schema for a `{domain}`, resolved by a
`TableDefinition` and filtered by the caller's permissions. Today that schema is
the same for every user — there is no way for a user to persist their own column
layout.

The product now wants each user to personalize, **per table**, and have it
restored on every subsequent visit:

- column **order** (drag & drop),
- column **width** (resize),
- column **visibility** (show / hide),
- room for future view-preferences (sort, saved filters, presets).

Non-negotiable constraints (from the proposal + existing standards):

- **Domain-agnostic.** One persistence mechanism for *every* table (users,
  products, companies, tasks, orders, …). No per-domain code to store
  preferences (`decision-making.md` → Reuse Before Build; `coding-standards.md`
  → Avoid Duplication).
- **PHP definition stays the single source of truth.** The `TableDefinition`
  remains the official, authoritative default. User preferences are a sparse
  **delta** over it, never a full copy — adding/removing a column in PHP must not
  require a data migration of saved preferences.
- **Forward/backward tolerant merge.** A column in PHP but not in the saved delta
  → use the default. A column in both → user value wins. A column in the delta
  but no longer in PHP → silently ignored. (Exact rules in *Decision §3*.)
- **Server-side authorization mandatory** (`security-standards.md` → Authorization
  First). A user can only ever read/write **their own** preferences, and only for
  a table they are allowed to see (`viewAny`).
- **No data leakage / no new attack surface beyond what is justified.** The write
  path accepts user input and must be whitelisted against the definition exactly
  as the SSRM rows endpoint is.
- **Reuse the existing framework.** No new pair of endpoints per domain; this
  plugs into the registry and the existing config-resolution path.

Gap in the proposal that this ADR must close: the proposal only describes
*extending the read endpoint* with a merge. Persistence also needs a **write
path** (how a preference is saved) and a **storage model**, neither of which is
specified. Both are proposed here and flagged for sign-off (see *Risks → Open
Questions*).

---

## Decision

Add a **single, domain-agnostic preference store** keyed by `(user, domain)`,
read-merged into the existing config endpoint and written through one new
endpoint. No per-domain class, table, or route is added — adding a domain keeps
costing exactly "one `TableDefinition` + one config line" (ADR-0002 invariant
preserved).

### 1. Storage — one generic table

```
user_table_preferences
├── id                (pk)
├── user_id           (fk → users.id, cascade on delete, indexed)
├── domain            (string, the registry key: "users", "products", …)
├── preferences       (json — sparse delta, see §3)
├── created_at
└── updated_at
└── UNIQUE (user_id, domain)
```

- One row per user per table. The `UNIQUE(user_id, domain)` makes the write an
  idempotent **upsert**.
- `domain` is the same registry key validated by `TableRegistry::resolve()`; an
  unknown domain never reaches storage (resolved → `404` first).
- `preferences` holds **only deviations from the default**, shaped by column id
  (the proposal's JSON), e.g.:

```json
{
  "name":  { "width": 400, "order": 2 },
  "email": { "visible": false, "order": 1 }
}
```

`Model`: `UserTablePreference extends BaseModel`,
`$fillable = ['user_id', 'domain', 'preferences']`, `preferences` cast to
`array`. It carries no sensitive fields. **No activity log** (decided): column
preferences are high-churn, per-user UI state with no audit value — logging every
resize/reorder would be pure noise. This is an explicit, documented exemption
from the mandatory `LogsModelActivity` rule, of the same spirit as the
reference-data exemption in `architecture.md`. The model therefore does **not**
use `LogsModelActivity`.

### 2. Authorization — ownership by construction, gated by the table's `viewAny`

There is **no cross-user access path**: every read and write is scoped to
`auth()->id()` server-side. The user never supplies a `user_id`. The only gate
needed on top is *"can this user see this table at all?"* — reuse the definition's
existing `authorizeViewAny($user)` (e.g. `users.viewAny`). You can only
personalize a table you are allowed to view. This satisfies Least Privilege with
zero new permission surface. **No dedicated `UserTablePreferencePolicy`**
(decided): the resource is implicitly self-scoped (the user never supplies a
`user_id`) and the only meaningful gate — "can the user see this table" — is
already enforced by the definition's `viewAny`. A CRUD Policy would add ceremony
without a real authorization boundary. Documented exception to the per-model
Policy rule.

### 3. Read path — merge into the existing config endpoint

`GET /api/tables/{domain}/columns` keeps its contract and gains a merge step.
The resolution becomes:

```
default schema (TableDefinition, permission-filtered)   ← ADR-0002, unchanged
        ⊕  user delta (user_table_preferences for auth user + domain)
        =  resolved schema returned to the frontend
```

Merge rules (applied per column, by `id`):

| Case | Result |
|---|---|
| column in definition, **absent** from delta | default value used |
| column in definition **and** delta | delta value **overrides** the default property (`visible`, `width`, `order`) |
| column **in delta but not** in definition | entry **ignored** (column renamed/removed in PHP — no error, no stale data) |
| property in delta not recognized (not in the allowed key set) | ignored |

Only **presentation** properties are user-overridable: `visible`, `width`,
`order`. Security-relevant properties — `sortable`, `filterable`, `type`,
`label`, `options` — are **never** taken from the delta; they always come from the
definition. This keeps the SSRM whitelist (ADR-0002) untouched: a user cannot
make a non-filterable column filterable by editing their preferences.

The merge lives in **one place** — a `TablePreferenceService` (or a method on the
generic resolution path) consumed by the existing config resolution — so it is
identical for every domain and centrally testable, consistent with how
`TableService` centralizes the SSRM engine.

**Contract delta (implemented):** `columns[]` in `docs/api/0002-generic-tables.md`
gains two presentation fields — `order` (integer, stable sort key) and `width`
(integer px, nullable). Additive and default-valued by the definition, so
existing consumers keep working. The preferences endpoints are specified in the
companion `docs/api/0003-table-preferences.md`.

### 4. Write path — one new endpoint, self-scoped upsert

```
POST   /api/tables/{domain}/preferences   → upsert current user's layout for {domain}
DELETE /api/tables/{domain}/preferences   → reset to default (explicit user action only)
```

- Behind the same `auth:sanctum` + `throttle` group; resolves the definition via
  the registry (unknown domain → `404`); calls `authorizeViewAny` (→ `403`).
- `POST` chosen (over `PUT`) to match the product's mental model — *"every column
  move rewrites what's already saved"* — a single write that overwrites this
  user's stored layout for this table. Idempotent upsert on `(user_id, domain)`.
- Body: the current column state. Validated by a generic `TablePreferencesRequest`
  against **the resolved definition's** column ids and the **allowed property
  whitelist** (`visible: bool`, `width: int>0`, `order: int>=0`). Unknown column id
  or out-of-whitelist property → `422`, never persisted. Same "trust nothing /
  validate against the definition" discipline as `TableRowsRequest`.
- **Sparse computation is server-side** (decided, §Resolved): the frontend sends
  the current column state; the service diffs it against the definition default
  and persists **only deviations**. Keeps "store only modified properties"
  enforced by the single source of truth (PHP), instead of trusting a client-side
  diff that goes stale when a PHP default changes.
- **Preferences are never auto-deleted.** They persist and are overwritten on each
  save. The only way to clear them is the **explicit** `DELETE …/preferences`
  ("reset to default" / "set columns to default"), triggered by a deliberate user
  action — never automatically. After reset, the next config load returns the pure
  PHP default.

### 5. Frontend — AG Grid state → debounced persistence

- On load, the grid builds its `ColDef`s from the **already-merged** config
  (saved layout is restored transparently; no extra round-trip, no flash of
  default layout beyond the existing skeleton — `coding-standards.md` → Loading
  States).
- AG Grid column events (`columnMoved`, `columnResized`, `columnVisible`) are
  **debounced** and pushed through a dedicated mutation
  (`features/table/api.ts` → `updateTablePreferences(domain, state)`), via the
  API layer (never an HTTP call inside a UI component). TanStack Query mutation;
  optimistic local state is already in the grid, so no refetch is needed on
  success.
- This wiring lives in the **generic** `features/table/*` (ADR-0002), so every
  domain inherits persistence for free; no per-domain renderer change.

### 6. Out of scope (future, listed by the proposal)

Default-sort override, saved filters, multiple named presets, role/shared
configs, import/export of layouts. The schema (`preferences` JSON + the merge
service) is intentionally shaped to absorb these later without a migration.

**In MVP v1** (per `docs/specs/0001-user-table-column-preferences.md`): persist
order / width / visibility (auto-save on change) **and** the explicit "reset to
default" action (`DELETE …/preferences`). Reset is in scope because it is the
near-zero-cost escape hatch the persistence model otherwise lacks (a user who
breaks their layout must be able to recover) and matches the product's stated
"set columns to default" expectation.

---

## Alternatives Considered

- **Store the full resolved schema per user (not a sparse delta)** — rejected:
  breaks "PHP is the single source of truth"; adding/removing/renaming a column in
  PHP would require migrating every user's stored copy; stale columns would
  resurface. The sparse delta + merge is the whole point.
- **A preferences column on each domain table / a per-domain preferences table**
  — rejected: not agnostic, reintroduces per-domain work, violates Reuse Before
  Build. One generic `user_table_preferences` serves all domains.
- **A generic key/value `user_settings` table** (store table prefs as one of many
  setting kinds) — rejected for now: wider blast radius and looser schema than the
  feature needs; `user_table_preferences` is the minimal, well-typed store. Can be
  folded into a broader settings store later if one emerges (not premature here).
- **Client-side sparse diff (proposal's JSON shape computed in the browser)** —
  not rejected outright but **superseded** by server-side diffing: the default
  lives in PHP, so the deviation must be computed against PHP to stay correct when
  defaults change. Frontend sends state; backend owns "what counts as a deviation".
- **Overriding any column property via the delta** — rejected: would let a user
  flip `sortable`/`filterable`/`type` and widen the SSRM whitelist. The override
  set is deliberately limited to `visible`/`width`/`order`.
- **A second pair of per-domain preference endpoints** — rejected: defeats
  ADR-0002's "one pair of endpoints for every domain". Preferences ride the same
  `{domain}` registry resolution.

---

## Trade-offs

- **Advantages**
  - Fully domain-agnostic: one table, one model, one merge service, one write
    endpoint serve every current and future table. Adding a domain costs nothing
    extra for preferences.
  - PHP definition stays authoritative; columns can be added/removed/renamed with
    no preference migration.
  - Zero new authorization surface: self-scoped by construction, gated by the
    existing `viewAny`. The SSRM security whitelist is untouched.
  - Centralized merge → single place to audit and test, like the SSRM engine.
- **Disadvantages**
  - One extra read step (load + merge the delta) on the config endpoint — cheap
    (single indexed row by `(user_id, domain)`), but non-zero.
  - The write endpoint is a new input surface; must carry the same whitelist
    discipline as the rows endpoint (mitigated by reusing the pattern).
- **What we give up**
  - Per-user override of structural/security column properties — intentional;
    only presentation is user-controlled.

---

## Consequences

- **Positive:** every table in the app becomes personalizable with no per-domain
  work; the contract grows by two additive column fields and one self-scoped
  endpoint; the design pre-absorbs the future preference types (sort, filters,
  presets) without schema churn.
- **Negative:** a migration (new table) and a small, security-sensitive write
  endpoint to implement and test; the config endpoint gains a merge step that must
  be covered so it can never leak or mis-merge for any domain.
- **Tracked tech debt:** the merge service and `TablePreferencesRequest` join
  `AbstractTableDefinition` / `TableService` as shared core — their tests must
  assert the merge rules (drop-unknown-column, override-only-whitelisted-props,
  never-override-sortable/filterable) so the shared path can't silently regress
  across all domains.

---

## Affected Agents

- **Architect Agent** (owner of this ADR; owns the contract delta to
  `docs/api/0002-generic-tables.md` + a companion `docs/api/0003-table-preferences.md`).
- **Backend Agent**: migration `user_table_preferences`; `UserTablePreference`
  model; `TablePreferenceService` (merge + sparse-diff); extend the config
  resolution to merge; `TableController::preferences` (PUT/DELETE) +
  `TablePreferencesRequest`; register routes. Reuse `TableRegistry`, envelope,
  `authorizeViewAny`.
- **Frontend Agent**: in generic `features/table/*`, build `ColDef`s from merged
  config; debounced `updateTablePreferences` mutation wired to AG Grid column
  events through the API layer.
- **Security Agent**: validate (a) self-scoping (no `user_id` from client; no
  cross-user read/write), (b) write whitelist against the definition, (c) the
  override set cannot widen the SSRM sort/filter whitelist, (d) the activity-log
  exemption decision.
- **Reviewer / QA**: merge-rule regression (the 4 cases in §3), upsert
  idempotency, `403` without `viewAny`, `422` on unknown column/property, `404`
  on unknown domain, layout restored across sessions.
- **DevOps**: migration review.
- **Product Manager**: owns the open product decisions in *Risks → Open Questions*.

---

## Risks

- **Merge mis-application leaks structure:** if the merge ever let a delta
  override `sortable`/`filterable`, a user could widen the SSRM whitelist.
  Mitigation: hard-coded override whitelist (`visible`/`width`/`order`); test
  asserting structural props always come from the definition.
- **Cross-user access via spoofed `user_id`:** mitigated by construction — the
  endpoint never reads `user_id` from the request; always `auth()->id()`.
- **Unknown/stale columns:** renamed/removed PHP columns must drop silently on
  read and `422` on write. Mitigation: merge ignores unknown ids; request
  validates ids against the live definition.
- **Write-endpoint abuse / chatty saves:** debounced client + `throttle` on the
  route; idempotent upsert bounds row growth to one per `(user, domain)`.
### Resolved Decisions

1. **Activity log — NO.** `UserTablePreference` does **not** use
   `LogsModelActivity`. High-churn UI state, no audit value; explicit documented
   exemption from the mandatory rule (user-decided). Security to confirm this
   produces no audit gap.
2. **Dedicated Policy — NO.** Self-scoped by construction + `viewAny` gate; no
   `UserTablePreferencePolicy` (user-decided: "if the user is on the table, they
   are allowed to see it"). Security to confirm no cross-user path exists.
3. **Sparse diff — SERVER-SIDE** (Architect technical authority). Frontend sends
   current column state; backend diffs vs PHP default and persists only
   deviations.
4. **Reset to default — IN MVP v1** (Product Manager, see feature spec). Explicit
   user action only (`DELETE …/preferences`); preferences are never auto-cleared.
   Verb for save is `POST` (matches the "every move rewrites" model).
5. **`width` bounds** (Backend/Frontend detail): px, clamp to a sane range
   (e.g. `min 50`, `max 1000`) to reject absurd values; enforced in
   `TablePreferencesRequest`.

---

## References

- `docs/adr/0002-generic-domain-driven-table-registry.md` — the framework this
  extends (registry, `TableDefinition`, `TableService`).
- `docs/api/0002-generic-tables.md` — config + rows contract; gains `order` /
  `width` column fields and a `preferences` companion on acceptance.
- `docs/adr/0001-backend-driven-datatable-ag-grid-ssrm.md` — original SSRM /
  envelope / whitelist rationale, reused.
- `standards/architecture.md` (BaseModel, migrations, authorization),
  `standards/security-standards.md` (Trust Nothing, Authorization First, Least
  Privilege), `standards/decision-making.md` (Reuse Before Build, Simplicity).
