<?php

namespace App\Http\Requests\EaSectors;

use App\DataObjects\EaSectors\UpdateEaSectorData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Models\EaSector;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for PUT/PATCH /api/ea-sectors/{eaSector}
 * (spec 0018). Every field is `sometimes` to support partial PATCH
 * updates. The anti-cycle guard (parent_id cannot be the sector itself or
 * one of its own descendants) is enforced by EaSectorService, not here (it
 * needs to walk the tree). Authorization is intentionally NOT handled here
 * (it stays in the controller via authorize('update', $eaSector)).
 * EnforcesFieldPermissions (spec 0004) additionally rejects any submitted
 * field the actor cannot edit on this specific model.
 */
class UpdateEaSectorRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:191'],
            'parent_id' => ['sometimes', 'nullable', 'integer', 'exists:ea_sectors,id'],
            'tag_ids' => ['sometimes', 'array'],
            'tag_ids.*' => ['integer', Rule::exists('tags', 'id')],
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
        /** @var EaSector $eaSector */
        $eaSector = $this->route('eaSector');

        return $eaSector;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): UpdateEaSectorData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return UpdateEaSectorData::fromValidated($validated);
    }
}
