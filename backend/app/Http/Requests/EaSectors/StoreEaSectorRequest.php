<?php

namespace App\Http\Requests\EaSectors;

use App\DataObjects\EaSectors\CreateEaSectorData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the payload for POST /api/ea-sectors (spec 0018).
 *
 * A cycle is structurally impossible on create (the sector has no id yet),
 * so no cycle guard is needed here (unlike UpdateEaSectorRequest's
 * companion Service-level guard). Authorization is intentionally NOT
 * handled here (it stays in the controller via authorize('create',
 * EaSector::class)). EnforcesFieldPermissions (spec 0004) additionally
 * rejects any submitted field the actor cannot edit (create context, model
 * = null).
 */
class StoreEaSectorRequest extends FormRequest
{
    use EnforcesFieldPermissions;

    public function authorize(): bool
    {
        // Authorization handled in the controller via EaSectorPolicy.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191'],
            'parent_id' => ['nullable', 'integer', 'exists:ea_sectors,id'],
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
        return 'ea-sectors';
    }

    protected function authorizationModel(): ?Model
    {
        return null;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): CreateEaSectorData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return CreateEaSectorData::fromValidated($validated);
    }
}
