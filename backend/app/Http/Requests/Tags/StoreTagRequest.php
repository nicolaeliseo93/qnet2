<?php

namespace App\Http\Requests\Tags;

use App\DataObjects\Tags\CreateTagData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the payload for POST /api/tags (spec 0019).
 *
 * Authorization is intentionally NOT handled here (it stays in the controller
 * via authorize('create', Tag::class)). EnforcesFieldPermissions (spec
 * 0004) additionally rejects any submitted field the actor cannot edit
 * (create-context, model = null).
 */
class StoreTagRequest extends FormRequest
{
    use EnforcesFieldPermissions;

    public function authorize(): bool
    {
        // Authorization handled in the controller via TagPolicy.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:191'],
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
        return 'tags';
    }

    protected function authorizationModel(): ?Model
    {
        return null;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): CreateTagData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return CreateTagData::fromValidated($validated);
    }
}
