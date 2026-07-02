<?php

namespace App\Http\Requests\Auth;

use App\Enums\LocaleEnum;
use App\Http\Requests\Concerns\ValidatesUserProfile;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the payload for PATCH /api/auth/me (self-service profile, ADR 0013).
 *
 * Reuses ValidatesUserProfile verbatim so the nested personal_data rules are the
 * SAME as PATCH /api/users/{user} (ADR 0012) — one source of truth, no fork. The
 * profile is optional here (update semantics): absent → card untouched.
 *
 * `name` is intentionally NOT accepted: on parity with the Users module the name
 * is derived from the submitted personal_data card (ProfileWriter via
 * CreatePersonalData::displayName()). roles / password / personable_* are not
 * accepted either; the owner is forced server-side to the authenticated user.
 *
 * `email` is READ-ONLY here (product decision): it is the registration email and
 * cannot be changed via self-service. GET /api/auth/me still exposes it; any email
 * sent in this payload is silently ignored, never validated nor persisted. The
 * Users module keeps managing email via its own requests, unaffected.
 */
class UpdateProfileRequest extends FormRequest
{
    use ValidatesUserProfile;

    public function authorize(): bool
    {
        // Self-service: ownership by construction (always the authenticated user).
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return array_merge([
            'locale' => ['sometimes', 'required', Rule::in(LocaleEnum::values())],
        ], $this->profileRules());
    }

    /**
     * Apply the per-type contact `value` rules for the nested profile (ADR 0012).
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(fn (Validator $validator) => $this->validateProfile($validator));
    }

    /**
     * Only the account fields the client actually submitted, ready for a partial
     * mass-assignment update. The name is derived from the card (not here), and
     * any forbidden field is dropped by the validator before this runs.
     *
     * @return array<string, string>
     */
    public function accountAttributes(): array
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return array_filter(
            [
                'locale' => $validated['locale'] ?? null,
            ],
            static fn ($value): bool => $value !== null,
        );
    }
}
