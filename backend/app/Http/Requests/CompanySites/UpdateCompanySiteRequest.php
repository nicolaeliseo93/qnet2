<?php

namespace App\Http\Requests\CompanySites;

use App\DataObjects\CompanySites\UpdateCompanySiteData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Models\CompanySite;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for PUT/PATCH /api/company-sites/{companySite}
 * (spec 0020). Every field is `sometimes` to support partial PATCH updates. A
 * present `address` key rewrites the site's single address; a present
 * `banks` key is AUTHORITATIVE (add/update/delete diff, BankService::sync).
 *
 * Authorization is intentionally NOT handled here (it stays in the controller
 * via authorize('update', $companySite)). EnforcesFieldPermissions (spec 0004)
 * rejects any submitted field the actor cannot edit — including every "Altro"
 * key (ceiling visibleReadonly, spec 0020).
 */
class UpdateCompanySiteRequest extends FormRequest
{
    use EnforcesFieldPermissions;

    public function authorize(): bool
    {
        // Authorization handled in the controller via CompanySitePolicy.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['sometimes', 'required', 'string', 'max:191'],
            'email' => ['sometimes', 'required', 'email', 'max:191'],
            'fiscal_code' => ['sometimes', 'nullable', 'string', 'max:20'],
            'vat_number' => ['sometimes', 'nullable', 'string', 'max:20'],
            'phone' => ['sometimes', 'nullable', 'string', 'max:191'],
            'pec' => ['sometimes', 'nullable', 'string', 'max:191'],
            'fax' => ['sometimes', 'nullable', 'string', 'max:191'],
            'notes' => ['sometimes', 'nullable', 'string'],

            'address' => ['sometimes', 'nullable', 'array'],
            'address.line1' => ['required_with:address', 'string', 'max:255'],
            'address.line2' => ['nullable', 'string', 'max:255'],
            'address.postal_code' => ['nullable', 'string', 'max:20'],
            'address.city_id' => ['nullable', 'integer', Rule::exists('cities', 'id')],
            'address.province_id' => ['nullable', 'integer', Rule::exists('provinces', 'id')],
            'address.state_id' => ['nullable', 'integer', Rule::exists('states', 'id')],
            'address.country_id' => ['nullable', 'integer', Rule::exists('countries', 'id')],

            'banks' => ['sometimes', 'array'],
            'banks.*.id' => ['sometimes', 'integer', 'min:1'],
            'banks.*.name' => ['required', 'string', 'max:191'],
            // ISO 13616 shape, aligned with the frontend's client-side regex
            // (`^[A-Z]{2}\d{2}[A-Z0-9]{1,30}$`) so client and server never
            // diverge on what an IBAN "looks like" — case-insensitive here
            // only to be at least as permissive as the client, never less.
            'banks.*.iban' => ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z]{2}[0-9]{2}[A-Za-z0-9]{1,30}$/'],
            'banks.*.notes' => ['nullable', 'string', 'max:191'],
            'default_bank_id' => ['sometimes', 'nullable', 'integer'],

            'responsible_rda_id' => ['sometimes', 'nullable', 'integer', Rule::exists('users', 'id')],
            'responsible_tickets_id' => ['sometimes', 'nullable', 'integer', Rule::exists('users', 'id')],
            'responsible_validation_contracts_id' => ['sometimes', 'nullable', 'integer', Rule::exists('users', 'id')],
            'responsible_validation_contracts_two_id' => ['sometimes', 'nullable', 'integer', Rule::exists('users', 'id')],
            'proforma_progressive' => ['sometimes', 'nullable', 'integer'],
            'invoice_progressive' => ['sometimes', 'nullable', 'integer'],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->enforceFieldPermissions($validator);
            $this->validateDefaultBankId($validator);
        });
    }

    /**
     * `default_bank_id`, if given, must match either a bank submitted in THIS
     * request, or — when `banks` was not resubmitted — one of the site's
     * currently owned banks (a plain "change the default" PATCH that leaves
     * the bank list untouched). CompanySiteService::resolveDefaultBank
     * re-checks the actually persisted set as defence in depth.
     */
    private function validateDefaultBankId(Validator $validator): void
    {
        $defaultBankId = $this->input('default_bank_id');

        if (! $this->has('default_bank_id') || $defaultBankId === null) {
            return;
        }

        $submittedIds = collect($this->input('banks', []))
            ->pluck('id')
            ->filter()
            ->map(static fn (mixed $id): int => (int) $id)
            ->all();

        if (in_array((int) $defaultBankId, $submittedIds, true)) {
            return;
        }

        /** @var CompanySite $companySite */
        $companySite = $this->route('companySite');

        if (! $this->has('banks') && $companySite->banks()->whereKey($defaultBankId)->exists()) {
            return;
        }

        $validator->errors()->add('default_bank_id', 'The selected bank does not belong to this company site.');
    }

    protected function authorizationResource(): string
    {
        return 'company-sites';
    }

    protected function authorizationModel(): ?Model
    {
        /** @var CompanySite $companySite */
        $companySite = $this->route('companySite');

        return $companySite;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): UpdateCompanySiteData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return UpdateCompanySiteData::fromValidated($validated);
    }
}
