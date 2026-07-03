<?php

use App\Models\OperationalSite;
use App\Models\User;
use App\Tables\OperationalSitesTableDefinition;
use App\Tables\UsersTableDefinition;
use Illuminate\Foundation\Testing\RefreshDatabase;

uses(RefreshDatabase::class);

/**
 * Unit-level regression contract for the `applyDerivedSearch` hook added by
 * spec 0011 (TableDefinition interface + AbstractTableDefinition default +
 * TableService::applySearch call site). Every domain that does not override
 * it (users/roles/business-functions/companies) MUST keep returning false —
 * the generic engine's flat OR-LIKE fallback is the only thing that runs for
 * them, unchanged (see also tests/Feature/Table/TableRowsSearchTest.php,
 * which exercises the full users/roles search behavior end-to-end and is
 * itself the functional regression proof for AC-007).
 */
it('AbstractTableDefinition default applyDerivedSearch is a no-op (false) for an existing domain', function () {
    /** @var UsersTableDefinition $definition */
    $definition = app(UsersTableDefinition::class);

    expect($definition->applyDerivedSearch(User::query(), 'name', '%anything%'))->toBeFalse();
});

it('operational-sites overrides the hook and handles city/street itself, false for anything else', function () {
    /** @var OperationalSitesTableDefinition $definition */
    $definition = app(OperationalSitesTableDefinition::class);
    $query = OperationalSite::query();

    expect($definition->applyDerivedSearch($query, 'city', '%milano%'))->toBeTrue()
        ->and($definition->applyDerivedSearch($query, 'postal_code', '%20100%'))->toBeFalse();
});
