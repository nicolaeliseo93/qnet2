<?php

namespace App\Http\Requests\PersonalData;

use App\Http\Requests\Concerns\ResolvesOwner;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validates the query string for GET /api/personal-data — the by-owner read of
 * a card.
 *
 * The owner travels as a public alias + id (personable_type/personable_id)
 * resolved through the config allowlist: the alias is the security boundary, so
 * a request can never target an arbitrary class. Authorization stays in the
 * controller via the PersonalDataPolicy. Query-string params are validated by
 * rules() for GET requests in Laravel, so this works for a read endpoint.
 */
class IndexPersonalDataRequest extends FormRequest
{
    use ResolvesOwner;

    public function authorize(): bool
    {
        // Authorization handled in the controller via the PersonalDataPolicy.
        return true;
    }

    /**
     * The owner fields are required here (unlike the `sometimes` ownerRules used
     * by the Store request), since selecting an owner is the whole purpose of
     * this read.
     *
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            $this->ownerTypeField() => ['required', 'string', Rule::in(array_keys((array) config($this->ownerConfigKey())))],
            $this->ownerIdField() => ['required', 'integer', 'min:1'],
        ];
    }

    /**
     * Enforce that the resolved owner actually exists.
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            $this->validateOwner($validator);
        });
    }

    protected function ownerConfigKey(): string
    {
        return 'personal_data.personable_types';
    }

    protected function ownerTypeField(): string
    {
        return 'personable_type';
    }

    protected function ownerIdField(): string
    {
        return 'personable_id';
    }
}
