<?php

namespace App\Http\Requests\Opportunities;

use App\DataObjects\Opportunities\CreateOpportunityData;
use App\DataObjects\Opportunities\LeadOpportunityDefaults;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Http\Requests\Concerns\ValidatesManagerSlots;
use App\Models\Lead;
use App\Services\Opportunities\LeadOpportunityDefaultsResolver;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for POST /api/opportunities (spec 0040). `name`/
 * `company_id`/`company_site_id` are always required (D-4, amendment rev.1
 * A-2 — NEITHER is BR-1-derivable, no lead/campaign chain to either);
 * `registry_id`/`operational_site_id` are required UNLESS `lead_id` derives
 * them (BR-1). The 5 BR-1-derivable fields (source_id/operational_site_id/
 * registry_id/business_function_id/product_category_id) become `prohibited`
 * when `lead_id` derives a non-null value for them — LeadOpportunityDefaultsResolver
 * is the single source of truth for which ones, shared verbatim with
 * OpportunityService's write-side derivation and the
 * GET /api/leads/{lead}/opportunity-defaults prefill. `referent_id` is NOT
 * derivable (spec 0041 D-1/D-3): it stays a plain, always-editable field
 * scoped to the chosen registry (BR-4, spec 0040).
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller via authorize('create', Opportunity::class)). EnforcesFieldPermissions
 * (spec 0004) additionally rejects any submitted field the actor cannot edit
 * (create-context, model = null).
 */
class StoreOpportunityRequest extends FormRequest
{
    use EnforcesFieldPermissions;
    use ValidatesManagerSlots;

    private ?LeadOpportunityDefaults $leadDefaultsCache = null;

    private bool $leadDefaultsResolved = false;

    public function authorize(): bool
    {
        // Authorization handled in the controller via OpportunityPolicy.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        $locked = $this->leadDefaults()?->lockedFields ?? [];

        return array_merge([
            'name' => ['required', 'string', 'max:255'],
            'registry_id' => $this->derivableRule($locked, 'registry_id', required: true, table: 'registries'),
            'company_id' => ['required', 'integer', Rule::exists('companies', 'id')],
            'company_site_id' => ['required', 'integer', Rule::exists('company_sites', 'id')],
            'operational_site_id' => $this->derivableRule($locked, 'operational_site_id', required: true, table: 'operational_sites'),
            'business_function_id' => $this->derivableRule($locked, 'business_function_id', required: false, table: 'business_functions'),
            'referent_id' => ['nullable', 'integer', Rule::exists('referents', 'id')],
            'commercial_id' => ['nullable', 'integer', Rule::exists('referents', 'id')],
            'reporter_id' => ['nullable', 'integer', Rule::exists('referents', 'id')],
            'supervisor_id' => ['nullable', 'integer', Rule::exists('users', 'id')],
            'source_id' => $this->derivableRule($locked, 'source_id', required: false, table: 'sources'),
            'product_category_id' => $this->derivableRule($locked, 'product_category_id', required: false, table: 'product_categories'),
            'lead_id' => ['nullable', 'integer', Rule::exists('leads', 'id'), Rule::unique('opportunities', 'lead_id')],
            'start_date' => ['nullable', 'date'],
            'estimated_value' => ['nullable', 'numeric', 'min:0', 'max:9999999999999.99'],
            'expected_close_date' => ['nullable', 'date'],
            'success_probability' => ['nullable', 'integer', 'between:0,100'],
        ], $this->managerSlotsRules());
    }

    /**
     * One of the 6 BR-1-derivable fields: `prohibited` when $locked derives a
     * value for it, else the plain (required or nullable) relation rule.
     *
     * @param  array<int, string>  $locked
     * @return array<int, mixed>
     */
    private function derivableRule(array $locked, string $field, bool $required, string $table): array
    {
        if (in_array($field, $locked, true)) {
            return ['prohibited'];
        }

        return $required
            ? ['required', 'integer', Rule::exists($table, 'id')]
            : ['nullable', 'integer', Rule::exists($table, 'id')];
    }

    /**
     * The submitted `lead_id`'s BR-1 defaults, memoized (rules() only needs
     * to resolve it once). Null when no lead_id was submitted or it does not
     * exist — the `exists` rule on lead_id itself surfaces that as its own
     * 422, so every other field falls back to its standalone rule.
     */
    private function leadDefaults(): ?LeadOpportunityDefaults
    {
        if ($this->leadDefaultsResolved) {
            return $this->leadDefaultsCache;
        }

        $leadId = $this->input('lead_id');
        $lead = is_numeric($leadId) ? Lead::find((int) $leadId) : null;

        $this->leadDefaultsCache = $lead !== null
            ? app(LeadOpportunityDefaultsResolver::class)->resolve($lead)
            : null;
        $this->leadDefaultsResolved = true;

        return $this->leadDefaultsCache;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateManagerSlots($validator);
            $this->enforceFieldPermissions($validator);
        });
    }

    protected function authorizationResource(): string
    {
        return 'opportunities';
    }

    protected function authorizationModel(): ?Model
    {
        return null;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): CreateOpportunityData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return CreateOpportunityData::fromValidated($validated);
    }
}
