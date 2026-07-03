<?php

use App\Imports\BusinessFunctionsImportDefinition;
use App\Imports\CompaniesImportDefinition;
use App\Imports\OperationalSitesImportDefinition;
use App\Imports\RolesImportDefinition;
use App\Imports\UsersImportDefinition;

return [

    /*
    |--------------------------------------------------------------------------
    | Generic Domain-driven Import Registry
    |--------------------------------------------------------------------------
    |
    | Maps each `{domain}` to its ImportDefinition class, mirroring
    | config/tables.php. One set of endpoints (GET .../template, POST ...,
    | GET .../{importRun}, POST .../{importRun}/confirm, GET .../{importRun}/
    | errors) serves every domain; ImportRegistry resolves the class below
    | through the container (so its dependencies — a domain Service,
    | GeoResolver — are injected). An unregistered {domain} resolves to
    | nothing and yields a 404.
    |
    | Adding a domain (e.g. products):
    |   1. class ProductsImportDefinition extends AbstractImportDefinition
    |   2. add 'products' => ProductsImportDefinition::class here
    | No new controller, service, request, resource or route is needed.
    |
    */

    'definitions' => [
        'business-functions' => BusinessFunctionsImportDefinition::class,
        'companies' => CompaniesImportDefinition::class,
        'operational-sites' => OperationalSitesImportDefinition::class,
        'roles' => RolesImportDefinition::class,
        'users' => UsersImportDefinition::class,
    ],

    /*
    |--------------------------------------------------------------------------
    | Magic-value constants (spec 0012 constraints)
    |--------------------------------------------------------------------------
    |
    | Centralized here so no import limit is hard-coded/scattered across
    | CsvReader, the Jobs or the Controller.
    */

    // Maximum accepted upload size, in kilobytes (Laravel's `max:` file rule unit).
    'max_file_kb' => (int) env('IMPORT_MAX_FILE_KB', 5120),

    // Maximum number of data rows CsvReader accepts (header excluded).
    'max_rows' => (int) env('IMPORT_MAX_ROWS', 5000),

    // Rows shown in the preview's valid_sample.
    'preview_valid' => (int) env('IMPORT_PREVIEW_VALID', 10),

    // Rows shown in the preview's invalid_sample (the FULL rejected set still
    // goes to the downloadable errors report regardless of this cap).
    'preview_invalid' => (int) env('IMPORT_PREVIEW_INVALID', 50),

    // Rows grouped per commit batch during ProcessImportJob (a future
    // chunked-commit optimization hook; per-row transactions are the current
    // isolation unit — see ProcessImportJob).
    'batch_size' => (int) env('IMPORT_BATCH_SIZE', 200),

];
