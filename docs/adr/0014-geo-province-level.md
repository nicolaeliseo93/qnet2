# Architecture Decision Record

## ADR ID

0014

## Title

Explicit province level in the geo hierarchy (country → state → province → city)

## Status

ACCEPTED

## Date

2026-06-15

---

## Context

ADR 0010 introduced the address geo cascade over `countries → states → cities`, where
`states` was used as the user-facing "provincia". That is wrong for Italian addresses
(and incomplete in general): the real hierarchy has a region **and** a province between
country and city — e.g. `Italia → Campania → Napoli → Grumo Nevano`.

The seed dataset (`dev/DatabaseWorld/world.sql`) is the **dr5hn
countries-states-cities-database**. The previous export flattened regions and provinces
into the single `states` table (for Italy: 20 regions + 107 provinces, no parent link)
and pointed Italian **cities at the region** (`Grumo Nevano.state_id` = Campania), so
the province level was effectively absent.

The current upstream dataset already models the hierarchy with `level`
(1 = region, 2 = province / metropolitan city / …) and `parent_id` (province → region),
with cities linked to their leaf division. This is the authoritative source to rebuild
from. Many countries have **no** province level, so the new level must be optional and
must never make a city unreachable.

Forces / constraints:

- Keep the denormalized-key pattern already used (`states.country_id`,
  `cities.country_id+state_id`): every level carries its ancestor ids so a child is
  reachable from any ancestor (scalable, reusable in future modules).
- Reuse the existing geo cascade contract (ADR 0010) and resources; do not migrate geo
  to the `for-select` async component (ADR 0011) — a much larger, unneeded refactor.
- Reference data only: no Policy / activity log / per-resource permission for the new
  `Province` model, like `Country / State / City`.
- Worldwide data; ODbL v1.0 → attribution required.

---

## Decision

### 1. Data model — new `provinces` table

`provinces (id, country_id, state_id, name, country_code)`, FKs to `countries` and
`states` (cascadeOnDelete), mirroring `states`. A province sits **under** a state
(region). `cities` gains a **nullable** `province_id` (FK → provinces, cascadeOnDelete,
indexed); `state_id` stays always set (= region) so province-less cities remain
reachable. `addresses` gains a **nullable** `province_id` (FK → provinces, nullOnDelete),
exactly like its `city_id / state_id / country_id`.

New `Province` model: `belongsTo(Country)`, `belongsTo(State)`, `hasMany(City)`;
`State`/`Country` gain `hasMany(Province)`; `City`/`Address` gain `belongsTo(Province)`.

### 2. Seed regeneration from dr5hn

`dev/DatabaseWorld/generate-world-sql.php` transforms the upstream dr5hn JSON into the
four-table seed: states with `parent_id = NULL` → `states`; states with a usable parent
→ `provinces` (with `state_id` = top-level region ancestor, cycle-safe against
self-parented source rows); each city → `state_id` = top-level region ancestor (always
set), `province_id` = its leaf division when that is a province, else `NULL`. Counts:
countries 250, states 4043, provinces 1265, cities 156025, 0 orphans. See
`dev/DatabaseWorld/README.md` for the ODbL attribution and reproduction steps.

### 3. API — additive, backward-compatible

New `GET /api/provinces?state_id={id}` (`ListProvincesRequest` + `ProvinceResource`,
same thin read-only pattern as states). `GET /api/cities` now accepts `province_id`
(preferred) **or** `state_id` (at least one required; `province_id` wins). `CityResource`
and `AddressResource` expose `province_id`; the address request/DTO and the nested
`personal_data.addresses[]` validation accept and persist it.

### 4. Frontend — optional province step in the cascade

`GeoSelect` becomes `country → state → province → city`. The province select is gated on
the state and simply shows its empty state for countries without provinces; the city
query filters by `province_id` when chosen, otherwise by `state_id`, so cities load as
soon as a state is picked, with or without a province. i18n relabels `state` as
"Region/Regione" and adds "Province/Provincia".

---

## Consequences

- The hierarchy is now correct for Italy and any country with a province level, and
  degrades cleanly (optional province) elsewhere — scalable and reusable.
- Regenerating the seed changes all geo ids. Acceptable: the seed loads into empty
  tables (`locations:add` guarded by `City::doesntExist()`); only runtime `addresses`
  reference these ids (nullable FKs) and tests use factories, not the seed.
- `GET /api/cities?state_id=` stays valid; `/provinces` is purely additive.

---

## References

- ADR 0010 — address geo cascade & primary flag (extended here)
- ADR 0011 — for-select API standard (deliberately not adopted for geo)
- dr5hn countries-states-cities-database (ODbL v1.0)
