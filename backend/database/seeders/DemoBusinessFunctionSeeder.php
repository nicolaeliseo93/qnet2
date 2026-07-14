<?php

namespace Database\Seeders;

use App\Models\BusinessFunction;
use App\Models\User;
use Faker\Factory as FakerFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class DemoBusinessFunctionSeeder extends Seeder
{
    /**
     * Curated demo business functions with their mutually-exclusive type
     * (spec 0010): 'unit' → business unit, 'service' → business service,
     * null → neither. `name` is UI content (i18n-exposed domain value), so the
     * Italian labels are intentional — they are not identifiers.
     *
     * @var array<int, array{name: string, type: 'unit'|'service'|null}>
     */
    private const array FUNCTIONS = [
        ['name' => 'Direzione Generale', 'type' => null],
        ['name' => 'Amministrazione, Finanza e Controllo', 'type' => 'unit'],
        ['name' => 'Risorse Umane', 'type' => 'unit'],
        ['name' => 'Commerciale e Vendite', 'type' => 'unit'],
        ['name' => 'Marketing e Comunicazione', 'type' => 'unit'],
        ['name' => 'Ricerca e Sviluppo', 'type' => 'unit'],
        ['name' => 'Produzione', 'type' => 'unit'],
        ['name' => 'Logistica e Magazzino', 'type' => 'unit'],
        ['name' => 'Acquisti e Approvvigionamenti', 'type' => 'unit'],
        ['name' => 'Qualità', 'type' => 'service'],
        ['name' => 'Sistemi Informativi (IT)', 'type' => 'service'],
        ['name' => 'Assistenza Clienti', 'type' => 'service'],
        ['name' => 'Affari Legali', 'type' => 'service'],
        ['name' => 'Sicurezza e Ambiente (HSE)', 'type' => 'service'],
        ['name' => 'Facility Management', 'type' => 'service'],
    ];

    private const int MIN_MEMBERS = 2;

    private const int MAX_MEMBERS = 8;

    private const int BASE_SEED = 20260703;

    /**
     * Seed the demo business functions, each with a responsible manager and a
     * set of associated users drawn from the already-seeded user pool
     * (DemoUsersSeeder runs first). Idempotent: re-running updates in place by name
     * and never duplicates a function or a membership row.
     */
    public function run(): void
    {
        /** @var Collection<int, int> $userIds */
        $userIds = User::query()->pluck('id');

        // No users to associate with (users-less environment): still seed the
        // functions alone so the module is populated.
        $this->seedFunctions($userIds, withRelations: $userIds->isNotEmpty());
    }

    /**
     * @param  Collection<int, int>  $userIds
     */
    private function seedFunctions(Collection $userIds, bool $withRelations): void
    {
        $faker = FakerFactory::create('it_IT');

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

            if ($withRelations) {
                $faker->seed(self::BASE_SEED + $index);
                $memberCount = $faker->numberBetween(self::MIN_MEMBERS, min(self::MAX_MEMBERS, $userIds->count()));
                // randomElements picks unique ids deterministically for the seed.
                $businessFunction->users()->sync($faker->randomElements($userIds->all(), $memberCount));
            }
        }
    }
}
