# DatabaseWorld — geo seed data

`world.sql` seeds the geo reference hierarchy used by the address module:

```
countries → states (regions) → provinces → cities
```

Every row carries its ancestor ids (denormalized keys), so a child is reachable
from any ancestor (`country_id` / `state_id` / `province_id`). It is loaded once
into empty tables by `php artisan locations:add` (guarded by `City::doesntExist()`).

## Source & license

Data is derived from the **dr5hn countries-states-cities-database**
(<https://github.com/dr5hn/countries-states-cities-database>), licensed under the
**Open Database License (ODbL v1.0)**. Attribution is required; derivatives must
stay under the same license.

Upstream models the hierarchy in a single flat `states` table using `level`
(1 = region, 2 = province / metropolitan city / …) and `parent_id`
(province → region). `generate-world-sql.php` transforms that into the explicit
four-table hierarchy above:

- `parent_id IS NULL` → **states** (region).
- `parent_id` set (and not self-referential) → **provinces**, with `state_id` =
  the top-level region ancestor.
- each city → `state_id` = its top-level region ancestor (always set),
  `province_id` = its leaf division when that is a province, else `NULL`.

## Regenerating `world.sql`

1. Download the three source files into `./sources/` (not committed):
   - `countries.json`
   - `states.json`
   - `countries+states+cities.json`

   from `https://raw.githubusercontent.com/dr5hn/countries-states-cities-database/master/json/<file>`.

2. Run the generator:

   ```bash
   php generate-world-sql.php ./sources ./world.sql
   ```

   It prints a summary (counts + `orphanCities`, which must be `0`).
