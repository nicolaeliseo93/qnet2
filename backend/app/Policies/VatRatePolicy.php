<?php

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Standard CRUD policy for the `vat-rates` resource. No special overrides:
 * every ability maps to "vat-rates.{ability}" via BasePolicy, auto-discovered
 * by Laravel from the VatRate model.
 */
class VatRatePolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'vat-rates';
    }
}
