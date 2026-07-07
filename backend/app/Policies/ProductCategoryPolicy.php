<?php

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Standard CRUD policy for the `product-categories` resource (spec 0017). No
 * special overrides: every ability maps to "product-categories.{ability}"
 * via BasePolicy, auto-discovered by Laravel from the ProductCategory model.
 */
class ProductCategoryPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'product-categories';
    }
}
