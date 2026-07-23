<?php

declare(strict_types=1);

namespace App\Http\Requests\RequestManagement;

use App\DataObjects\RequestManagement\CreateRequestData;
use App\DataObjects\Users\ProfileData;
use App\Http\Requests\Concerns\ValidatesProductLines;
use App\Http\Requests\Concerns\ValidatesRequestClientProfile;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * POST /api/request-management (spec 0057): creates the Opportunity behind
 * a "Gestione Richieste" row (the record IS an Opportunity, D-1). The client
 * anagraphic block is EXACTLY one of two mutually-exclusive branches (D-2):
 * `registry_id` (an existing Registry, untouched) or `client_identity` (+
 * optional `client_contacts`/`client_address`), which creates a brand-new
 * Registry+PersonalData. `product_lines` reuses ValidatesProductLines
 * VERBATIM (D-3, same rules as the opportunities form).
 *
 * Authorization is intentionally NOT handled here (it stays in the
 * controller, mirroring every other action of this module —
 * `request-management.create`). This request deliberately does NOT compose
 * EnforcesFieldPermissions: every field the form's own catalogue would gate
 * (`opportunity_workflow_status_id`, `attribute_values`, the attribution
 * block, etc. — RequestManagementAuthorization::fields()) belongs to the
 * work panel's PATCH, not to this create-only payload; none of THIS payload's
 * fields (registry_id, client_* blocks, product_lines) are in that
 * catalogue, so calling it here would be a no-op.
 */
class StoreRequestRequest extends FormRequest
{
    use ValidatesProductLines;
    use ValidatesRequestClientProfile;

    public function authorize(): bool
    {
        // Authorization handled in the controller (request-management.create).
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        // D-2's XOR resolved once here (not via requiredIf/prohibitedIf
        // closures composed with `sometimes`): `sometimes` skips a rule
        // entirely for an ABSENT key, which would silently let
        // registry_id's own requiredIf never fire when neither branch is
        // submitted — `required`/`prohibited` are implicit rules that always
        // evaluate presence, so the plain conditional below is both simpler
        // and correct.
        $hasIdentity = $this->filled('client_identity');

        $rules = array_merge(
            [
                // Both halves of the XOR live on this single field, so
                // either branch's violation reports on it (AC-004/AC-005).
                'registry_id' => [
                    $hasIdentity ? 'prohibited' : 'required',
                    'nullable', 'integer', Rule::exists('registries', 'id'),
                ],
            ],
            $this->clientProfileRules(),
            $this->productLinesRules(required: true),
        );

        // D-2: "i blocchi client_* sono rifiutati" when an existing registry
        // is chosen — appended (not replacing) clientProfileRules()' own base
        // shape rules for the two.
        $rules['client_contacts'][] = Rule::prohibitedIf(fn (): bool => $this->filled('registry_id'));
        $rules['client_address'][] = Rule::prohibitedIf(fn (): bool => $this->filled('registry_id'));

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateClientProfile($validator);
            $this->validateProductLines($validator);
        });
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service).
     */
    public function toData(): CreateRequestData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return new CreateRequestData(
            registryId: isset($validated['registry_id']) ? (int) $validated['registry_id'] : null,
            clientProfile: $this->buildClientProfile(),
            productLines: self::normalizeProductLines((array) $validated['product_lines']),
        );
    }

    /**
     * D-2: null on the `registry_id` branch (nothing to write); assembled
     * from the trait's typed payload otherwise. `client_address` is a SINGLE
     * row (this panel's own narrower shape, ValidatesRequestClientProfile),
     * wrapped into ProfileData's array-of-addresses convention.
     */
    private function buildClientProfile(): ?ProfileData
    {
        $payload = $this->clientProfilePayload();

        if (! isset($payload['client_identity'])) {
            return null;
        }

        return new ProfileData(
            card: $payload['client_identity'],
            contacts: $payload['client_contacts'] ?? null,
            addresses: isset($payload['client_address']) ? [$payload['client_address']] : null,
        );
    }

    /**
     * @param  array<int, array<string, mixed>>  $rows
     * @return array<int, array{business_function_id: int, product_category_id: int}>
     */
    private static function normalizeProductLines(array $rows): array
    {
        return array_map(
            static fn (array $row): array => [
                'business_function_id' => (int) $row['business_function_id'],
                'product_category_id' => (int) $row['product_category_id'],
            ],
            $rows,
        );
    }
}
