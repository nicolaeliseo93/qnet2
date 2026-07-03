import { useCallback, useState } from 'react'
import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import type { TFunction } from 'i18next'
import axios from 'axios'
import { confirmImport, getImportRun, uploadImport } from '@/features/imports/api'
import { importKeys } from '@/features/imports/query-keys'
import type { ImportRunDetail, ImportStatus } from '@/features/imports/types'

/** Interval (ms) between polls while the run is validating or processing. */
const POLL_INTERVAL_MS = 1500

/** Statuses that still require polling; any other status is a pause/terminal point. */
const POLLING_STATUSES: ReadonlySet<ImportStatus> = new Set(['validating', 'processing'])

/**
 * Maps a failed request onto a localized message (status-only, mirroring the
 * codebase convention in `companies-table.tsx`/`form-errors.ts`): no server
 * message parsing, just the well-known statuses this contract can return.
 */
function resolveImportErrorMessage(error: unknown, t: TFunction): string {
  const status = axios.isAxiosError(error) ? error.response?.status : undefined
  if (status === 403) return t('imports.errors.forbidden')
  if (status === 422) return t('imports.errors.validation')
  if (status === 409) return t('imports.errors.invalidState')
  return t('imports.errors.generic')
}

interface UseImportArgs {
  /** Resource key selecting the backend `ImportDefinition` (route segment). */
  domain: string
}

/**
 * Orchestrates the generic two-phase import flow: upload → poll while
 * validating → pause at `awaiting_confirmation` (preview) → confirm → poll
 * while processing → terminal (`completed`/`failed`). All business logic
 * lives here; `ImportDialog` and friends only render from the returned state.
 */
export function useImport({ domain }: UseImportArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [runId, setRunId] = useState<number | null>(null)

  const uploadMutation = useMutation({
    mutationFn: (file: File) => uploadImport(domain, file),
    onSuccess: (importRun) => {
      setRunId(importRun.id)
      queryClient.setQueryData<ImportRunDetail>(importKeys.run(domain, importRun.id), {
        import_run: importRun,
        preview: null,
      })
    },
  })

  const runQuery = useQuery({
    queryKey: runId != null ? importKeys.run(domain, runId) : importKeys.domain(domain),
    queryFn: () => getImportRun(domain, runId as number),
    enabled: runId != null,
    // Re-evaluated on every query update (including the `setQueryData` calls
    // below), so confirming resumes polling without a separate effect.
    refetchInterval: (query) => {
      const status = query.state.data?.import_run.status
      return status && POLLING_STATUSES.has(status) ? POLL_INTERVAL_MS : false
    },
  })

  const confirmMutation = useMutation({
    mutationFn: () => confirmImport(domain, runId as number),
    onSuccess: (importRun) => {
      queryClient.setQueryData<ImportRunDetail>(
        importKeys.run(domain, importRun.id),
        (previous) => ({ import_run: importRun, preview: previous?.preview ?? null }),
      )
    },
  })

  /** Clears the run and both mutation states, e.g. when the dialog closes. */
  const reset = useCallback(() => {
    setRunId(null)
    uploadMutation.reset()
    confirmMutation.reset()
  }, [uploadMutation, confirmMutation])

  return {
    runDetail: runId != null ? runQuery.data : undefined,
    upload: uploadMutation.mutate,
    isUploading: uploadMutation.isPending,
    uploadError: uploadMutation.isError
      ? resolveImportErrorMessage(uploadMutation.error, t)
      : null,
    confirm: confirmMutation.mutate,
    isConfirming: confirmMutation.isPending,
    confirmError: confirmMutation.isError
      ? resolveImportErrorMessage(confirmMutation.error, t)
      : null,
    reset,
  }
}
