<?php

namespace Database\Seeders;

use App\DataObjects\Campaigns\CreateCampaignData;
use App\Models\BusinessFunction;
use App\Models\Campaign;
use App\Models\Country;
use App\Models\ProductCategory;
use App\Models\Project;
use App\Models\ProjectStatus;
use App\Models\Referent;
use App\Models\Registry;
use App\Models\Source;
use App\Models\State;
use App\Services\CampaignService;
use Faker\Factory as FakerFactory;
use Faker\Generator;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Development seed for the campaigns module (spec 0023), covering both
 * shapes: LINKED (project_id set, the 4 classification columns forced NULL —
 * BR-2, read-through from the project) and STANDALONE (project_id null, the 4
 * columns are the campaign's own and required).
 *
 * Every campaign is created through CampaignService::create() — the same
 * path POST /api/campaigns uses — so the BR-1 sequential `code`
 * (CMP-0001...) and the BR-2 null-forcing on the derived columns come from
 * the real mechanism. Linked campaign budgets are computed as a fraction of
 * each project's OWN remaining budget (never the full amount), so BR-3
 * (`SUM(campaigns.total_budget) <= project.total_budget`) holds with margin
 * by construction — CampaignService::create() still runs the real guard, so
 * any seeding mistake here fails loudly rather than silently violating it.
 *
 * Depends on DemoProjectSeeder (linked campaigns) plus the same
 * classification lookups it uses (standalone campaigns need their own
 * project-status/business-function/state/product-category). Idempotent:
 * existing campaigns are cleared first — harmless if DemoProjectSeeder
 * already did it, and keeps this seeder runnable on its own too.
 */
class DemoCampaignSeeder extends Seeder
{
    private const int STANDALONE_CAMPAIGNS = 8;

    private const int STATE_SAMPLE = 20;

    public function __construct(private readonly CampaignService $campaigns) {}

    public function run(): void
    {
        // Idempotent even if run standalone (DemoProjectSeeder already clears
        // campaigns before recreating its projects).
        Campaign::query()->delete();

        $faker = FakerFactory::create('it_IT');
        $faker->seed(20260714);

        $projects = Project::query()->orderBy('id')->get();
        $statuses = ProjectStatus::query()->orderBy('sort_order')->get();
        $businessFunctions = BusinessFunction::query()->orderBy('name')->get();
        $productCategories = ProductCategory::query()->orderBy('name')->get();
        $states = $this->italianStates();
        $registries = Registry::query()->orderBy('id')->get();
        $sources = Source::query()->orderBy('name')->get();
        $partners = Referent::query()->orderBy('name')->get();

        $this->seedLinkedCampaigns($faker, $projects, $partners);

        if ($statuses->isEmpty() || $businessFunctions->isEmpty() || $states->isEmpty() || $productCategories->isEmpty()) {
            // Nothing sensible to seed for the standalone shape without all 4
            // required classification lookups (BR-2).
            return;
        }

        $this->seedStandaloneCampaigns($faker, $statuses, $businessFunctions, $states, $productCategories, $registries, $sources, $partners);
    }

    /**
     * One or two campaigns per project, budgeted as a fraction of that
     * project's OWN remaining budget so BR-3 holds with margin. Every 4th
     * project deliberately gets none (exercises BR-5's "no campaigns" delete
     * path). registry/source/partner default to the project's own but the
     * partner is occasionally varied (D-2: "copiati dal progetto ma
     * eventualmente variati").
     *
     * @param  Collection<int, Project>  $projects
     * @param  Collection<int, Referent>  $partners
     */
    private function seedLinkedCampaigns(Generator $faker, Collection $projects, Collection $partners): void
    {
        foreach ($projects as $index => $project) {
            $campaignCount = $this->linkedCampaignCount($index);

            $remainingBudget = $project->total_budget !== null ? (float) $project->total_budget : null;

            for ($slot = 0; $slot < $campaignCount; $slot++) {
                $isLastSlot = $slot === $campaignCount - 1;
                $budget = $this->nextLinkedBudget($faker, $remainingBudget, $isLastSlot);

                if ($remainingBudget !== null && $budget !== null) {
                    $remainingBudget -= $budget;
                }

                $partnerId = $project->partner_id;

                if ($faker->boolean(30) && $partners->isNotEmpty()) {
                    $partnerId = $partners[($index + $slot + 1) % $partners->count()]->id;
                }

                $this->createCampaign($faker, [
                    'project_id' => $project->id,
                    'name' => $faker->unique()->bs(),
                    'registry_id' => $project->registry_id,
                    'source_id' => $project->source_id,
                    'partner_id' => $partnerId,
                    'total_budget' => $budget,
                ]);
            }
        }
    }

    /**
     * 0 campaigns on every 4th project (BR-5 no-campaigns case), 2 on every
     * 3rd of the rest, 1 otherwise.
     */
    private function linkedCampaignCount(int $index): int
    {
        if ($index % 4 === 0) {
            return 0;
        }

        return $index % 3 === 0 ? 2 : 1;
    }

    /**
     * Without a budget cap (A-1), any amount is valid. With a cap, take 60%
     * of what remains on the last slot and 40% on any earlier one — always
     * strictly less than the remainder, so BR-3 holds with margin.
     */
    private function nextLinkedBudget(Generator $faker, ?float $remainingBudget, bool $isLastSlot): ?float
    {
        if ($remainingBudget === null) {
            return $faker->boolean(70) ? $faker->randomFloat(2, 1000, 40000) : null;
        }

        $share = $isLastSlot ? 0.6 : 0.4;

        return round($remainingBudget * $share, 2);
    }

    /**
     * Campaigns with no project (project_id null): the 4 classification
     * columns are the campaign's own and required (BR-2), drawn round-robin
     * across the same lookups DemoProjectSeeder uses.
     *
     * @param  Collection<int, ProjectStatus>  $statuses
     * @param  Collection<int, BusinessFunction>  $businessFunctions
     * @param  Collection<int, State>  $states
     * @param  Collection<int, ProductCategory>  $productCategories
     * @param  Collection<int, Registry>  $registries
     * @param  Collection<int, Source>  $sources
     * @param  Collection<int, Referent>  $partners
     */
    private function seedStandaloneCampaigns(
        Generator $faker,
        Collection $statuses,
        Collection $businessFunctions,
        Collection $states,
        Collection $productCategories,
        Collection $registries,
        Collection $sources,
        Collection $partners,
    ): void {
        for ($index = 0; $index < self::STANDALONE_CAMPAIGNS; $index++) {
            $this->createCampaign($faker, [
                'project_id' => null,
                'name' => $faker->unique()->bs(),
                'registry_id' => $this->pick($registries, $index)?->id,
                'source_id' => $this->pick($sources, $index)?->id,
                'partner_id' => $this->pick($partners, $index)?->id,
                'project_status_id' => $statuses[$index % $statuses->count()]->id,
                'business_function_id' => $businessFunctions[$index % $businessFunctions->count()]->id,
                'state_id' => $states[$index % $states->count()]->id,
                'product_category_id' => $productCategories[$index % $productCategories->count()]->id,
                'total_budget' => $faker->boolean(75) ? $faker->randomFloat(2, 500, 60000) : null,
            ]);
        }
    }

    /**
     * Build the DTO from the given overrides plus shared defaults (dates,
     * target_lead) and create the campaign through the real Service — the
     * derived fields absent from $overrides for a linked campaign are simply
     * left null, since CreateCampaignData::attributes() forces them null
     * regardless (BR-2).
     *
     * @param  array<string, mixed>  $overrides
     */
    private function createCampaign(Generator $faker, array $overrides): void
    {
        $startDate = $faker->dateTimeBetween('-12 months', '+1 month');
        $endDate = $faker->boolean(65)
            ? (clone $startDate)->modify('+'.$faker->numberBetween(1, 10).' months')
            : null;

        $data = new CreateCampaignData(
            projectId: $overrides['project_id'] ?? null,
            name: $overrides['name'],
            description: $faker->boolean(50) ? $faker->sentence() : null,
            registryId: $overrides['registry_id'] ?? null,
            sourceId: $overrides['source_id'] ?? null,
            partnerId: $overrides['partner_id'] ?? null,
            projectStatusId: $overrides['project_status_id'] ?? null,
            businessFunctionId: $overrides['business_function_id'] ?? null,
            stateId: $overrides['state_id'] ?? null,
            productCategoryId: $overrides['product_category_id'] ?? null,
            startDate: $startDate->format('Y-m-d'),
            endDate: $endDate?->format('Y-m-d'),
            totalBudget: $overrides['total_budget'] ?? null,
            targetLead: $faker->boolean(75) ? $faker->numberBetween(1, 150) : null,
        );

        $this->campaigns->create($data);
    }

    /**
     * @template TModel of \Illuminate\Database\Eloquent\Model
     *
     * @param  Collection<int, TModel>  $items
     * @return TModel|null
     */
    private function pick(Collection $items, int $index): mixed
    {
        return $items->isNotEmpty() ? $items[$index % $items->count()] : null;
    }

    /**
     * Italy's regions, mirroring DemoProjectSeeder::italianStates().
     *
     * @return Collection<int, State>
     */
    private function italianStates(): Collection
    {
        $italy = Country::query()->where('iso2', 'IT')->first();

        if ($italy === null) {
            return State::query()->inRandomOrder()->limit(self::STATE_SAMPLE)->get();
        }

        return State::query()->where('country_id', $italy->id)->orderBy('name')->get();
    }
}
