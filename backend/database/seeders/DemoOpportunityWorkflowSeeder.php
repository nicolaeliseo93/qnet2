<?php

namespace Database\Seeders;

use App\DataObjects\OpportunityWorkflows\CreateOpportunityWorkflowData;
use App\Enums\WorkflowStatusGroup;
use App\Models\BusinessFunction;
use App\Models\OpportunityWorkflow;
use App\Models\Source;
use App\Services\OpportunityWorkflowService;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;

/**
 * Development seed for the opportunity workflow configurator (spec 0047): a
 * few realistic workflows plus an enriched GLOBAL default status set, so the
 * demo shows the working-state dimension in action.
 *
 * Every workflow is created through OpportunityWorkflowService::create() — the
 * same path POST /api/opportunity-workflows uses — so this exercises the real
 * write path (signature uniqueness, the 3 pinned system rows, criteria sync),
 * not a raw insert. Because DemoDataSeeder runs this BEFORE DemoOpportunitySeeder,
 * the demo opportunities whose source (a criterion below) matches a workflow
 * pick up that workflow's own statuses at creation time (the resolver runs in
 * OpportunityService::create) — the "reference opportunities" that make the
 * feature concrete in the demo grid.
 *
 * Criteria key on `source_id` (a direct Opportunity column DemoOpportunitySeeder
 * assigns round-robin, so matches are deterministic) plus one two-criteria
 * workflow (source + business_function) to exercise the specificity tie-break
 * (AC-011). Idempotent: existing workflows are cleared first (cascading their
 * criteria/statuses), and the default set is re-synced in place.
 *
 * Depends on DemoSourceSeeder (mandatory — the criterion values) and
 * DemoBusinessFunctionSeeder (optional — the two-criteria workflow is skipped
 * when its function is absent). A no-op when no sources are seeded.
 */
class DemoOpportunityWorkflowSeeder extends Seeder
{
    /**
     * Custom intermediate rows added to the GLOBAL default set (open/
     * closed_won/closed_lost system rows are seeded by the migration and left
     * untouched). `color` is one of the badge tokens (BADGE_COLOR_TOKENS).
     *
     * @var array<int, array{name: string, color: string, group: string}>
     */
    private const array DEFAULT_CUSTOM_STATUSES = [
        ['name' => 'Da lavorare', 'color' => 'slate', 'group' => 'open'],
        ['name' => 'In lavorazione', 'color' => 'blue', 'group' => 'pending'],
        ['name' => 'In attesa cliente', 'color' => 'amber', 'group' => 'pending'],
    ];

    public function __construct(private readonly OpportunityWorkflowService $workflows) {}

    public function run(): void
    {
        $sources = Source::query()->pluck('id', 'name');

        if ($sources->isEmpty()) {
            // The criterion values live on sources — nothing sensible to seed
            // without them (mirrors DemoOpportunitySeeder's registry guard).
            return;
        }

        // Step 1: clear existing workflows (cascade drops their criteria/
        // statuses) so the signature-uniqueness check never rejects a re-run.
        OpportunityWorkflow::query()->delete();

        // Step 2: enrich the always-present global default set.
        $this->syncDefaultSet();

        // Step 3: the demo workflows, most-specific last so a matching
        // opportunity resolves to the two-criteria workflow over the plain one.
        $this->seedSourceWorkflow($sources, 'Website', 'Vendite Web', [
            ['name' => 'Primo contatto', 'color' => 'indigo', 'group' => 'open'],
            ['name' => 'Qualificazione', 'color' => 'blue', 'group' => 'pending'],
            ['name' => 'Demo prodotto', 'color' => 'violet', 'group' => 'pending'],
        ]);

        $this->seedSourceWorkflow($sources, 'Referral', 'Segnalazioni', [
            ['name' => 'Verifica segnalazione', 'color' => 'teal', 'group' => 'open'],
            ['name' => 'Trattativa', 'color' => 'amber', 'group' => 'pending'],
        ]);

        $this->seedWebCommercialWorkflow($sources);
    }

    /**
     * The GLOBAL default set (opportunity_workflow_id null): its system rows
     * already exist (migration), so only the custom intermediate rows are
     * synced — full-replace, idempotent on re-run.
     */
    private function syncDefaultSet(): void
    {
        $this->workflows->syncDefaultStatuses(array_map(
            static fn (array $status): array => [
                'id' => null,
                'name' => $status['name'],
                'color' => $status['color'],
                'group' => $status['group'],
            ],
            self::DEFAULT_CUSTOM_STATUSES,
        ));
    }

    /**
     * A single-criterion workflow matched on the source named $sourceName.
     *
     * @param  Collection<string, int>  $sources
     * @param  array<int, array{name: string, color: string, group: string}>  $customStatuses
     */
    private function seedSourceWorkflow($sources, string $sourceName, string $name, array $customStatuses): void
    {
        $sourceId = $sources->get($sourceName);

        if ($sourceId === null) {
            return;
        }

        $this->workflows->create(new CreateOpportunityWorkflowData(
            name: $name,
            isActive: true,
            criteria: [['field' => 'source_id', 'value_id' => $sourceId]],
            statuses: $this->normalizeCustomStatuses($customStatuses),
        ));
    }

    /**
     * A two-criteria workflow (source Website AND business function
     * "Commerciale e Vendite"): more specific than the "Vendite Web" workflow,
     * so an opportunity carrying both resolves to THIS one (AC-011). Skipped
     * when either lookup is absent.
     *
     * @param  Collection<string, int>  $sources
     */
    private function seedWebCommercialWorkflow($sources): void
    {
        $sourceId = $sources->get('Website');
        $businessFunctionId = BusinessFunction::query()->where('name', 'Commerciale e Vendite')->value('id');

        if ($sourceId === null || $businessFunctionId === null) {
            return;
        }

        $this->workflows->create(new CreateOpportunityWorkflowData(
            name: 'Vendite Web Commerciale',
            isActive: true,
            criteria: [
                ['field' => 'source_id', 'value_id' => $sourceId],
                ['field' => 'business_function_id', 'value_id' => $businessFunctionId],
            ],
            statuses: $this->normalizeCustomStatuses([
                ['name' => 'Analisi esigenze', 'color' => 'blue', 'group' => 'open'],
                ['name' => 'Offerta dedicata', 'color' => 'emerald', 'group' => 'pending'],
            ]),
        ));
    }

    /**
     * Shape a demo status list to the CreateOpportunityWorkflowData custom-row
     * contract ({name, color, group}) — the pinned open/closed_won/closed_lost
     * rows are added by WorkflowStatusWriter, not listed here.
     *
     * @param  array<int, array{name: string, color: string, group: string}>  $customStatuses
     * @return array<int, array{name: string, color: ?string, group: string}>
     */
    private function normalizeCustomStatuses(array $customStatuses): array
    {
        return array_map(
            static fn (array $status): array => [
                'name' => $status['name'],
                'color' => $status['color'],
                'group' => WorkflowStatusGroup::from($status['group'])->value,
            ],
            $customStatuses,
        );
    }
}
