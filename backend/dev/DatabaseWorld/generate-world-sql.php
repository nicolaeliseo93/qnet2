<?php

declare(strict_types=1);

/**
 * Regenerates dev/DatabaseWorld/world.sql from the dr5hn
 * "countries-states-cities" dataset (https://github.com/dr5hn/countries-states-cities-database),
 * licensed under the Open Database License (ODbL v1.0) — attribution required.
 *
 * WHY THIS SCRIPT EXISTS (no hidden logic, per standards/ai-rules.md):
 * the upstream dataset models the geo hierarchy in a single flat `states` table
 * using `level` (1 = region, 2 = province / metropolitan city / ...) and
 * `parent_id` (province -> region). This Starter Kit instead wants an explicit
 * four-level hierarchy with denormalized ancestor keys on every row:
 *
 *     countries -> states (regions, level 1) -> provinces (level 2+) -> cities
 *
 * so that a city is reachable from any ancestor id (country_id / state_id /
 * province_id), exactly like the existing pattern. This script applies that
 * transformation deterministically and emits one INSERT block per table, in FK
 * dependency order (countries, states, provinces, cities).
 *
 * SOURCE FILES (download once into ./sources, see README.md for URLs):
 *   - countries.json
 *   - states.json                       (has level + parent_id)
 *   - countries+states+cities.json      (nested; gives city -> leaf state)
 *
 * USAGE:
 *   php generate-world-sql.php [sourcesDir=./sources] [outFile=./world.sql]
 *
 * TRANSFORMATION RULES:
 *   - A state with parent_id = NULL is a REGION  -> `states` table.
 *   - A state with parent_id != NULL is a PROVINCE -> `provinces` table, with
 *     `state_id` = its top-level region ancestor.
 *   - Each city is nested under its leaf state L:
 *       state_id    = top-level region ancestor of L (always set, NOT NULL)
 *       province_id = L when L is itself a province (parent_id != NULL), else NULL
 *       country_id  = L.country_id
 */
ini_set('memory_limit', '-1');

$sourcesDir = rtrim($argv[1] ?? __DIR__.'/sources', '/');
$outFile = $argv[2] ?? __DIR__.'/world.sql';

function loadJson(string $path): array
{
    if (! is_file($path)) {
        fwrite(STDERR, "Missing source file: {$path}\n");
        exit(1);
    }
    $data = json_decode((string) file_get_contents($path), true, 512, JSON_THROW_ON_ERROR);

    return is_array($data) ? $data : [];
}

/** SQL string literal (escapes backslash and single quote). */
function s(?string $v): string
{
    if ($v === null) {
        return 'NULL';
    }

    return "'".str_replace(['\\', "'"], ['\\\\', "\\'"], $v)."'";
}

function n(int|string|null $v): string
{
    return $v === null ? 'NULL' : (string) (int) $v;
}

fwrite(STDERR, "Loading sources...\n");
$countries = loadJson($sourcesDir.'/countries.json');
$states = loadJson($sourcesDir.'/states.json');
$nested = loadJson($sourcesDir.'/countries+states+cities.json');
fwrite(STDERR, "Loaded. Building rows...\n");

// Index states by id and resolve the top-level region ancestor of each state.
$stateById = [];
foreach ($states as $st) {
    $stateById[(int) $st['id']] = $st;
}

// Resolve the top-level region ancestor, iteratively and cycle-safe: the source
// data contains self-parented rows (parent_id == id) and could contain longer
// loops, so we stop as soon as a node repeats and treat the last one as the root.
$rootCache = [];
$rootOf = function (int $id) use (&$rootCache, $stateById): int {
    if (isset($rootCache[$id])) {
        return $rootCache[$id];
    }
    $seen = [];
    $current = $id;
    while (true) {
        $seen[$current] = true;
        $st = $stateById[$current] ?? null;
        $parent = $st['parent_id'] ?? null;
        if ($st === null || $parent === null || $parent === '') {
            break; // reached a root (or an orphan: its own root)
        }
        $parent = (int) $parent;
        if ($parent === $current || isset($seen[$parent]) || ! isset($stateById[$parent])) {
            break; // self-parent, cycle, or dangling parent → stop here
        }
        $current = $parent;
    }

    return $rootCache[$id] = $current;
};

// A state is a province when it has a usable (non-self, existing) parent. Rows
// that only "self-parent" are roots, not provinces.
$isProvince = function (array $st) use ($stateById): bool {
    $p = $st['parent_id'] ?? null;
    if ($p === null || $p === '') {
        return false;
    }
    $p = (int) $p;

    return $p !== (int) $st['id'] && isset($stateById[$p]);
};

// ---------------------------------------------------------------------------
// Build rows
// ---------------------------------------------------------------------------
$countryRows = [];
foreach ($countries as $c) {
    $countryRows[] = sprintf(
        '(%d,%s,%s,1,%s,%s,%s,%s)',
        (int) $c['id'],
        s($c['iso2'] ?? null),
        s($c['name'] ?? null),
        s((string) ($c['phonecode'] ?? '')),
        s($c['iso3'] ?? null),
        s((string) ($c['region'] ?? '')),
        s((string) ($c['subregion'] ?? '')),
    );
}

$stateRows = [];
$provinceRows = [];
foreach ($states as $st) {
    $id = (int) $st['id'];
    if ($isProvince($st)) {
        $provinceRows[] = sprintf(
            '(%d,%d,%d,%s,%s)',
            $id,
            (int) $st['country_id'],
            $rootOf($id),
            s($st['name'] ?? null),
            s($st['country_code'] ?? null),
        );
    } else {
        $stateRows[] = sprintf(
            '(%d,%d,%s,%s)',
            $id,
            (int) $st['country_id'],
            s($st['name'] ?? null),
            s($st['country_code'] ?? null),
        );
    }
}

$cityRows = [];
$orphanCities = 0;
foreach ($nested as $c) {
    $countryId = (int) $c['id'];
    foreach (($c['states'] ?? []) as $st) {
        $leafId = (int) $st['id'];
        $meta = $stateById[$leafId] ?? null;
        $countryCode = $meta['country_code'] ?? ($c['iso2'] ?? null);
        $rootId = $rootOf($leafId);
        $provinceId = ($meta !== null && $isProvince($meta)) ? $leafId : null;
        if ($meta === null) {
            $orphanCities += count($st['cities'] ?? []);
            // Leaf unknown in states.json: keep the leaf as the state row id so the
            // FK still resolves (it exists in the nested set), no province.
            $rootId = $leafId;
            $provinceId = null;
        }
        foreach (($st['cities'] ?? []) as $city) {
            $cityRows[] = sprintf(
                '(%d,%d,%d,%s,%s,%s)',
                (int) $city['id'],
                $countryId,
                $rootId,
                n($provinceId),
                s($city['name'] ?? null),
                s($countryCode),
            );
        }
    }
}

// ---------------------------------------------------------------------------
// Emit world.sql
// ---------------------------------------------------------------------------
$out = fopen($outFile, 'w');

$block = function ($handle, string $insert, array $rows): void {
    if ($rows === []) {
        return;
    }
    fwrite($handle, $insert."\n");
    fwrite($handle, implode(",\n", array_map(fn ($r) => "\t".$r, $rows)).";\n\n");
};

fwrite($out, "-- Generated by generate-world-sql.php from the dr5hn\n");
fwrite($out, "-- countries-states-cities-database (ODbL v1.0). Do not edit by hand.\n\n");

$block($out, 'INSERT INTO `countries` (`id`, `iso2`, `name`, `status`, `phone_code`, `iso3`, `region`, `subregion`) VALUES', $countryRows);
$block($out, 'INSERT INTO `states` (`id`, `country_id`, `name`, `country_code`) VALUES', $stateRows);
$block($out, 'INSERT INTO `provinces` (`id`, `country_id`, `state_id`, `name`, `country_code`) VALUES', $provinceRows);
$block($out, 'INSERT INTO `cities` (`id`, `country_id`, `state_id`, `province_id`, `name`, `country_code`) VALUES', $cityRows);

fclose($out);

fprintf(
    STDERR,
    "Wrote %s\n  countries=%d states(regions)=%d provinces=%d cities=%d orphanCities=%d\n",
    $outFile,
    count($countryRows),
    count($stateRows),
    count($provinceRows),
    count($cityRows),
    $orphanCities,
);
