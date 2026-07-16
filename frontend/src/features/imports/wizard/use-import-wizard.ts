import { useCallback, useMemo, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import {
  analyzeImport,
  confirmImportRun,
  configureImportRun,
  createMappingTemplate,
  getImportWizardRun,
} from '@/features/imports/wizard/api'
import { importWizardKeys } from '@/features/imports/wizard/query-keys'
import { resolveImportWizardErrorMessage } from '@/features/imports/wizard/resolve-error-message'
import type {
  ConfigureImportPayload,
  ImportRunDetail,
  ImportRunStatus,
} from '@/features/imports/wizard/types'

/** Interval (ms) between polls while the run is analyzing/staging/processing. */
const POLL_INTERVAL_MS = 1500

/** Statuses that still require polling; any other status is a pause/terminal point. */
const POLLING_STATUSES: ReadonlySet<ImportRunStatus> = new Set(['analyzing', 'staging', 'processing'])

/** Index into the 5-step stepper (upload/config/mapping/review/summary). */
export type WizardStepIndex = 0 | 1 | 2 | 3 | 4

function isWizardStepIndex(value: number): value is WizardStepIndex {
  return value === 0 || value === 1 || value === 2 || value === 3 || value === 4
}

/**
 * Derives the step to render from the run's server-authoritative `status`
 * (spec 0033 AC-025: the wizard resumes from `GET run`, never from client
 * state alone), falling back to `localStep` only for the two pairs of steps
 * that share one backend status: config/mapping both live under
 * `configuring` (nothing is persisted until the mapping step's submit), and
 * review/summary both live under `reviewing` (summary is a read-only report
 * over the same staged rows, confirmed only from there).
 */
function deriveStepIndex(status: ImportRunStatus | undefined, localStep: WizardStepIndex): WizardStepIndex {
  switch (status) {
    case 'analyzing':
      return 0
    case 'configuring':
      if (localStep === 0) return 0
      return localStep === 2 ? 2 : 1
    case 'staging':
      return 3
    case 'reviewing':
      return localStep === 4 ? 4 : 3
    case 'processing':
    case 'completed':
    case 'failed':
      return 4
    default:
      return 0
  }
}

/** Reads a global-config value from the run as a relation id, or its field default. */
function resolveGlobalConfigValue(
  run: ImportRunDetail,
  fieldId: string,
  fieldDefault: string | number | null,
): number | null {
  const stored = run.global_config?.[fieldId]
  if (typeof stored === 'number') return stored
  return typeof fieldDefault === 'number' ? fieldDefault : null
}

interface UseImportWizardArgs {
  /** Resource key selecting the backend `ImportDefinition` (route segment). */
  domain: string
  /** Run id resumed from the page's `?runId=` query param, or `null` for a fresh wizard. */
  initialRunId: number | null
  /** Called once a fresh upload creates a run, so the caller can persist it in the URL. */
  onRunCreated?: (runId: number) => void
}

/**
 * Orchestrates the advanced import wizard's state machine (spec 0033): the
 * run's `status` (server-authoritative) drives which step renders; upload,
 * configure and confirm are mutations that move the run forward; polling
 * covers the two async backend phases (analyzing, staging, processing).
 * Config/mapping drafts are held locally until the mapping step's single
 * submit persists both together (the backend only exposes one `configure`
 * endpoint for column_mapping + global_config + dedup_strategy combined).
 */
export function useImportWizard({ domain, initialRunId, onRunCreated }: UseImportWizardArgs) {
  const { t } = useTranslation('importWizard')
  const queryClient = useQueryClient()
  const [runId, setRunId] = useState<number | null>(initialRunId)
  // A resumed run (initialRunId set) skips the "review analysis, then
  // continue" gate of a fresh upload — see `deriveStepIndex`'s `analyzing`
  // sibling handling in `ImportStepUpload`.
  const [localStep, setLocalStep] = useState<WizardStepIndex>(initialRunId != null ? 1 : 0)
  const [configDraft, setConfigDraft] = useState<Record<string, number | null> | null>(null)

  const runQuery = useQuery({
    queryKey: runId != null ? importWizardKeys.run(domain, runId) : importWizardKeys.domain(domain),
    queryFn: () => getImportWizardRun(domain, runId as number),
    enabled: runId != null,
    // Re-evaluated on every query update, so confirming resumes polling
    // without a separate effect.
    refetchInterval: (query) => {
      const status = query.state.data?.status
      return status && POLLING_STATUSES.has(status) ? POLL_INTERVAL_MS : false
    },
  })

  const run = runId != null ? (runQuery.data ?? null) : null

  const uploadMutation = useMutation({
    mutationFn: (file: File) => analyzeImport(domain, file),
    onSuccess: (createdRun) => {
      setRunId(createdRun.id)
      setLocalStep(0)
      setConfigDraft(null)
      onRunCreated?.(createdRun.id)
    },
  })

  const configureMutation = useMutation({
    mutationFn: (payload: ConfigureImportPayload) => configureImportRun(domain, runId as number, payload),
    onSuccess: () => {
      setLocalStep(3)
      void queryClient.invalidateQueries({ queryKey: importWizardKeys.run(domain, runId as number) })
    },
  })

  const confirmMutation = useMutation({
    mutationFn: () => confirmImportRun(domain, runId as number),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: importWizardKeys.run(domain, runId as number) })
    },
  })

  const initialConfigValues = useMemo<Record<string, number | null>>(() => {
    if (!run) return {}
    const values: Record<string, number | null> = {}
    for (const field of run.global_fields) {
      values[field.id] = resolveGlobalConfigValue(run, field.id, field.default)
    }
    return values
  }, [run])

  const configValues = configDraft ?? initialConfigValues

  const initialMapping = useMemo<Record<string, string>>(
    () => ({ ...(run?.suggested_mapping ?? {}), ...(run?.column_mapping ?? {}) }),
    [run],
  )

  const dedupStrategy = run?.dedup_strategy ?? run?.dedup_modes[0] ?? null

  const currentStep = deriveStepIndex(run?.status, localStep)

  const advanceFromUpload = useCallback(() => setLocalStep(1), [])

  const submitConfig = useCallback((values: Record<string, number | null>) => {
    setConfigDraft(values)
    setLocalStep(2)
  }, [])

  const submitMapping = useCallback(
    (mapping: Record<string, string>, strategy: string, saveAsTemplate?: { name: string }) => {
      configureMutation.mutate(
        { column_mapping: mapping, global_config: configValues, dedup_strategy: strategy },
        {
          // Side-effect POST run AFTER configure already succeeded (spec 0035
          // constraint: never blocks/rolls back the wizard's advance, which
          // `configureMutation`'s own onSuccess above already committed).
          onSuccess: () => {
            if (!saveAsTemplate || runId == null) return
            createMappingTemplate(domain, { name: saveAsTemplate.name, import_run_id: runId })
              .then(() => {
                toast.success(t('mapping.templates.saveSuccess'))
                void queryClient.invalidateQueries({ queryKey: importWizardKeys.mappingTemplates(domain) })
              })
              .catch(() => toast.error(t('mapping.templates.saveError')))
          },
        },
      )
    },
    [configValues, configureMutation, domain, runId, queryClient, t],
  )

  const advanceFromReview = useCallback(() => setLocalStep(4), [])

  const goToStep = useCallback(
    (index: number) => {
      if (!isWizardStepIndex(index)) return
      if (run?.status === 'configuring' && (index === 1 || index === 2)) {
        setLocalStep(index)
      } else if (run?.status === 'reviewing' && (index === 3 || index === 4)) {
        setLocalStep(index)
      }
    },
    [run?.status],
  )

  return {
    run,
    currentStep,
    goToStep,
    isRunLoading: runId != null && runQuery.isLoading,
    isRunError: runQuery.isError,
    refetchRun: runQuery.refetch,

    upload: uploadMutation.mutate,
    isUploading: uploadMutation.isPending,
    uploadError: uploadMutation.isError ? resolveImportWizardErrorMessage(uploadMutation.error, t) : null,
    advanceFromUpload,

    configValues,
    submitConfig,

    mappingValues: initialMapping,
    dedupStrategy,
    submitMapping,
    isConfiguring: configureMutation.isPending,
    configureError: configureMutation.isError
      ? resolveImportWizardErrorMessage(configureMutation.error, t)
      : null,

    advanceFromReview,

    confirm: confirmMutation.mutate,
    isConfirming: confirmMutation.isPending,
    confirmError: confirmMutation.isError ? resolveImportWizardErrorMessage(confirmMutation.error, t) : null,
  }
}
