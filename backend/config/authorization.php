<?php

use App\Authorization\BusinessFunctionsAuthorization;
use App\Authorization\CompaniesAuthorization;
use App\Authorization\OperationalSitesAuthorization;
use App\Authorization\ReferentsAuthorization;
use App\Authorization\ReferentTypesAuthorization;
use App\Authorization\RolesAuthorization;
use App\Authorization\UsersAuthorization;

return [

    /*
    |--------------------------------------------------------------------------
    | Centralized Authorization Metadata Registry
    |--------------------------------------------------------------------------
    |
    | Maps each resource key to its ResourceAuthorization class. Resolved
    | through the container by App\Authorization\AuthorizationRegistry (so its
    | dependencies are injected), the same pattern as config/tables.php.
    |
    | GET /api/meta/{resource} and the users/roles CRUD endpoints all read
    | from this map. Adding a resource:
    |   1. class ProductsAuthorization extends AbstractResourceAuthorization
    |   2. add 'products' => ProductsAuthorization::class here
    | No new controller or route is needed.
    |
    | See docs/specs/0004-centralized-authorization-metadata.md
    |     docs/conventions/metadata-driven-forms.md
    */

    'definitions' => [
        'users' => UsersAuthorization::class,
        'roles' => RolesAuthorization::class,
        'business-functions' => BusinessFunctionsAuthorization::class,
        'companies' => CompaniesAuthorization::class,
        'operational-sites' => OperationalSitesAuthorization::class,
        'referent-types' => ReferentTypesAuthorization::class,
        'referents' => ReferentsAuthorization::class,
    ],

];
