<?php

use App\Imports\BusinessFunctionsImportDefinition;
use App\Imports\CompaniesImportDefinition;
use App\Imports\LeadsImportDefinition;
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
        'leads' => LeadsImportDefinition::class,
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

    /*
    |--------------------------------------------------------------------------
    | Column mapping aliases (spec 0033 — App\Imports\Support\ColumnMapper)
    |--------------------------------------------------------------------------
    |
    | Extra human header variants (IT/EN), on top of a field's own id/label,
    | that ColumnMapper's auto-mapping resolves to the given mappable field
    | id. Matched after normalization (lower/trim/accent-strip/underscore-
    | and-dash-to-space), so "E-mail" already matches "email" without an
    | alias — these entries cover the variants that normalization alone
    | does not bridge. Centralized here, never scattered across
    | ImportDefinition classes.
    */
    'column_aliases' => [
        'full_name' => ['nome completo', 'full name', 'nominativo'],
        'first_name' => ['nome', 'first name'],
        'last_name' => ['cognome', 'last name'],
        'email' => ['e mail', 'indirizzo email'],
        'phone' => ['telefono', 'tel'],
        'mobile' => ['cellulare', 'cell'],
        'company_name' => ['ragione sociale', 'azienda', 'company'],
        'tax_code' => ['codice fiscale', 'cf'],
        'vat_number' => ['partita iva', 'piva', 'vat'],
        'street' => ['indirizzo', 'via'],
        'postal_code' => ['cap', 'zip', 'zip code'],
        'country' => ['nazione', 'stato'],
        'region' => ['regione'],
        'province' => ['provincia'],
        'city' => ['citta', 'comune'],
        'notes' => ['note'],
    ],

];
