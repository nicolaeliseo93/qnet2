<?php

declare(strict_types=1);

namespace App\Exceptions\Import;

use App\DataObjects\Import\ImportConversionReadiness;
use RuntimeException;

/**
 * Thrown by ImportService::confirmStaged() when `convert_to_opportunity:
 * true` is requested but the run is not ready (spec 0045): missing
 * operational site, campaign with no derivable product line, or a creatable
 * row with no effective operator. Caught by ImportController::confirm()
 * (before the generic Throwable handler) to build the frozen 422
 * `convert_blockers` body — never surfaced as a generic 500/422.
 */
final class ImportConversionNotReadyException extends RuntimeException
{
    public function __construct(private readonly ImportConversionReadiness $readiness)
    {
        parent::__construct('The import is not ready to convert its leads to opportunities.');
    }

    /**
     * @return array{operational_site_missing: bool, campaign_missing_product_line: bool, rows_without_operator: array<int, int>}
     */
    public function blockers(): array
    {
        return [
            'operational_site_missing' => ! $this->readiness->operationalSiteSet,
            'campaign_missing_product_line' => ! $this->readiness->campaignDerivesProductLine,
            'rows_without_operator' => $this->readiness->rowsWithoutOperatorNumbers,
        ];
    }
}
