<?php

namespace App\Http\Requests\VatRates;

use App\DataObjects\VatRates\UpdateVatRateData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Models\VatRate;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the payload for PUT/PATCH /api/vat-rates/{vatRate}. Both fields
 * are `sometimes` to support partial PATCH updates.
 *
 * Authorization is intentionally NOT handled here (it stays in the controller
 * via authorize('update', $vatRate)). EnforcesFieldPermissions (spec 0004)
 * additionally rejects any submitted field the actor cannot edit on this
 * specific model.
 */
class UpdateVatRateRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:191'],
            'rate' => ['sometimes', 'required', 'numeric', 'min:0'],
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
        /** @var VatRate $vatRate */
        $vatRate = $this->route('vatRate');

        return $vatRate;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): UpdateVatRateData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return UpdateVatRateData::fromValidated($validated);
    }
}
