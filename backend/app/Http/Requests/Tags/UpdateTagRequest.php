<?php

namespace App\Http\Requests\Tags;

use App\DataObjects\Tags\UpdateTagData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Models\Tag;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the payload for PUT/PATCH /api/tags/{tag} (spec 0019).
 * `name` is `sometimes` to support partial PATCH updates.
 *
 * Authorization is intentionally NOT handled here (it stays in the controller
 * via authorize('update', $tag)). EnforcesFieldPermissions (spec 0004)
 * additionally rejects any submitted field the actor cannot edit on this
 * specific model.
 */
class UpdateTagRequest extends FormRequest
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
            'name' => ['sometimes', 'required', 'string', 'max:191'],
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
        /** @var Tag $tag */
        $tag = $this->route('tag');

        return $tag;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): UpdateTagData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return UpdateTagData::fromValidated($validated);
    }
}
