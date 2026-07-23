<?php

namespace App\Http\Requests\Companies;

use App\DataObjects\Companies\CreateCompanyData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Rules\VatNumber;
use App\Support\InputFormat;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for POST /api/companies (spec 0010).
 *
 * Authorization is intentionally NOT handled here (it stays in the controller
 * via authorize('create', Company::class)). The nested `address` rules mirror
 * ValidatesUserProfile's per-row address rules, but for a SINGLE object (a
 * company owns at most one address, see AddressService/CompanyService).
 * EnforcesFieldPermissions (spec 0004) additionally rejects any submitted
 * field the actor cannot edit (create-context, model = null).
 */
class StoreCompanyRequest extends FormRequest
{
    use EnforcesFieldPermissions;

    public function authorize(): bool
    {
        // Authorization handled in the controller via CompanyPolicy.
        return true;
    }

    /**
     * Canonicalize the typed VAT number before the rules run (user directive
     * 2026-07-23): the same code typed `IT 12345678901` or `12345678901`
     * is stored identically here and on the personal-data card.
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
            'denomination' => ['required', 'string', 'max:255'],
            'vat_number' => ['nullable', 'string', 'max:50', new VatNumber],

            // Address: present key (even empty) is authoritative.
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
        return null;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): CreateCompanyData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return CreateCompanyData::fromValidated($validated);
    }
}
