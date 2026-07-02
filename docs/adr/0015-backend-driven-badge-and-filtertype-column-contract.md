# Architecture Decision Record

## ADR ID

0015

## Title

Backend-driven badge columns and an explicit `filterType` in the table column contract

## Status

ACCEPTED

## Date

2026-06-15

---

## Context

The generic, domain-driven table framework (ADR-0002) exposes a per-column
contract through `GET /api/tables/{domain}/columns`. Each column declares
`id, label, type, visible, width, order, sortable, filterable` and an optional
`options` (a flat `string[]` for `set`/`enum` columns). The frontend
(`features/table`, `components/data-table`) is domain-agnostic: it renders
whatever schema the backend returns.

Extending the Users table surfaced two limitations of that contract:

1. **No badge/colour metadata.** The "user type" column (person vs company,
   derived from `PersonalData.type` / `PersonalDataTypeEnum`) must render as a
   colored badge whose values, labels, colours and icons are owned by the
   backend enum — not hardcoded in the frontend. The existing `tags`/`enum`
   columns only carry `options: string[]`; the frontend rendered neutral
   badges. The enum metadata already exists server-side (ADR-0007: `HasMeta`,
   `EnumMeta` with `value/label/color/icon/is_default/hidden_on_form`) and the
   frontend already has the matching `EnumOption` shape — but the table contract
   had no channel to carry it per column.

2. **Filter widget coupled to render type.** The frontend derives the AG Grid
   filter widget from the column `type`, because `filterType` was deliberately
   stripped from the config output (kept "backend-only"). That works only while
   every set-filtered column also *renders* as a set-like type (`tags`/`enum`).
   The new geo columns (country/region/province/city, derived from the primary
   address) must render as plain **text** yet filter as a **set** of
   backend-resolved names. Render type alone cannot express that.

Notably, the frontend `TableColumn` type and `data-table` already supported a
`filterType` field ("Prefers the explicit `filterType` from the config contract
when present") — the contract was designed for it; the backend simply never
emitted it.

## Decision

Extend the column contract additively and backward-compatibly:

1. **New render type `type: "badge"`** (not a reuse of `enum`, so `locale` and
   other `enum` columns are unaffected) plus a new optional column property
   **`badges: EnumMeta[]`** (`{value,label,color,icon,is_default,hidden_on_form}`),
   emitted **only** for badge columns. A definition supplies it via a new
   `AbstractTableDefinition::badgesFor()` hook (mirroring `optionsFor()`), reusing
   the enum's `HasMeta::options()` and `EnumMeta::toArray()`. `options` keeps
   carrying the plain value tokens for the set filter, so the filter machinery is
   untouched. Every non-badge column stays byte-identical (no `badges` key).

2. **Promote `filterType` to the public column contract.** `resolveConfig()` now
   emits `filterType` (`text | number | date | set`, or `null`) on every column.
   The frontend filter widget is driven by it, decoupled from the render `type`
   — enabling text/badge-rendered columns to advertise a `set`/`date` filter.
   This supersedes the earlier "filterType is backend-only" choice from ADR-0002.

The frontend gains a single generic `BadgeCell` (used as a per-type fallback for
`badge` columns, so no domain registers it) and one central colour-token→class
map. No domain-specific logic is added: the Users table stays agnostic.

Filterability for the new columns is wired through the existing derived-filter
hook (`applyDerivedFilter`): `user_type` and geo as `whereHas` set filters
(matched by enum value / geo name), `primary_address`/`primary_contact` as bound
`LIKE` text filters across the `personalData` relations. Geo set-filter options
are resolved from the distinct names actually in use among users' primary
addresses, so the token equals the matched name and the option list stays small.

Sortability for the same derived columns is wired through a sibling hook,
`applyDerivedSort` (added to the table contract alongside `applyDerivedFilter`).
Because these columns have no real `users` column to `ORDER BY`, each sorts on a
**correlated subquery** that yields a single scalar per user (the related geo
name, the primary-address line, the `personalData.type`, or the `MIN` primary
contact value), or `NULL` when the user has no card/primary row. Subqueries are
used rather than JOINs so the main `users.*` selection is never polluted and rows
are never multiplied; the generic engine falls back to a plain `ORDER BY` for
real columns (so e.g. the Roles `users_count` aggregate alias is unaffected). All
seven new columns are therefore `filterable: true, sortable: true`.

## Consequences

- **Positive.** Badge presentation (values/labels/colours/icons) and all filter
  options live entirely in the backend enum/definition; the frontend renders
  from metadata with zero domain knowledge. `filterType` makes render type and
  filter widget independent, which the geo columns require and which the
  frontend was already built to consume. The change is additive: existing
  columns and their tests are unaffected except for the single assertion that
  previously checked `filterType` was *absent* (now asserts it is present).
- **Negative / cost.** The contract has two more concepts (`badge` type,
  `badges` array) to maintain and document. Derived geo/text filters add a
  per-request subquery (`whereHas`); option resolution adds one query per geo
  column at config time (bounded, runs on `GET /columns`, not per row). The
  colour-token→class map must stay in sync with the colour vocabulary used by
  the domain enums.
- **Neutral.** Per-row mapping reads sensitive source fields (address `line1`,
  contact `value`) server-side only to build the formatted strings; the raw
  fields are never returned as row fields (verified by tests).

## References

- ADR-0002 — Generic domain-driven table registry (the contract being extended).
- ADR-0007 — Domain enum attribute metadata reader (`HasMeta` / `EnumMeta`).
- ADR-0010 / ADR-0014 — Address geo cascade, primary flag, province level.
- `docs/api/0002-generic-tables.md` — updated column contract (`type: badge`,
  `badges`, `filterType`).
- `backend/app/Tables/AbstractTableDefinition.php`,
  `backend/app/Tables/UsersTableDefinition.php`,
  `frontend/src/features/table/cell-renderers.tsx`,
  `frontend/src/components/data-table/data-table.tsx`.
