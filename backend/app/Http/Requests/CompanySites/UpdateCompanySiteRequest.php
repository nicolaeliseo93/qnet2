<?php

namespace App\Http\Requests\CompanySites;

use App\DataObjects\CompanySites\UpdateCompanySiteData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Http\Requests\Concerns\ValidatesUserProfile;
use App\Models\CompanySite;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for PUT/PATCH /api/company-sites/{companySite}
 * (spec 0020). Every field is `sometimes` to support partial PATCH updates.
 * `personal_data` present rewrites the site's card (contacts + its single
 * address); a present `banks` key is AUTHORITATIVE (add/update/delete diff,
 * BankService::sync).
 *
 * Authorization is intentionally NOT handled here (it stays in the controller
 * via authorize('update', $companySite)). EnforcesFieldPermissions (spec 0004)
 * rejects any submitted field the actor cannot edit.
 */
class UpdateCompanySiteRequest extends FormRequest
{
    use EnforcesFieldPermissions;
    use ValidatesUserProfile;

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
        return array_merge([
            'name' => ['sometimes', 'required', 'string', 'max:191'],
            'notes' => ['sometimes', 'nullable', 'string'],

            'banks' => ['sometimes', 'array'],
            'banks.*.id' => ['sometimes', 'integer', 'min:1'],
            'banks.*.name' => ['required', 'string', 'max:191'],
            // ISO 13616 shape, aligned with the frontend's client-side regex
            // (`^[A-Z]{2}\d{2}[A-Z0-9]{1,30}$`) so client and server never
            // diverge on what an IBAN "looks like" — case-insensitive here
            // only to be at least as permissive as the client, never less.
            'banks.*.iban' => ['nullable', 'string', 'max:50', 'regex:/^[A-Za-z]{2}[0-9]{2}[A-Za-z0-9]{1,30}$/'],
            'banks.*.notes' => ['nullable', 'string', 'max:191'],
            'banks.*.is_primary' => ['sometimes', 'boolean'],

            'company_id' => ['sometimes', 'nullable', 'integer', Rule::exists('companies', 'id')],
            'responsible_rda_id' => ['sometimes', 'nullable', 'integer', Rule::exists('users', 'id')],
            'responsible_tickets_id' => ['sometimes', 'nullable', 'integer', Rule::exists('users', 'id')],
            'responsible_validation_contracts_id' => ['sometimes', 'nullable', 'integer', Rule::exists('users', 'id')],
            'responsible_validation_contracts_two_id' => ['sometimes', 'nullable', 'integer', Rule::exists('users', 'id')],
            'proforma_progressive' => ['sometimes', 'nullable', 'integer'],
            'invoice_progressive' => ['sometimes', 'nullable', 'integer'],
        ], $this->cappedProfileRules());
    }

    /**
     * The shared nested-profile rules (ValidatesUserProfile), with the
     * company-site cap of AT MOST ONE address applied on top: a site owns a
     * single address, so `personal_data.addresses` is validated `max:1`. Clean
     * array override (instead of an after-hook count check) so the cap is a
     * first-class validation rule reported at the field path.
     *
     * @return array<string, array<int, mixed>>
     */
    private function cappedProfileRules(): array
    {
        $rules = $this->profileRules();
        $rules['personal_data.addresses'] = ['sometimes', 'array', 'max:1'];

        return $rules;
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateProfile($validator);
            $this->enforceFieldPermissions($validator);
        });
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
