<?php

namespace App\DataObjects\CompanySites;

/**
 * Validated payload for a partial (PATCH) company site update
 * (PUT/PATCH /api/company-sites/{companySite}, spec 0020).
 *
 * Every scalar field is legitimately nullable-or-clearable, so a plain null
 * property cannot distinguish "not submitted" from "submitted as null" — each
 * carries a `*Submitted` flag, mirroring UpdateCompanyData/UpdateProductData.
 * The nested `personal_data` card (contacts + address) is read separately by
 * the controller via the request's toProfile() (ValidatesUserProfile) and
 * handed to CompanySiteService as a ProfileData, mirroring Registry. `banks`
 * present is the AUTHORITATIVE list (add/update/delete diff, BankService::sync);
 * `default_bank_id` is resolved AFTER the bank sync (CompanySiteService).
 * "Altro" and `is_default` are never accepted here (see CreateCompanySiteData).
 */
final readonly class UpdateCompanySiteData
{
    /**
     * @param  array<int, BankInput>  $banks
     */
    public function __construct(
        public ?string $name = null,
        public ?string $notes = null,
        public bool $notesSubmitted = false,
        public ?int $responsibleRdaId = null,
        public bool $responsibleRdaIdSubmitted = false,
        public ?int $responsibleTicketsId = null,
        public bool $responsibleTicketsIdSubmitted = false,
        public ?int $responsibleValidationContractsId = null,
        public bool $responsibleValidationContractsIdSubmitted = false,
        public ?int $responsibleValidationContractsTwoId = null,
        public bool $responsibleValidationContractsTwoIdSubmitted = false,
        public ?int $proformaProgressive = null,
        public bool $proformaProgressiveSubmitted = false,
        public ?int $invoiceProgressive = null,
        public bool $invoiceProgressiveSubmitted = false,
        public array $banks = [],
        public bool $banksSubmitted = false,
        public ?int $defaultBankId = null,
        public bool $defaultBankIdSubmitted = false,
    ) {}

    /**
     * Build from the validated UpdateCompanySiteRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data): self
    {
        return new self(
            name: array_key_exists('name', $data) ? (string) $data['name'] : null,
            notes: array_key_exists('notes', $data) ? $data['notes'] : null,
            notesSubmitted: array_key_exists('notes', $data),
            responsibleRdaId: self::nullableInt($data, 'responsible_rda_id'),
            responsibleRdaIdSubmitted: array_key_exists('responsible_rda_id', $data),
            responsibleTicketsId: self::nullableInt($data, 'responsible_tickets_id'),
            responsibleTicketsIdSubmitted: array_key_exists('responsible_tickets_id', $data),
            responsibleValidationContractsId: self::nullableInt($data, 'responsible_validation_contracts_id'),
            responsibleValidationContractsIdSubmitted: array_key_exists('responsible_validation_contracts_id', $data),
            responsibleValidationContractsTwoId: self::nullableInt($data, 'responsible_validation_contracts_two_id'),
            responsibleValidationContractsTwoIdSubmitted: array_key_exists('responsible_validation_contracts_two_id', $data),
            proformaProgressive: self::nullableInt($data, 'proforma_progressive'),
            proformaProgressiveSubmitted: array_key_exists('proforma_progressive', $data),
            invoiceProgressive: self::nullableInt($data, 'invoice_progressive'),
            invoiceProgressiveSubmitted: array_key_exists('invoice_progressive', $data),
            banks: array_key_exists('banks', $data) ? self::buildBanks((array) $data['banks']) : [],
            banksSubmitted: array_key_exists('banks', $data),
            defaultBankId: self::nullableInt($data, 'default_bank_id'),
            defaultBankIdSubmitted: array_key_exists('default_bank_id', $data),
        );
    }

    /**
     * Only the plain scalar attributes the client actually submitted, ready
     * for a partial mass-assignment update. `banks`/`default_bank_id` and the
     * nested profile are handled separately by CompanySiteService.
     *
     * @return array<string, mixed>
     */
    public function submittedAttributes(): array
    {
        $attributes = [];

        if ($this->name !== null) {
            $attributes['name'] = $this->name;
        }

        foreach ([
            'notes' => ['notesSubmitted', 'notes'],
            'responsible_rda_id' => ['responsibleRdaIdSubmitted', 'responsibleRdaId'],
            'responsible_tickets_id' => ['responsibleTicketsIdSubmitted', 'responsibleTicketsId'],
            'responsible_validation_contracts_id' => ['responsibleValidationContractsIdSubmitted', 'responsibleValidationContractsId'],
            'responsible_validation_contracts_two_id' => ['responsibleValidationContractsTwoIdSubmitted', 'responsibleValidationContractsTwoId'],
            'proforma_progressive' => ['proformaProgressiveSubmitted', 'proformaProgressive'],
            'invoice_progressive' => ['invoiceProgressiveSubmitted', 'invoiceProgressive'],
        ] as $column => [$submittedProperty, $valueProperty]) {
            if ($this->{$submittedProperty}) {
                $attributes[$column] = $this->{$valueProperty};
            }
        }

        return $attributes;
    }

    private static function nullableInt(array $data, string $key): ?int
    {
        return array_key_exists($key, $data) && $data[$key] !== null ? (int) $data[$key] : null;
    }

    /**
     * @param  array<int, array<string, mixed>>  $banks
     * @return array<int, BankInput>
     */
    private static function buildBanks(array $banks): array
    {
        return array_values(array_map(
            static fn (array $row): BankInput => new BankInput(
                id: isset($row['id']) ? (int) $row['id'] : null,
                data: new CreateBank(
                    name: (string) ($row['name'] ?? ''),
                    iban: $row['iban'] ?? null,
                    notes: $row['notes'] ?? null,
                ),
            ),
            $banks,
        ));
    }
}
