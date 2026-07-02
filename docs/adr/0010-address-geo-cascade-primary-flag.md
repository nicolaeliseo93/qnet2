# Architecture Decision Record

## ADR ID

0010

## Title

Guided geo cascade for Address (country → state → city), hidden coordinates, and the `is_primary` flag

## Status

ACCEPTED

## Date

2026-06-15

---

## Context

The Address module (ADR 0006, polymorphic personal-data) already stores `city_id`,
`state_id`, `country_id`, `latitude` and `longitude`, but:

- The frontend `address-form.tsx` only renders `label / line1 / line2 / postal_code /
  latitude / longitude`. The geo foreign keys are accepted by the backend but are NOT
  selectable in the UI, so addresses are never linked to the existing geo hierarchy.
- Coordinates (`latitude` / `longitude`) are exposed as raw text inputs — meaningless
  data entry for end users.
- There is no way to mark one address of an owner as the principal one.

The geo hierarchy already exists as reference data: `countries` (250 rows),
`states` (= the user-facing "provincia", `states.country_id`), `cities`
(`cities.state_id`, potentially thousands per state). Models `Country / State / City`
extend `BaseModel`, are exempt from activity log / Policy CRUD / full Factory
(reference data, per `architecture.md`). **No geo lookup endpoint exists yet.**

`ContactService` already owns a cross-row invariant ("at most one primary per
owner + type", `demoteSiblings`) that is the template for Address's `is_primary`.

Forces / constraints:

- Reusable + owner-agnostic: the Address component and the geo selector must work for
  any future owner (supplier, company), not just personal-data.
- Coordinates must remain in DB/backend for future use; only the UI drops them.
- The frontend has only the base shadcn `Select` (no combobox/command/popover with
  search). `cities` can be large.
- Standards: thin controllers, Service for business logic, DTO across layers,
  server-side authorization mandatory, TanStack Query for server state, skeleton
  loading, English source + i18n.

---

## Decision

### 1. Geo lookup endpoints (new, read-only)

Three thin, read-only endpoints inside the existing `auth:sanctum` group, served by a
single `GeoController` with simple Eloquent queries (no Service — these are pure
reference-data lookups with no business logic, the documented exception to the
"logic in Service" rule):

```
GET /api/countries                          → all countries, ordered by name
GET /api/states?country_id={id}             → states of a country (country_id REQUIRED)
GET /api/cities?state_id={id}&search={q}    → cities of a state (state_id REQUIRED,
                                              optional name search, capped result set)
```

- Response: standard `ok()` envelope, payload as dedicated resources
  `CountryResource` (`id`, `name`, `iso2`), `StateResource` (`id`, `name`,
  `country_id`), `CityResource` (`id`, `name`, `state_id`).
- `states` and `cities` require their parent filter (422 if missing) — we never return
  the entire `states`/`cities` table.
- `cities` accepts an optional `search` (name `LIKE`) and is **hard-capped** (e.g.
  `limit 50`) ordered by name, so a large state never ships thousands of rows.
- Authorization: **authenticated (`auth:sanctum`) but no per-resource permission**.
  These are non-sensitive reference data needed by any post-login form; gating them
  behind a CRUD permission would be friction with no security benefit. Consistent with
  `AddressController` living in the same auth group. Abuse is bounded by the required
  parent filter + the cities cap + a `throttle` middleware.
- Indexes: ensure `states.country_id`, `cities.state_id` and `cities.name` are indexed
  (a migration adds any missing index) to keep the cascade queries fast.

**Not** folded into `/api/config` (ADR 0008): config is a small, static, eagerly
prefetched bootstrap payload. Countries alone (250) plus on-demand states/cities are
lookup data fetched lazily as the user navigates the cascade — putting them in config
would bloat the boot payload and couple unrelated concerns. Countries stay an
on-demand query (cached for the session via TanStack Query `staleTime`).

### 2. `is_primary` on Address

- Migration `add_is_primary_to_addresses_table`: `boolean is_primary` default `false`,
  placed after `addressable` columns; index on `(addressable_type, addressable_id,
  is_primary)` to make the "primary of this owner" lookup cheap.
- `Address` model: add `is_primary` to `$fillable` and cast `'is_primary' => 'bool'`.
  **Not** added to `$hidden` (it is not personal data and must stay logged/serialized).
- `CreateAddress` DTO: add `bool $isPrimary = false`, mapped in `toAttributes()`.
- `StoreAddressRequest` / `UpdateAddressRequest`: add
  `'is_primary' => ['sometimes', 'boolean']`; map into the DTO.
- `AddressResource`: add `'is_primary' => $this->is_primary`.
- `AddressService`: own the invariant **"at most one primary `is_primary=true` per
  OWNER (addressable)"** — note: per OWNER, not per type as ContactService does
  (an address has no type). Mirror `demoteSiblings`, keyed on
  `addressable_type + addressable_id`, inside the existing transactions of
  `createFor` / `update`. **The first address of an owner is auto-promoted to
  primary** (when the owner has no other address, force `is_primary = true`) so an
  owner is never left without a principal address.

### 3. Frontend — new reusable `geo` feature + guided cascade

- New feature `frontend/src/features/geo/` (domain-agnostic, NOT under personal-data):
  - `api.ts`: `fetchCountries()`, `fetchStates(countryId)`, `fetchCities(stateId,
    search?)`.
  - `types.ts`: `Country`, `State`, `City`.
  - `query-keys.ts` / hooks `useCountries()`, `useStates(countryId)`,
    `useCities(stateId, search)` — **dependent queries**: `useStates` enabled only when
    `countryId` is set, `useCities` enabled only when `stateId` is set.
  - `geo-select.tsx`: a self-contained, controlled `GeoSelect` rendering the three
    dependent selects (country → state → city), resetting the child value whenever the
    parent changes, with loading/empty handled per select. Domain-agnostic: it takes
    `{ countryId, stateId, cityId }` + `onChange`, knows nothing about addresses.
- **Selects, not a searchable combobox**: country (≤250) and the parent-filtered
  state/city lists are small enough for the base `Select`. The single scalability risk
  — a very large city list — is handled **server-side** by the `cities` `search`
  param + cap, not by building a new combobox/command/popover primitive. This keeps us
  on the existing UI primitive (minimal change, less surface to maintain). If UX later
  needs type-ahead inside the city select, that is an isolated follow-up.

### 4. Frontend — hide coordinates

- Remove `latitude` / `longitude` from `address-form.tsx`, from `address-schema.ts`,
  and from `SERVER_ERROR_FIELDS`. Stop sending them (backend still accepts them as
  null — backward compatible).

### 5. Frontend — `is_primary` toggle + types

- Add an `is_primary` checkbox to `address-form.tsx`, reusing the exact pattern already
  in `contact-form.tsx` (native checkbox, no new primitive). Add `is_primary` to
  `SERVER_ERROR_FIELDS`.
- `types.ts`: add `is_primary: boolean` to `Address`, and `is_primary?: boolean` +
  `city_id/state_id/country_id` to `AddressFields` payloads (city/state/country already
  present in `AddressFields`).

### 6. Component placement — keep Address in personal-data (minimal change)

`AddressForm` / `AddressesManager` are already owner-agnostic (`OwnerRef` props). They
are NOT extracted to a shared location now: nothing outside personal-data consumes them
yet, and `architecture.md` mandates minimal change. The genuinely cross-domain piece —
`GeoSelect` and the `geo` feature — IS placed in its own domain-agnostic feature so any
future owner/form can reuse it. Extraction of the Address components can happen the day
a second consumer appears (YAGNI).

---

## Alternatives Considered

- **Geo data inside `/api/config`** — rejected: bloats the eager bootstrap payload
  (ADR 0008) with up to thousands of cities; cascade data is inherently on-demand.
- **One combined `/api/geo` endpoint with a `level` param** — rejected: three explicit,
  REST-style endpoints are clearer, independently cacheable, and map 1:1 to the three
  dependent queries.
- **Building a searchable combobox/command primitive now** — rejected: new UI surface
  to maintain for a problem (large city list) already solvable server-side via
  `search` + cap on the existing `Select`.
- **`is_primary` enforced by a DB partial unique index** — rejected: MySQL lacks
  partial/filtered unique indexes on a boolean; the morph relation has no DB-level
  scope. The invariant lives in `AddressService` (consistent with `ContactService`).
- **Public (unauthenticated) geo endpoints** — rejected: the forms are post-login;
  staying in `auth:sanctum` is consistent with `AddressController` and avoids an
  unauthenticated abuse surface, at no UX cost.
- **Extracting Address components to `features/address` now** — rejected: no second
  consumer exists; minimal-change + YAGNI.

---

## Trade-offs

- Advantages: reuses the existing geo schema with no new domain tables; coordinates
  preserved in DB; reusable domain-agnostic `GeoSelect`; invariant consistent with the
  established `ContactService` pattern; no new UI dependency; backward compatible.
- Disadvantages: city select has no in-field type-ahead (mitigated by server `search`);
  three extra endpoints + resources to maintain.
- We give up: a fully searchable city picker (deferred until UX demands it).

---

## Consequences

- Positive: addresses become geo-linked and primary-aware; the cascade is reusable
  across future owners; smaller, more meaningful address form.
- Negative: more round-trips (one per cascade level) — acceptable, each is small,
  cached, and lazy.
- Tech debt: the deferred searchable city combobox and the deferred extraction of
  Address components are explicit, tracked follow-ups, not hidden debt.

---

## Affected Agents

Backend Agent (endpoints, migration, model, DTO, requests, resource, service, tests),
Frontend Agent (geo feature, cascade, form changes, types, tests), Reviewer, QA,
Security (optional — new authenticated endpoints, abuse/rate-limit), Documentation
(API contract in `docs/`).

---

## Risks

- N+1 / slow queries on `cities` without indexes — mitigated by the index migration and
  the result cap.
- Migrating existing addresses: rows created before this change have no primary —
  optional data backfill can promote the oldest address per owner; left as a QA/Backend
  decision, not blocking.
- Stale child selection if the parent changes — mitigated by resetting child values in
  `GeoSelect` on parent change.

---

## References

- ADR 0006 (personal-data contacts polymorphic modules)
- ADR 0008 (public bootstrap config endpoint)
- `ContactService::demoteSiblings` (single-primary invariant template)
- `standards/architecture.md` (DTO, reference-data exceptions), `coding-standards.md`
