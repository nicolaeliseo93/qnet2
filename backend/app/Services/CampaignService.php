<?php

declare(strict_types=1);

namespace App\Services;

use App\DataObjects\Campaigns\CreateCampaignData;
use App\DataObjects\Campaigns\UpdateCampaignData;
use App\Models\Campaign;
use App\Models\Project;
use App\Services\Concerns\GeneratesSequentialCode;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;

/**
 * Business logic for the `campaigns` resource (spec 0023): create/update
 * (with the server-generated CMP-0001 code, BR-1), the BR-2 classification
 * derivation (forcing the 4 classification fields null when linked to a
 * project) and the BR-3 budget guard, both computed inside the write
 * transaction with a pessimistic lock on the project row so two concurrent
 * writes against the same project's budget always serialize.
 */
class CampaignService
{
    use GeneratesSequentialCode;

    private const string CODE_PREFIX = 'CMP';

    private const string CODE_TABLE = 'campaigns';

    private const string CODE_COLUMN = 'code';

    /**
     * Relations eager-loaded for the detail read tree (CampaignResource):
     * the campaign's OWN classification (standalone case) plus the linked
     * project's (the read-through source when derived_from_project=true),
     * so a single query never N+1s across either branch.
     *
     * @var array<int, string>
     */
    private const array DETAIL_RELATIONS = [
        'project.projectStatus',
        'project.businessFunction',
        'project.state',
        'project.productCategory',
        'registry',
        'source',
        'partner',
        'projectStatus',
        'businessFunction',
        'state',
        'productCategory',
    ];

    public function loadDetail(Campaign $campaign): Campaign
    {
        return $campaign->load(self::DETAIL_RELATIONS);
    }

    /**
     * Create a new campaign. The code is generated inside the transaction
     * with a pessimistic lock (BR-1); when linked, the target project is
     * locked first and the BR-3 budget guard runs before the insert.
     */
    public function create(CreateCampaignData $data): Campaign
    {
        $campaign = DB::transaction(function () use ($data): Campaign {
            if ($data->isLinkedToProject()) {
                $project = $this->lockProject($data->projectId);
                $this->assertBudgetAvailable($project, $data->totalBudget, excludingCampaignId: null);
            }

            // `code` is deliberately absent from Campaign's #[Fillable] (BR-1),
            // so a mass-assigned Campaign::create() would silently drop it,
            // leaving the NOT NULL `code` column unset — assign it directly
            // (bypasses mass-assignment guarding, mirroring how
            // CampaignFactory itself sets it) AFTER the fillable attributes.
            $campaign = new Campaign($data->attributes());
            $campaign->code = $this->nextSequentialCode(self::CODE_TABLE, self::CODE_COLUMN, self::CODE_PREFIX);
            $campaign->save();

            return $campaign;
        });

        return $this->loadDetail($campaign);
    }

    /**
     * Update an existing campaign. Only keys present in $data are touched
     * (partial PATCH), except the 4 BR-2 classification fields, which are
     * additionally forced by resolveUpdateAttributes() whenever the
     * EFFECTIVE project_id (after this update) is non-null.
     */
    public function update(Campaign $campaign, UpdateCampaignData $data): Campaign
    {
        DB::transaction(function () use ($campaign, $data): void {
            $attributes = $this->resolveUpdateAttributes($campaign, $data);
            $effectiveProjectId = array_key_exists('project_id', $attributes)
                ? $attributes['project_id']
                : $campaign->project_id;

            if ($effectiveProjectId !== null) {
                $project = $this->lockProject($effectiveProjectId);
                $requestedBudget = array_key_exists('total_budget', $attributes)
                    ? $attributes['total_budget']
                    : ($campaign->total_budget !== null ? (float) $campaign->total_budget : null);

                $this->assertBudgetAvailable($project, $requestedBudget, excludingCampaignId: $campaign->id);
            }

            // Unconditional save: fire the model's saved event even when no
            // native attribute changed, so the HasCustomFields write pipeline
            // (spec 0021) persists a custom-fields-only edit.
            $campaign->fill($attributes)->save();
        });

        return $this->loadDetail($campaign);
    }

    /**
     * Plain delete: no BR restricts removing a campaign (unlike Projects,
     * which cannot be deleted while they still have campaigns — BR-5).
     */
    public function delete(Campaign $campaign): void
    {
        $campaign->delete();
    }

    /**
     * BR-2: force the 4 classification fields null whenever the campaign
     * will be linked to a project once this update applies — regardless of
     * what was actually submitted (the FormRequest already rejects an
     * explicit conflicting value; this is the transition-to-linked case,
     * AC-028, plus defence in depth).
     *
     * @return array<string, mixed>
     */
    private function resolveUpdateAttributes(Campaign $campaign, UpdateCampaignData $data): array
    {
        $attributes = $data->submittedAttributes();

        $effectiveProjectId = array_key_exists('project_id', $attributes)
            ? $attributes['project_id']
            : $campaign->project_id;

        if ($effectiveProjectId !== null) {
            $attributes['project_status_id'] = null;
            $attributes['business_function_id'] = null;
            $attributes['state_id'] = null;
            $attributes['product_category_id'] = null;
        }

        return $attributes;
    }

    /**
     * Lock the project row for the remainder of this transaction (BR-3): any
     * concurrent create/update against the SAME project's budget must wait
     * for this lock, serializing the allocated-budget check below.
     */
    private function lockProject(int $projectId): Project
    {
        return Project::query()->lockForUpdate()->findOrFail($projectId);
    }

    /**
     * BR-3: SUM(total_budget of the project's OTHER campaigns) + the
     * requested amount must not exceed the project's total_budget. No-op
     * when the project has no budget cap (A-1). `$excludingCampaignId` keeps
     * the campaign being updated from counting against its own new value
     * (AC-026). A null requested budget contributes nothing to the
     * allocation it is about to make.
     */
    private function assertBudgetAvailable(Project $project, ?float $requestedBudget, ?int $excludingCampaignId): void
    {
        if ($project->total_budget === null) {
            return;
        }

        $allocatedQuery = Campaign::query()->where('project_id', $project->id);

        if ($excludingCampaignId !== null) {
            $allocatedQuery->where('id', '!=', $excludingCampaignId);
        }

        $allocated = (float) $allocatedQuery->sum('total_budget');
        $totalBudget = (float) $project->total_budget;
        $remaining = $totalBudget - $allocated;
        $requested = $requestedBudget ?? 0.0;

        if ($requested > $remaining) {
            throw ValidationException::withMessages([
                'total_budget' => [sprintf(
                    'Budget insufficiente sul progetto %s: budget %s, già allocato %s, residuo %s, richiesto %s.',
                    $project->code,
                    $this->formatMoney($totalBudget),
                    $this->formatMoney($allocated),
                    $this->formatMoney($remaining),
                    $this->formatMoney($requested),
                )],
            ]);
        }
    }

    private function formatMoney(float $value): string
    {
        return number_format($value, 2, '.', '');
    }
}
