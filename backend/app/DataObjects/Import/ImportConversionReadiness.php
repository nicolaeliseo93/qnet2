<?php

declare(strict_types=1);

namespace App\DataObjects\Import;

/**
 * Auto-convert-to-Opportunity readiness of a `leads` import run (spec 0045),
 * computed by App\Services\Import\ImportOpportunityConvertibility: whether
 * `POST .../confirm` with `convert_to_opportunity: true` may proceed. Backs
 * both `GET .../summary`'s `conversion_readiness` block and the confirm
 * gate's `convert_blockers` (App\Exceptions\Import\
 * ImportConversionNotReadyException).
 */
final readonly class ImportConversionReadiness
{
    /**
     * @param  array<int, int>  $rowsWithoutOperatorNumbers  row_number of every creatable row with no effective operator
     */
    public function __construct(
        public bool $operationalSiteSet,
        public bool $campaignDerivesProductLine,
        public int $creatableRowsCount,
        public int $rowsWithoutOperatorCount,
        public array $rowsWithoutOperatorNumbers,
    ) {}

    public function isReady(): bool
    {
        return $this->operationalSiteSet
            && $this->campaignDerivesProductLine
            && $this->rowsWithoutOperatorCount === 0;
    }
}
