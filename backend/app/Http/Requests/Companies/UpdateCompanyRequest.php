<?php

namespace App\Http\Requests\Companies;

use App\DataObjects\Companies\UpdateCompanyData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Models\Company;
use App\Rules\VatNumber;
use App\Support\InputFormat;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for PUT/PATCH /api/companies/{company} (spec 0010).
 * Every field is `sometimes` to support partial PATCH updates. A present
 * `address` key rewrites the company's single address (update if one exists,
 * create otherwise — see CompanyService); absence leaves it untouched.
 *
 * Authorization is intentionally NOT handled here (it stays in the controller
 * via authorize('update', $company)). EnforcesFieldPermissions (spec 0004)
 * additionally rejects any submitted field the actor cannot edit on this
 * specific $company.
 */
class UpdateCompanyRequest extends FormRequest
{
    use EnforcesFieldPermissions;

    public function authorize(): bool
    {
        // Authorization handled in the controller via CompanyPolicy.
        return true;
    }

    /**
     * Canonicalize the typed VAT number before the rules run, exactly as
     * StoreCompanyRequest does (user directive 2026-07-23). Sparse-safe: an
     * absent key is never introduced, so `sometimes` keeps its meaning.
     */
    protected function prepareForValidation(): void
    {
        if (is_string($this->input('vat_number'))) {
            $this->merge(['vat_number' => InputFormat::vatNumber($this->string('vat_number')->toString())]);
        }
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'denomination' => ['sometimes', 'required', 'string', 'max:255'],
            'vat_number' => ['sometimes', 'nullable', 'string', 'max:50', new VatNumber],

            'address' => ['sometimes', 'nullable', 'array'],
            'address.line1' => ['required_with:address', 'string', 'max:255'],
            'address.line2' => ['nullable', 'string', 'max:255'],
            'address.postal_code' => ['nullable', 'string', 'max:20'],
            'address.city_id' => ['nullable', 'integer', Rule::exists('cities', 'id')],
            'address.province_id' => ['nullable', 'integer', Rule::exists('provinces', 'id')],
            'address.state_id' => ['nullable', 'integer', Rule::exists('states', 'id')],
            'address.country_id' => ['nullable', 'integer', Rule::exists('countries', 'id')],
        ];
    }

    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->enforceFieldPermissions($validator);
        });
    }

    protected function authorizationResource(): string
    {
        return 'companies';
    }

    protected function authorizationModel(): ?Model
    {
        /** @var Company $company */
        $company = $this->route('company');

        return $company;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): UpdateCompanyData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return UpdateCompanyData::fromValidated($validated);
    }
}
