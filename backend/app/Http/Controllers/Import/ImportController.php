<?php

namespace App\Http\Controllers\Import;

use App\Enums\ImportDedupMode;
use App\Enums\ImportStatus;
use App\Http\Controllers\Abstract\BaseApiController;
use App\Http\Requests\Import\ConfigureImportRequest;
use App\Http\Requests\Import\UpdateImportRowRequest;
use App\Http\Requests\Import\UploadImportRequest;
use App\Http\Resources\ImportRunResource;
use App\Http\Resources\ImportRunRowResource;
use App\Imports\ImportDefinition;
use App\Imports\ImportRegistry;
use App\Models\ImportRun;
use App\Models\ImportRunRow;
use App\Models\User;
use App\Services\ImportService;
use App\Support\Import\ImportRunPayloadBuilder;
use App\Support\Import\ImportRunSummaryBuilder;
use App\Support\Import\ReviewRowsQuery;
use App\Support\Import\StagedRowReviser;
use Illuminate\Auth\Access\AuthorizationException;
use Illuminate\Database\Eloquent\ModelNotFoundException;
use Illuminate\Http\Exceptions\HttpResponseException;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Generic, domain-driven import endpoints (spec 0012, extended by the
 * unified wizard flow spec 0033). One controller serves every domain;
 * {domain} resolves through the registry (unknown → 404), mirroring
 * App\Http\Controllers\Table\TableController. Every action re-authorizes via
 * the definition's authorizeImport() (deny → 403); a bound {importRun} that
 * does not belong to the actor OR whose resource does not match {domain}
 * 404s (never 403), mirroring TableFilterViewController::assertBelongsToDomain.
 *
 * upload()/show()/confirm() are BRANCH-AWARE: a "wizard" definition
 * (isWizardDefinition() — non-empty globalConfig(), supportsExtraFields(), or
 * any dedupModes() beyond the legacy create_only) drives the analyze ->
 * configure -> stage -> review -> confirm flow via ImportService::
 * startAnalyze()/confirmStaged(); every other (legacy) definition keeps the
 * original two-phase start()/confirm() flow untouched — the two never share
 * a run (status alone tells them apart).
 *
 * @see ImportService
 */
class ImportController extends BaseApiController
{
    public function __construct(
        private readonly ImportRegistry $registry,
        private readonly ImportService $service,
        private readonly ImportRunPayloadBuilder $payloadBuilder,
        private readonly ImportRunSummaryBuilder $summaryBuilder,
        private readonly ReviewRowsQuery $reviewRowsQuery,
        private readonly StagedRowReviser $rowReviser,
    ) {}

    /**
     * GET /api/imports/{domain}/template — downloadable CSV template, header =
     * the definition's declared columns, in order.
     */
    public function template(Request $request, string $domain): StreamedResponse|JsonResponse
    {
        try {
            $definition = $this->registry->resolve($domain); // 404 if unknown
            $this->authorizeImport($definition, $request->user());

            $columns = $definition->columnIds();

            return response()->streamDownload(function () use ($columns): void {
                $handle = fopen('php://output', 'w');
                fputcsv($handle, $columns);
                fclose($handle);
            }, "{$domain}-import-template.csv", ['Content-Type' => 'text/csv']);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * GET /api/imports/{domain} — paginated history of the actor's OWN runs
     * for this domain (spec 0033, AC-018).
     */
    public function index(Request $request, string $domain): JsonResponse
    {
        try {
            $definition = $this->registry->resolve($domain); // 404 if unknown
            /** @var User $actor */
            $actor = $request->user();
            $this->authorizeImport($definition, $actor);

            $page = max(1, (int) $request->query('page', 1));
            $perPage = min(self::MAX_LIMIT, max(1, (int) $request->query('per_page', 15)));

            $paginator = ImportRun::query()
                ->where('user_id', $actor->id)
                ->where('resource', $domain)
                ->latest('id')
                ->paginate($perPage, ['*'], 'page', $page);

            return $this->paginatedResponse(
                items: ImportRunResource::collection($paginator->items()),
                total: $paginator->total(),
                offset: ($page - 1) * $perPage,
                limit: $perPage,
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * POST /api/imports/{domain} — store the uploaded file and create the
     * ImportRun. A wizard definition (e.g. `leads`) starts analyzing
     * (spec 0033, AC-007); a legacy definition starts the original dry-run
     * validation (spec 0012), untouched.
     */
    public function upload(UploadImportRequest $request, string $domain): JsonResponse
    {
        try {
            $definition = $this->registry->resolve($domain); // 404 if unknown
            /** @var User $actor */
            $actor = $request->user();
            $this->authorizeImport($definition, $actor);

            $run = $this->isWizardDefinition($definition)
                ? $this->service->startAnalyze($actor, $definition, $request->file('file'))
                : $this->service->start($actor, $definition, $request->file('file'));

            return $this->created(['import_run' => new ImportRunResource($run)]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__);
        }
    }

    /**
     * GET /api/imports/{domain}/{importRun} — poll the run's status. For a
     * wizard run this is enriched (spec 0033) with the definition's mapping
     * catalogue and a LIVE-recomputed `suggested_mapping` (diffable against
     * whatever the user has since edited into `column_mapping`); for a
     * legacy run these fields are simply the AbstractImportDefinition
     * defaults (empty/null), harmless additions to the frozen spec-0012 shape.
     */
    public function show(Request $request, string $domain, ImportRun $importRun): JsonResponse
    {
        try {
            $definition = $this->registry->resolve($domain); // 404 if unknown
            $this->authorizeImport($definition, $request->user());
            $this->assertOwnedRun($importRun, $request->user(), $domain);

            return $this->ok([
                'import_run' => $this->payloadBuilder->build($definition, $importRun),
                'preview' => $importRun->preview,
            ]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['importRun' => $importRun->id]);
        }
    }

    /**
     * PUT /api/imports/{domain}/{importRun}/configure — persist the wizard's
     * mapping/global config/dedup strategy and dispatch staging (spec 0033,
     * AC-008). Valid only from `configuring` (ImportService::configure aborts
     * 422 otherwise).
     */
    public function configure(ConfigureImportRequest $request, string $domain, ImportRun $importRun): JsonResponse
    {
        try {
            $definition = $this->registry->resolve($domain); // 404 if unknown
            $this->authorizeImport($definition, $request->user());
            $this->assertOwnedRun($importRun, $request->user(), $domain);

            $data = $request->safe()->only(['column_mapping', 'global_config', 'dedup_strategy']);

            $run = $this->service->configure(
                $importRun,
                $data['column_mapping'],
                $data['global_config'] ?? [],
                $data['dedup_strategy'],
            );

            return $this->ok(['import_run' => new ImportRunResource($run)]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['importRun' => $importRun->id]);
        }
    }

    /**
     * POST /api/imports/{domain}/{importRun}/rows — SSRM page of the run's
     * staged rows (spec 0033, AC-016), valid only from `reviewing`. Sort/
     * filter/search are ALL delegated to ReviewRowsQuery's allow-list — never
     * built from raw input here.
     */
    public function rows(Request $request, string $domain, ImportRun $importRun): JsonResponse
    {
        try {
            $definition = $this->registry->resolve($domain); // 404 if unknown
            $this->authorizeImport($definition, $request->user());
            $this->assertOwnedRun($importRun, $request->user(), $domain);
            $this->assertReviewing($importRun);

            $params = $request->validate([
                'startRow' => ['sometimes', 'integer', 'min:0'],
                'endRow' => ['sometimes', 'integer', 'min:0'],
                'sortModel' => ['sometimes', 'array'],
                'filterModel' => ['sometimes', 'array'],
                'search' => ['sometimes', 'nullable', 'string', 'max:100'],
            ]);

            $fieldIds = array_map(static fn (array $field): string => $field['id'], $definition->fields());
            $result = $this->reviewRowsQuery->paginate($importRun, $fieldIds, $params);

            return $this->paginatedResponse(
                items: ImportRunRowResource::collection($result['items']),
                total: $result['total'],
                offset: $result['offset'],
                limit: $result['limit'],
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['importRun' => $importRun->id]);
        }
    }

    /**
     * PATCH /api/imports/{domain}/{importRun}/rows/{row} — inline-edit one
     * staged row (spec 0033, AC-017), valid only from `reviewing`. Re-runs
     * the SAME StagedRowBuilder pipeline (recognizers + validateRow +
     * resolveDuplicate) the original staging used, via StagedRowReviser, then
     * recomputes the run's counters from the database.
     */
    public function updateRow(UpdateImportRowRequest $request, string $domain, ImportRun $importRun, ImportRunRow $row): JsonResponse
    {
        try {
            $definition = $this->registry->resolve($domain); // 404 if unknown
            /** @var User $actor */
            $actor = $request->user();
            $this->authorizeImport($definition, $actor);
            $this->assertOwnedRun($importRun, $actor, $domain);
            $this->assertRowBelongsToRun($row, $importRun);
            $this->assertReviewing($importRun);

            $values = $request->safe()->only(['values'])['values'];

            $updated = $this->rowReviser->revise($definition, $actor, $importRun, $row, $values);
            $this->service->recomputeCounts($importRun->fresh());

            return $this->ok([
                'row' => new ImportRunRowResource($updated),
                'counts' => $this->summaryBuilder->counts($importRun->fresh()),
            ]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['importRun' => $importRun->id, 'row' => $row->id]);
        }
    }

    /**
     * GET /api/imports/{domain}/{importRun}/summary — pre-confirm recap
     * (spec 0033), valid only from `reviewing`.
     */
    public function summary(Request $request, string $domain, ImportRun $importRun): JsonResponse
    {
        try {
            $definition = $this->registry->resolve($domain); // 404 if unknown
            $this->authorizeImport($definition, $request->user());
            $this->assertOwnedRun($importRun, $request->user(), $domain);
            $this->assertReviewing($importRun);

            return $this->ok(['summary' => $this->summaryBuilder->summary($importRun)]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['importRun' => $importRun->id]);
        }
    }

    /**
     * POST /api/imports/{domain}/{importRun}/confirm — move a reviewing (or,
     * legacy, awaiting_confirmation) run to processing and dispatch the
     * commit job. Any other status → 422 (ImportService::confirm()/
     * confirmStaged()).
     */
    public function confirm(Request $request, string $domain, ImportRun $importRun): JsonResponse
    {
        try {
            $definition = $this->registry->resolve($domain); // 404 if unknown
            $this->authorizeImport($definition, $request->user());
            $this->assertOwnedRun($importRun, $request->user(), $domain);

            $run = $this->isWizardDefinition($definition)
                ? $this->service->confirmStaged($importRun)
                : $this->service->confirm($importRun);

            return $this->ok(['import_run' => new ImportRunResource($run)]);
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['importRun' => $importRun->id]);
        }
    }

    /**
     * GET /api/imports/{domain}/{importRun}/errors — downloadable CSV of every
     * rejected row (not just the preview sample); 404 when no report exists.
     */
    public function errors(Request $request, string $domain, ImportRun $importRun): StreamedResponse|JsonResponse
    {
        try {
            $definition = $this->registry->resolve($domain); // 404 if unknown
            $this->authorizeImport($definition, $request->user());
            $this->assertOwnedRun($importRun, $request->user(), $domain);
            $this->assertHasErrorReport($importRun);

            return Storage::disk('local')->download(
                $importRun->error_report_path,
                "{$domain}-import-errors-{$importRun->id}.csv",
            );
        } catch (Throwable $exception) {
            return $this->handleControllerException($exception, __FUNCTION__, ['importRun' => $importRun->id]);
        }
    }

    /**
     * A definition rides the unified wizard flow when it declares ANY
     * wizard-only capability: global configuration fields, `__extra__`
     * mapping support, or a dedup strategy beyond the legacy create-only
     * default. The 5 legacy definitions declare none of these (their
     * AbstractImportDefinition defaults), so this is false for them.
     */
    private function isWizardDefinition(ImportDefinition $definition): bool
    {
        if ($definition->globalConfig() !== []) {
            return true;
        }

        if ($definition->supportsExtraFields()) {
            return true;
        }

        return $definition->dedupModes() !== [ImportDedupMode::CreateOnly];
    }

    /**
     * @throws HttpResponseException via abort() when not `reviewing`.
     */
    private function assertReviewing(ImportRun $importRun): void
    {
        if ($importRun->status !== ImportStatus::Reviewing) {
            abort(422, 'The import is not in review.');
        }
    }

    /**
     * A {row} not belonging to the bound {importRun} 404s (never 403),
     * mirroring assertOwnedRun().
     *
     * @throws ModelNotFoundException
     */
    private function assertRowBelongsToRun(ImportRunRow $row, ImportRun $importRun): void
    {
        if ($row->import_run_id !== $importRun->id) {
            throw (new ModelNotFoundException)->setModel(ImportRunRow::class, [$row->id]);
        }
    }

    /**
     * Single enforcement point: deny → AuthorizationException → 403.
     *
     * @throws AuthorizationException
     */
    private function authorizeImport(ImportDefinition $definition, User $actor): void
    {
        if (! $definition->authorizeImport($actor)) {
            throw new AuthorizationException;
        }
    }

    /**
     * A bound {importRun} that is not owned by the actor, or whose resource
     * does not match the route {domain}, must never leak cross-user/cross-
     * domain: surfaced as 404 (not 403), identical to an unknown id.
     *
     * @throws ModelNotFoundException
     */
    private function assertOwnedRun(ImportRun $importRun, User $actor, string $domain): void
    {
        if ($importRun->user_id !== $actor->id || $importRun->resource !== $domain) {
            throw (new ModelNotFoundException)->setModel(ImportRun::class, [$importRun->id]);
        }
    }

    /**
     * @throws ModelNotFoundException when no errors report was ever written.
     */
    private function assertHasErrorReport(ImportRun $importRun): void
    {
        if ($importRun->error_report_path === null || ! Storage::disk('local')->exists($importRun->error_report_path)) {
            throw (new ModelNotFoundException)->setModel(ImportRun::class, [$importRun->id]);
        }
    }
}
