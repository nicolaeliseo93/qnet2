<?php

namespace Database\Seeders;

use App\Models\BusinessFunction;
use App\Models\OperationalSite;
use App\Models\User;
use Faker\Factory as FakerFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class DemoBusinessFunctionSeeder extends Seeder
{
    /**
     * Curated demo business functions with their mutually-exclusive type
     * (spec 0010): 'unit' → business unit, 'service' → business service,
     * null → neither. An optional `parent` (a name earlier in this list)
     * wires the parent/child hierarchy (spec 0010 REV). `name` is UI content
     * (i18n-exposed domain value), so the Italian labels are intentional —
     * they are not identifiers.
     *
     * @var array<int, array{name: string, type: 'unit'|'service'|null, parent?: string}>
     */
    private const array FUNCTIONS = [
        ['name' => 'Direzione Generale', 'type' => null],
        ['name' => 'Amministrazione, Finanza e Controllo', 'type' => 'unit', 'parent' => 'Direzione Generale'],
        ['name' => 'Risorse Umane', 'type' => 'unit', 'parent' => 'Direzione Generale'],
        ['name' => 'Commerciale e Vendite', 'type' => 'unit', 'parent' => 'Direzione Generale'],
        ['name' => 'Marketing e Comunicazione', 'type' => 'unit', 'parent' => 'Commerciale e Vendite'],
        ['name' => 'Ricerca e Sviluppo', 'type' => 'unit'],
        ['name' => 'Produzione', 'type' => 'unit'],
        ['name' => 'Logistica e Magazzino', 'type' => 'unit', 'parent' => 'Produzione'],
        ['name' => 'Acquisti e Approvvigionamenti', 'type' => 'unit', 'parent' => 'Produzione'],
        ['name' => 'Qualità', 'type' => 'service'],
        ['name' => 'Sistemi Informativi (IT)', 'type' => 'service'],
        ['name' => 'Assistenza Clienti', 'type' => 'service', 'parent' => 'Commerciale e Vendite'],
        ['name' => 'Affari Legali', 'type' => 'service'],
        ['name' => 'Sicurezza e Ambiente (HSE)', 'type' => 'service'],
        ['name' => 'Facility Management', 'type' => 'service'],
    ];

    private const int MIN_MEMBERS = 2;

    private const int MAX_MEMBERS = 8;

    private const int MAX_SITES = 4;

    private const int BASE_SEED = 20260703;

    /**
     * Reseed offset that keeps the operational-site draws in a separate
     * deterministic stream from the manager/member draws (both reseed the
     * shared process PRNG — see seedFunctions).
     */
    private const int SITE_SEED_OFFSET = 100000;

    /**
     * Seed the demo business functions, each with a responsible manager and a
     * set of associated users drawn from the already-seeded user pool
     * (DemoUsersSeeder runs first), then wire the parent/child hierarchy and
     * the operational-site pivot (DemoOperationalSiteSeeder runs first).
     * Idempotent: re-running updates in place by name and never duplicates a
     * function, a membership row or a site link.
     */
    public function run(): void
    {
        /** @var Collection<int, int> $userIds */
        $userIds = User::query()->pluck('id');
        /** @var Collection<int, int> $operationalSiteIds */
        $operationalSiteIds = OperationalSite::query()->pluck('id');

        // No users to associate with (users-less environment): still seed the
        // functions alone so the module is populated.
        $functions = $this->seedFunctions($userIds, withRelations: $userIds->isNotEmpty());

        $this->linkHierarchy($functions);
        $this->attachOperationalSites($functions, $operationalSiteIds);
    }

    /**
     * @param  Collection<int, int>  $userIds
     * @return Collection<string, BusinessFunction> keyed by name
     */
    private function seedFunctions(Collection $userIds, bool $withRelations): Collection
    {
        $faker = FakerFactory::create('it_IT');
        $functions = collect();

        foreach (self::FUNCTIONS as $index => $definition) {
            $businessFunction = BusinessFunction::firstOrNew(['name' => $definition['name']]);
            $businessFunction->is_business_unit = $definition['type'] === 'unit';
            $businessFunction->is_business_service = $definition['type'] === 'service';

            if ($withRelations) {
                // Re-seed right before EACH draw (not once for the whole loop):
                // `Faker\Generator::seed()` reseeds PHP's process-global
                // `mt_rand()` (fakerphp/faker delegates every random draw to
                // it), which every OTHER Faker consumer in this same
                // single-process test run also shares (`fake()`, the
                // container-bound singleton used by every other 0027
                // factory). A single reseed at the top of the loop only
                // guarantees THAT instant is deterministic; it does not
                // protect the draws several iterations later from drifting
                // if the shared PRNG is touched in between. Reseeding
                // per-row, immediately before its own draws, keeps each
                // row's outcome reproducible regardless of what else runs
                // in the same process.
                $faker->seed(self::BASE_SEED + $index);
                $businessFunction->manager_id = $faker->randomElement($userIds->all());
            }

            $businessFunction->save();
            $functions->put($definition['name'], $businessFunction);

            if ($withRelations) {
                $faker->seed(self::BASE_SEED + $index);
                $memberCount = $faker->numberBetween(self::MIN_MEMBERS, min(self::MAX_MEMBERS, $userIds->count()));
                // randomElements picks unique ids deterministically for the seed.
                $businessFunction->users()->sync($faker->randomElements($userIds->all(), $memberCount));
            }
        }

        return $functions;
    }

    /**
     * Set each function's `parent_id` from its declared parent name. Parents
     * always appear before their children in FUNCTIONS, so every reference
     * resolves against the already-seeded map.
     *
     * @param  Collection<string, BusinessFunction>  $functions
     */
    private function linkHierarchy(Collection $functions): void
    {
        foreach (self::FUNCTIONS as $definition) {
            $parent = $functions->get($definition['parent'] ?? '');

            $functions->get($definition['name'])
                ->forceFill(['parent_id' => $parent?->id])
                ->save();
        }
    }

    /**
     * Attach a small deterministic subset of operational sites to each
     * function via the pivot. A no-op when no sites are seeded (partial run).
     *
     * @param  Collection<string, BusinessFunction>  $functions
     * @param  Collection<int, int>  $operationalSiteIds
     */
    private function attachOperationalSites(Collection $functions, Collection $operationalSiteIds): void
    {
        if ($operationalSiteIds->isEmpty()) {
            return;
        }

        $faker = FakerFactory::create('it_IT');

        $functions->values()->each(function (BusinessFunction $function, int $index) use ($faker, $operationalSiteIds): void {
            $faker->seed(self::BASE_SEED + self::SITE_SEED_OFFSET + $index);
            $siteCount = $faker->numberBetween(0, min(self::MAX_SITES, $operationalSiteIds->count()));

            $function->operationalSites()->sync(
                $siteCount === 0 ? [] : $faker->randomElements($operationalSiteIds->all(), $siteCount),
            );
        });
    }
}
