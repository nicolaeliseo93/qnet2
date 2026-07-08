<?php

namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

/**
 * Standard CRUD policy for the `custom-fields` admin resource (spec 0021 —
 * ADMIN CRUD DEFINIZIONI). Named after the model it guards
 * (CustomFieldDefinition) rather than the resource key, following this
 * codebase's 1:1 Model→Policy naming convention that Laravel's Gate relies on
 * for automatic resolution (Gate::guessPolicyName) — every other Policy here
 * mirrors its model's name the same way (AttributePolicy/Attribute,
 * ProductCategoryPolicy/ProductCategory, …). The exposed permission prefix
 * stays `custom-fields`, matching the domain/resource key used everywhere
 * else in the module (TableDefinition, Authorization, routes).
 */
class CustomFieldDefinitionPolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'custom-fields';
    }
}
