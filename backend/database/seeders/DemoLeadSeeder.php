<?php

namespace Database\Seeders;

use App\DataObjects\Leads\CreateLeadData;
use App\Models\Campaign;
use App\Models\Lead;
use App\Models\OperationalSite;
use App\Models\Referent;
use App\Models\Source;
use App\Models\User;
use App\Services\LeadService;
use Faker\Factory as FakerFactory;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Development seed for the leads module (spec 0024): round-robins existing
 * referents/campaigns (BR-1, mandatory) against the optional site/source/
 * operator lookups, so the demo grid exercises every derived table column
 * (referent/campaign/operational_site/source/operator) with realistic,
 * varied values.
 *
 * Every lead is created through LeadService::create() — the same path
 * POST /api/leads uses — so this exercises the real write path, not a raw
 * insert. Idempotent: existing leads are cleared first (harmless — nothing
 * else references a Lead, restrictOnDelete only runs the OTHER way).
 *
 * Depends on DemoReferentSeeder, DemoCampaignSeeder, DemoOperationalSiteSeeder,
 * DemoSourceSeeder and DemoUsersSeeder (all seeded earlier in DemoDataSeeder)
 * — a no-op (nothing to seed) if referents or campaigns are empty.
 */
class DemoLeadSeeder extends Seeder
{
    private const int LEADS = 60;

    public function __construct(private readonly LeadService $leads) {}

    public function run(): void
    {
        Lead::query()->delete();

        $faker = FakerFactory::create('it_IT');
        $faker->seed(20260713);

        $referents = Referent::query()->orderBy('id')->get();
        $campaigns = Campaign::query()->orderBy('id')->get();

        if ($referents->isEmpty() || $campaigns->isEmpty()) {
            // Nothing sensible to seed without the 2 mandatory relations (BR-1).
            return;
        }

        $sites = OperationalSite::query()->orderBy('id')->get();
        $sources = Source::query()->orderBy('id')->get();
        $operators = User::query()->orderBy('id')->get();

        for ($index = 0; $index < self::LEADS; $index++) {
            $this->createLead($faker, $index, $referents, $campaigns, $sites, $sources, $operators);
        }
    }

    /**
     * @param  Collection<int, Referent>  $referents
     * @param  Collection<int, Campaign>  $campaigns
     * @param  Collection<int, OperationalSite>  $sites
     * @param  Collection<int, Source>  $sources
     * @param  Collection<int, User>  $operators
     */
    private function createLead(
        Generator $faker,
        int $index,
        Collection $referents,
        Collection $campaigns,
        Collection $sites,
        Collection $sources,
        Collection $operators,
    ): void {
        $data = new CreateLeadData(
            referentId: $referents[$index % $referents->count()]->id,
            campaignId: $campaigns[$index % $campaigns->count()]->id,
            operationalSiteId: $this->maybePick($sites, $index, $faker, 60)?->id,
            sourceId: $this->maybePick($sources, $index + 1, $faker, 70)?->id,
            operatorId: $this->maybePick($operators, $index + 2, $faker, 50)?->id,
            notes: $faker->boolean(40) ? $faker->sentence() : null,
            isConverted: $faker->boolean(35),
        );

        $this->leads->create($data);
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Collection<int, TModel>  $items
     * @return TModel|null
     */
    private function maybePick(Collection $items, int $index, Generator $faker, int $probability): mixed
    {
        if ($items->isEmpty() || ! $faker->boolean($probability)) {
            return null;
        }

        return $items[$index % $items->count()];
    }
}
