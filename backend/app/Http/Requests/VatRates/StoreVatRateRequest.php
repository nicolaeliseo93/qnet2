<?php

namespace App\Http\Requests\VatRates;

use App\DataObjects\VatRates\CreateVatRateData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the payload for POST /api/vat-rates.
 *
 * Authorization is intentionally NOT handled here (it stays in the controller
 * via authorize('create', VatRate::class)). EnforcesFieldPermissions (spec
 * 0004) additionally rejects any submitted field the actor cannot edit
 * (create-context, model = null).
 */
class StoreVatRateRequest extends FormRequest
{
    use EnforcesFieldPermissions;

    public function authorize(): bool
    {
        // Authorization handled in the controller via VatRatePolicy.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191'],
            'rate' => ['required', 'numeric', 'min:0'],
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
        return 'vat-rates';
    }

    protected function authorizationModel(): ?Model
    {
        return null;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): CreateVatRateData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return CreateVatRateData::fromValidated($validated);
    }
}
