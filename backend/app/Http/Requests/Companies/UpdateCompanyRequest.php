<?php

namespace App\Http\Requests\Companies;

use App\DataObjects\Companies\UpdateCompanyData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Models\Company;
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
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'denomination' => ['sometimes', 'required', 'string', 'max:255'],
            'vat_number' => ['sometimes', 'nullable', 'string', 'max:50'],

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
