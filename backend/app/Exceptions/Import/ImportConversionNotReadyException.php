<?php

declare(strict_types=1);

namespace App\Exceptions\Import;

use App\DataObjects\Import\ImportConversionReadiness;
use RuntimeException;

/**
 * Thrown by ImportService::confirmStaged() when `convert_to_opportunity:
 * true` is requested but the run is not ready (spec 0045, extended to mirror
 * the per-row Operational Site override): campaign with no derivable product
 * line, a creatable row with no effective operator, or a creatable row with
 * no effective operational site. Caught by ImportController::confirm()
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
     * @return array{campaign_missing_product_line: bool, rows_without_operator: array<int, int>, rows_without_site: array<int, int>}
     */
    public function blockers(): array
    {
        return [
            'campaign_missing_product_line' => ! $this->readiness->campaignDerivesProductLine,
            'rows_without_operator' => $this->readiness->rowsWithoutOperatorNumbers,
            'rows_without_site' => $this->readiness->rowsWithoutSiteNumbers,
        ];
    }
}
