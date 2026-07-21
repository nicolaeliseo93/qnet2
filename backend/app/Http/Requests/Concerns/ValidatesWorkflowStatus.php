<?php

declare(strict_types=1);

namespace App\Http\Requests\Concerns;

use App\Models\Opportunity;
use App\Models\OpportunityProductLine;
use App\Services\Opportunities\OpportunityWorkflowResolver;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Support\Collection;

/**
 * Shared cross-field validation for the `opportunity_workflow_status_id`
 * submitted on the opportunity write endpoints (spec 0047, AC-017): the
 * chosen status must belong to the workflow set OpportunityWorkflowResolver
 * resolves for the SUBMITTED (not-yet-persisted) source_id/state_id/
 * product_lines — the exact values the request is about to write, not
 * whatever is currently on the model — so a create/update that simultaneously
 * changes the resolving criteria and the working status is validated against
 * the NEW set, mirroring OpportunityService's write-side resolveAndAssign().
 *
 * Not submitted, or submitted null, both skip validation entirely: either
 * means "let the resolver decide" (see CreateOpportunityData/
 * UpdateOpportunityData docblocks), never a value to check against a set.
 *
 * @phpstan-require-extends FormRequest
 */
trait ValidatesWorkflowStatus
{
    /**
     * @param  Opportunity|null  $current  the persisted opportunity on an
     *                                     update (null on create), the fallback source for whichever of the 3
     *                                     resolving values the request left untouched (partial PATCH)
     */
    protected function validateWorkflowStatus(Validator $validator, ?Opportunity $current = null): void
    {
        $submitted = $this->input('opportunity_workflow_status_id');

        if ($submitted === null || ! is_numeric($submitted)) {
            return;
        }

        $resolver = app(OpportunityWorkflowResolver::class);
        $workflow = $resolver->resolve($this->resolutionOpportunity($current));
        $allowedIds = $resolver->statusesFor($workflow)->pluck('id')->all();

        if (! in_array((int) $submitted, $allowedIds, true)) {
            $validator->errors()->add(
                'opportunity_workflow_status_id',
                "The selected working status does not belong to the opportunity's resolved workflow.",
            );
        }
    }

    /**
     * A transient (never-persisted) Opportunity carrying the SUBMITTED
     * source_id/state_id/product_lines when present, falling back to
     * $current's persisted values for whichever of the 3 the request left
     * untouched.
     */
    private function resolutionOpportunity(?Opportunity $current): Opportunity
    {
        $opportunity = new Opportunity;
        $opportunity->source_id = $this->resolutionScalar('source_id', $current?->source_id);
        $opportunity->state_id = $this->resolutionScalar('state_id', $current?->state_id);
        $opportunity->setRelation('productLines', $this->resolutionProductLines($current));

        return $opportunity;
    }

    private function resolutionScalar(string $field, ?int $fallback): ?int
    {
        if (! $this->has($field)) {
            return $fallback;
        }

        $value = $this->input($field);

        return $value === null ? null : (int) $value;
    }

    /**
     * The submitted `product_lines` rows (well-formed pairs only — a
     * malformed row is already 422'd by ValidatesProductLines) when present,
     * else $current's PERSISTED rows (an explicit query, never a lazy-loaded
     * access).
     *
     * @return Collection<int, OpportunityProductLine>
     */
    private function resolutionProductLines(?Opportunity $current): Collection
    {
        $submitted = $this->input('product_lines');

        if (is_array($submitted)) {
            return collect($submitted)
                ->filter(static fn (mixed $row): bool => is_array($row) && isset($row['business_function_id'], $row['product_category_id']))
                ->map(static fn (array $row): OpportunityProductLine => new OpportunityProductLine([
                    'business_function_id' => (int) $row['business_function_id'],
                    'product_category_id' => (int) $row['product_category_id'],
                ]))
                ->values();
        }

        return $current?->productLines()->get() ?? collect();
    }
}
