<?php

namespace App\Imports;

use App\Models\User;
use Illuminate\Database\Eloquent\Model;

/**
 * Contract for a single domain's CSV import (spec 0012).
 *
 * A definition declares EVERYTHING about its import: which columns the fixed
 * CSV template carries, how to validate one row, how to detect a natural-key
 * duplicate (against the database AND against the rest of the file), and how
 * to create one valid row via the EXISTING domain Service (never duplicated
 * creation logic here). The generic ImportRegistry + ImportController +
 * ValidateImportJob/ProcessImportJob operate ONLY through this contract, so
 * the security-critical parsing/validation/dedup engine lives in exactly one
 * place and every domain inherits it identically — adding a resource is 1
 * class + 1 config line (config/imports.php), mirroring App\Tables.
 *
 * @phpstan-type ImportColumn array{id: string, required: bool}
 */
interface ImportDefinition
{
    /**
     * Permission prefix / domain key, e.g. "business-functions". Also the
     * `resource` field persisted on the ImportRun and matched against the
     * route {domain} segment for ownership.
     */
    public function domain(): string;

    /**
     * The `resource` key persisted/exposed (defaults to domain()).
     */
    public function resource(): string;

    /**
     * The Eloquent model class this import authorizes against. Drives the
     * fail-closed default authorizeImport() (via the model's Policy `import`
     * ability), so a definition can never accidentally skip the permission
     * check.
     *
     * @return class-string<Model>
     */
    public function modelClass(): string;

    /**
     * Authorize the import action. False → 403 on every endpoint.
     *
     * Defaults (in AbstractImportDefinition) to the model's Policy `import`
     * ability (fail-closed): a definition that forgets to override still
     * requires the permission.
     */
    public function authorizeImport(User $actor): bool;

    /**
     * Ordered column catalogue: drives the downloadable CSV template header
     * (in this exact order) AND which columns are required (a blank value on
     * a required column is a validation error the definition itself reports
     * from validateRow(), typically via AbstractImportDefinition::
     * requiredColumnIds()).
     *
     * @return array<int, array{id: string, required: bool}>
     */
    public function columns(): array;

    /**
     * Validate one CSV row's OWN fields (required columns, format, geo
     * resolution, ...). Dedup (natural-key vs existing DB rows AND intra-file
     * duplicates) is NOT this method's concern — it is layered on top by
     * ImportRowProcessor via dedupKey()/existsInDatabase(), so every
     * definition gets identical, correct dedup semantics for free.
     *
     * @param  array<string, string>  $row  column id => raw CSV value (already mapped to the declared header, never re-split)
     * @return array<int, string> motivated error messages; empty = row accepted
     */
    public function validateRow(array $row, ImportRowContext $context): array;

    /**
     * The row's natural-key value for dedup (both intra-file AND existing-DB),
     * built from the row's OWN values — never a DB id. Null means this
     * definition has no meaningful key for the row (e.g. its key column is
     * blank and already reported as an error by validateRow(), or the
     * definition has no natural key at all and only dedups differently — see
     * existsInDatabase()).
     *
     * @param  array<string, string>  $row
     */
    public function dedupKey(array $row): ?string;

    /**
     * Whether a row with the given dedupKey() already exists in the database.
     * A definition with NO database natural key (e.g. operational-sites, which
     * only dedups intra-file on city+street) always returns false here —
     * intra-file dedup still applies via dedupKey(), it just never collides
     * with a persisted row.
     */
    public function existsInDatabase(string $key): bool;

    /**
     * Create ONE valid, already-deduped row by delegating to the EXISTING
     * domain Service (e.g. BusinessFunctionService/CompanyService/
     * OperationalSiteService) — CREATE ONLY, no update/upsert (spec 0012).
     * Called inside the caller's own per-row DB::transaction (ProcessImportJob),
     * so a thrown exception here isolates cleanly to this row.
     *
     * @param  array<string, string>  $row
     */
    public function createRow(User $actor, array $row): void;
}
