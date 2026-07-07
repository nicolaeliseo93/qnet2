<?php

namespace App\Http\Requests\Sources;

use App\DataObjects\Sources\UpdateSourceData;
use App\Http\Requests\Concerns\EnforcesFieldPermissions;
use App\Models\Source;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the payload for PUT/PATCH /api/sources/{source} (spec 0018).
 * `name` is `sometimes` to support partial PATCH updates.
 *
 * Authorization is intentionally NOT handled here (it stays in the controller
 * via authorize('update', $source)). EnforcesFieldPermissions (spec 0004)
 * additionally rejects any submitted field the actor cannot edit on this
 * specific model.
 */
class UpdateSourceRequest extends FormRequest
{
    use EnforcesFieldPermissions;

    public function authorize(): bool
    {
        // Authorization handled in the controller via SourcePolicy.
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
        return 'sources';
    }

    protected function authorizationModel(): ?Model
    {
        /** @var Source $source */
        $source = $this->route('source');

        return $source;
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toData(): UpdateSourceData
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return UpdateSourceData::fromValidated($validated);
    }
}
