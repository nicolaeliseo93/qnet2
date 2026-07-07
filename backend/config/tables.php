<?php

use App\Tables\AttributesTableDefinition;
use App\Tables\BusinessFunctionsTableDefinition;
use App\Tables\CompaniesTableDefinition;
use App\Tables\OperationalSitesTableDefinition;
use App\Tables\ProductCategoriesTableDefinition;
use App\Tables\ProductsTableDefinition;
use App\Tables\ReferentsTableDefinition;
use App\Tables\ReferentTypesTableDefinition;
use App\Tables\RolesTableDefinition;
use App\Tables\UsersTableDefinition;

return [

    /*
    |--------------------------------------------------------------------------
    | Generic Domain-driven Table Registry
    |--------------------------------------------------------------------------
    |
    | Maps each table `{domain}` to its TableDefinition class. One pair of
    | endpoints (GET /api/tables/{domain}/columns, POST /api/tables/{domain}/rows)
    | serves every domain; TableRegistry resolves the class below through the
    | container (so its dependencies are injected). An unregistered {domain}
    | resolves to nothing and yields a 404.
    |
    | Adding a domain (e.g. products):
    |   1. class ProductsTableDefinition extends AbstractTableDefinition
    |   2. add 'products' => ProductsTableDefinition::class here
    | No new controller, service, request, resource or route is needed.
    |
    | See docs/adr/0002-generic-domain-driven-table-registry.md
    |     docs/api/0002-generic-tables.md
    */

    'definitions' => [
        'users' => UsersTableDefinition::class,
        'roles' => RolesTableDefinition::class,
        'business-functions' => BusinessFunctionsTableDefinition::class,
        'companies' => CompaniesTableDefinition::class,
        'operational-sites' => OperationalSitesTableDefinition::class,
        'referent-types' => ReferentTypesTableDefinition::class,
        'referents' => ReferentsTableDefinition::class,
        'attributes' => AttributesTableDefinition::class,
        'product-categories' => ProductCategoriesTableDefinition::class,
        'products' => ProductsTableDefinition::class,
    ],

];
