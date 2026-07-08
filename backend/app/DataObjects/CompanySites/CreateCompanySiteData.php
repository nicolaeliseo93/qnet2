<?php

namespace App\DataObjects\CompanySites;

use Illuminate\Http\UploadedFile;

/**
 * Validated payload for creating a company site (POST /api/company-sites,
 * spec 0020). Declared DTO (no "magic flying array") so the
 * StoreCompanySiteRequest → CompanySiteService contract is explicit — see
 * standards/architecture.md → Data Transfer Objects.
 *
 * Only the site's OWN scalar fields (name/notes + Impostazioni) plus the banks
 * are carried here. The nested `personal_data` card (contacts + address) is
 * read separately by the controller via the request's toProfile()
 * (ValidatesUserProfile) and handed to CompanySiteService as a ProfileData,
 * mirroring Registry. The "Altro" section and `is_default` are never accepted
 * on this path (Altro is read-only; the default flag is set exclusively via
 * POST /company-sites/{id}/set-default).
 */
final readonly class CreateCompanySiteData
{
    /**
     * @param  array<int, BankInput>  $banks
     */
    public function __construct(
        public string $name,
        public ?string $notes = null,
        public ?int $responsibleRdaId = null,
        public ?int $responsibleTicketsId = null,
        public ?int $responsibleValidationContractsId = null,
        public ?int $responsibleValidationContractsTwoId = null,
        public ?int $proformaProgressive = null,
        public ?int $invoiceProgressive = null,
        public array $banks = [],
        public ?int $defaultBankId = null,
        public ?UploadedFile $logo = null,
    ) {}

    /**
     * Build from the validated StoreCompanySiteRequest payload.
     *
     * @param  array<string, mixed>  $data
     */
    public static function fromValidated(array $data, ?UploadedFile $logo = null): self
    {
        return new self(
            name: (string) $data['name'],
            notes: $data['notes'] ?? null,
            responsibleRdaId: isset($data['responsible_rda_id']) ? (int) $data['responsible_rda_id'] : null,
            responsibleTicketsId: isset($data['responsible_tickets_id']) ? (int) $data['responsible_tickets_id'] : null,
            responsibleValidationContractsId: isset($data['responsible_validation_contracts_id']) ? (int) $data['responsible_validation_contracts_id'] : null,
            responsibleValidationContractsTwoId: isset($data['responsible_validation_contracts_two_id']) ? (int) $data['responsible_validation_contracts_two_id'] : null,
            proformaProgressive: isset($data['proforma_progressive']) ? (int) $data['proforma_progressive'] : null,
            invoiceProgressive: isset($data['invoice_progressive']) ? (int) $data['invoice_progressive'] : null,
            banks: self::buildBanks($data['banks'] ?? []),
            defaultBankId: isset($data['default_bank_id']) ? (int) $data['default_bank_id'] : null,
            logo: $logo,
        );
    }

    public function hasLogo(): bool
    {
        return $this->logo !== null;
    }

    /**
     * The site attributes for a mass-assignment create.
     *
     * @return array<string, mixed>
     */
    public function attributes(): array
    {
        return [
            'name' => $this->name,
            'notes' => $this->notes,
            'responsible_rda_id' => $this->responsibleRdaId,
            'responsible_tickets_id' => $this->responsibleTicketsId,
            'responsible_validation_contracts_id' => $this->responsibleValidationContractsId,
            'responsible_validation_contracts_two_id' => $this->responsibleValidationContractsTwoId,
            'proforma_progressive' => $this->proformaProgressive,
            'invoice_progressive' => $this->invoiceProgressive,
        ];
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
