<?php

namespace Database\Seeders;

use App\Enums\QualificationTypeEnum;
use App\Enums\RelationshipTypeEnum;
use App\Models\BusinessFunction;
use App\Models\Company;
use App\Models\OperationalSite;
use App\Models\User;
use Database\Seeders\Concerns\SeedsDevelopmentUsers;
use Faker\Factory as FakerFactory;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Seed an employment profile for every deterministic development user (spec
 * 0015): a small responsible/subordinate hierarchy — the first 2 seeded
 * users become managers (is_manager=true, no reports_to), every other seeded
 * user is a subordinate reporting to one of them, round-robin. Idempotent:
 * the profile is upserted by owner.
 */
class DemoEmploymentProfileSeeder extends Seeder
{
    use SeedsDevelopmentUsers;

    private const int MANAGER_COUNT = 2;

    /** @var Collection<int, int> */
    private Collection $businessFunctionIds;

    /** @var Collection<int, int> */
    private Collection $companyIds;

    /** @var Collection<int, int> */
    private Collection $operationalSiteIds;

    public function run(): void
    {
        $faker = FakerFactory::create('it_IT');
        $faker->seed(20260704);

        $this->businessFunctionIds = BusinessFunction::query()->pluck('id');
        $this->companyIds = Company::query()->pluck('id');
        $this->operationalSiteIds = OperationalSite::query()->pluck('id');

        $users = $this->seededUsersQuery()->orderBy('email')->get();

        if ($users->count() < self::MANAGER_COUNT + 1) {
            // Not enough seeded users to form a hierarchy (e.g. a partial/test
            // seed run) — skip rather than seed a degenerate one.
            return;
        }

        $managers = $users->take(self::MANAGER_COUNT);
        $subordinates = $users->slice(self::MANAGER_COUNT)->values();

        $managers->each(fn (User $manager) => $this->seedManager($faker, $manager));
        $subordinates->each(fn (User $user, int $index) => $this->seedSubordinate($faker, $user, $managers, $index));
    }

    private function seedManager(Generator $faker, User $manager): void
    {
        $manager->employment()->updateOrCreate([], [
            'is_manager' => true,
            'reports_to_id' => null,
            'relationship_type' => RelationshipTypeEnum::Employee->value,
            'qualification_type' => QualificationTypeEnum::Coordinator->value,
            'hired_at' => $faker->dateTimeBetween('-8 years', '-2 years')->format('Y-m-d'),
            'standard_daily_minutes' => 480,
            'break_daily_minutes' => 30,
            ...$this->contractualAttributes($faker),
        ]);
    }

    /**
     * @param  Collection<int, User>  $managers
     */
    private function seedSubordinate(Generator $faker, User $user, Collection $managers, int $index): void
    {
        /** @var User $manager */
        $manager = $managers[$index % $managers->count()];

        $user->employment()->updateOrCreate([], [
            'is_manager' => false,
            'reports_to_id' => $manager->id,
            'relationship_type' => $faker->randomElement(RelationshipTypeEnum::values()),
            'qualification_type' => $faker->randomElement(QualificationTypeEnum::values()),
            'hired_at' => $faker->dateTimeBetween('-5 years', '-1 month')->format('Y-m-d'),
            'standard_daily_minutes' => 480,
            'break_daily_minutes' => 30,
            ...$this->contractualAttributes($faker),
        ]);
    }

    /**
     * The contractual-section FKs (function / company / operational site),
     * each an optional pick from the seeded lookups so the demo covers both
     * the set and the nullable state. Empty entries when a lookup is unseeded.
     *
     * @return array<string, int|null>
     */
    private function contractualAttributes(Generator $faker): array
    {
        return [
            'business_function_id' => $this->maybePick($faker, $this->businessFunctionIds),
            'company_id' => $this->maybePick($faker, $this->companyIds),
            'operational_site_id' => $this->maybePick($faker, $this->operationalSiteIds),
        ];
    }

    /**
     * @param  Collection<int, int>  $ids
     */
    private function maybePick(Generator $faker, Collection $ids): ?int
    {
        return $ids->isNotEmpty() && $faker->boolean(75)
            ? $faker->randomElement($ids->all())
            : null;
    }
}
