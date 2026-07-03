<?php

namespace Database\Seeders;

use App\Models\BusinessFunction;
use App\Models\User;
use Faker\Factory as FakerFactory;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

class BusinessFunctionSeeder extends Seeder
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

    /**
     * Seed the demo business functions, each with a responsible manager and a
     * set of associated users drawn from the already-seeded user pool
     * (UserSeeder runs first). Idempotent: re-running updates in place by name
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
        $faker->seed(20260703);

        foreach (self::FUNCTIONS as $definition) {
            $businessFunction = BusinessFunction::firstOrNew(['name' => $definition['name']]);
            $businessFunction->is_business_unit = $definition['type'] === 'unit';
            $businessFunction->is_business_service = $definition['type'] === 'service';

            if ($withRelations) {
                $businessFunction->manager_id = $faker->randomElement($userIds->all());
            }

            $businessFunction->save();

            if ($withRelations) {
                $memberCount = $faker->numberBetween(self::MIN_MEMBERS, min(self::MAX_MEMBERS, $userIds->count()));
                // randomElements picks unique ids deterministically for the seed.
                $businessFunction->users()->sync($faker->randomElements($userIds->all(), $memberCount));
            }
        }
    }
}
