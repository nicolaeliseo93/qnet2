<?php

namespace App\Http\Requests\Referents;

use App\DataObjects\Referents\ReferentDuplicateCriteria;
use Illuminate\Contracts\Validation\Validator;
use Illuminate\Foundation\Http\FormRequest;

/**
 * Validates the payload for POST /api/referents/duplicate-check (spec
 * 0037). Authorization is intentionally NOT handled here (it stays in the
 * controller via authorize('create', Referent::class)).
 */
class CheckReferentDuplicatesRequest extends FormRequest
{
    public function authorize(): bool
    {
        // Authorization handled in the controller via the ReferentPolicy.
        return true;
    }

    /**
     * @return array<string, array<int, mixed>>
     */
    public function rules(): array
    {
        return [
            'tax_code' => ['nullable', 'string', 'max:32'],
            'contacts' => ['nullable', 'array'],
            'contacts.*.type' => ['required', 'in:email,phone,mobile'],
            'contacts.*.value' => ['required', 'string', 'max:255'],
        ];
    }

    /**
     * At least one non-empty criterion is required — a bare empty payload
     * would otherwise return every referent's tax_code/contact-less "match".
     */
    public function withValidator(Validator $validator): void
    {
        $validator->after(function (Validator $validator): void {
            if ($this->hasNoCriteria()) {
                $validator->errors()->add('tax_code', 'At least one of tax_code or contacts is required.');
            }
        });
    }

    private function hasNoCriteria(): bool
    {
        $taxCode = trim((string) $this->input('tax_code', ''));

        $contacts = collect($this->input('contacts', []))
            ->filter(fn (mixed $contact): bool => is_array($contact) && trim((string) ($contact['value'] ?? '')) !== '');

        return $taxCode === '' && $contacts->isEmpty();
    }

    /**
     * The validated payload as a typed DTO (no magic array crosses into the
     * Service — see standards/architecture.md → Data Transfer Objects).
     */
    public function toCriteria(): ReferentDuplicateCriteria
    {
        /** @var array<string, mixed> $validated */
        $validated = $this->validated();

        return ReferentDuplicateCriteria::fromValidated($validated);
    }
}
