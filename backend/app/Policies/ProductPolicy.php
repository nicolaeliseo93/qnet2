<?php

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Standard CRUD policy for the `products` resource (spec 0017). No special
 * overrides: every ability maps to "products.{ability}" via BasePolicy,
 * auto-discovered by Laravel from the Product model.
 */
class ProductPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'products';
    }
}
