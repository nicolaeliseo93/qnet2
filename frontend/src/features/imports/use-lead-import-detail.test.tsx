import { beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { renderHook, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { useLeadImportDetail } from '@/features/imports/use-lead-import-detail'
import type { ImportRunDetail, ImportRunSummaryReport } from '@/features/imports/wizard/types'

/**
 * Spec 0034 AC-013: the detail page's data hook fetches the run fresh
 * (`GET /imports/leads/{run}`, reused 1:1 from the wizard) and, only once the
 * run is known to be in a state the backend actually serves it for
 * (`reviewing`/`completed`/`failed`), its pre-confirm `summary`.
 */

const getImportWizardRunMock = vi.fn()
const getImportRunSummaryMock = vi.fn()

vi.mock('@/features/imports/wizard/api', () => ({
  getImportWizardRun: (...args: unknown[]) => getImportWizardRunMock(...args),
  getImportRunSummary: (...args: unknown[]) => getImportRunSummaryMock(...args),
}))

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function run(overrides: Partial<ImportRunDetail> = {}): ImportRunDetail {
  return {
    id: 1,
    resource: 'leads',
    status: 'completed',
    original_filename: 'leads.csv',
    total_rows: 3,
    valid_rows: 2,
    warning_rows: 1,
    error_rows: 0,
    duplicate_rows: 0,
    imported_rows: 2,
    modified_rows: 0,
    has_error_report: false,
    created_at: '2026-07-15T00:00:00Z',
    error_count: 0,
    detected_columns: null,
    column_mapping: null,
    global_config: null,
    dedup_strategy: null,
    suggested_mapping: null,
    fields: [],
    global_fields: [],
    dedup_modes: [],
    ...overrides,
  }
}

function summary(): ImportRunSummaryReport {
  return {
    total_rows: 3,
    valid_rows: 2,
    warning_rows: 1,
    error_rows: 0,
    duplicate_rows: 0,
    modified_rows: 0,
    mapped_fields: [],
    extra_fields: [],
    global_config: {},
    dedup_strategy: null,
    warnings: [],
    conversion_readiness: {
      operational_site_set: true,
      campaign_derives_product_line: true,
      creatable_rows: 2,
      rows_without_operator: 0,
    },
  }
}

beforeEach(() => {
  vi.clearAllMocks()
})

describe('useLeadImportDetail', () => {
  it('fetches nothing when runId is null', () => {
    const { result } = renderHook(() => useLeadImportDetail(null), { wrapper: wrapper() })

    expect(result.current.run).toBeUndefined()
    expect(result.current.isLoading).toBe(false)
    expect(getImportWizardRunMock).not.toHaveBeenCalled()
  })

  it('fetches the run, then the summary for a concluded run, and reports it as not resumable', async () => {
    getImportWizardRunMock.mockResolvedValue(run({ status: 'completed' }))
    getImportRunSummaryMock.mockResolvedValue(summary())

    const { result } = renderHook(() => useLeadImportDetail(1), { wrapper: wrapper() })

    await waitFor(() => expect(result.current.run).toBeDefined())
    expect(getImportWizardRunMock).toHaveBeenCalledWith('leads', 1)

    await waitFor(() => expect(result.current.summary).toBeDefined())
    expect(getImportRunSummaryMock).toHaveBeenCalledWith('leads', 1)
    expect(result.current.isResumable).toBe(false)
  })

  it('skips the summary fetch and reports resumable=true for an in-progress run', async () => {
    getImportWizardRunMock.mockResolvedValue(run({ status: 'staging' }))

    const { result } = renderHook(() => useLeadImportDetail(1), { wrapper: wrapper() })

    await waitFor(() => expect(result.current.run).toBeDefined())
    expect(result.current.isResumable).toBe(true)
    expect(getImportRunSummaryMock).not.toHaveBeenCalled()
  })

  it('surfaces the run fetch error via isError, with a working refetch', async () => {
    getImportWizardRunMock.mockRejectedValueOnce(new Error('boom')).mockResolvedValueOnce(run())

    const { result } = renderHook(() => useLeadImportDetail(1), { wrapper: wrapper() })

    await waitFor(() => expect(result.current.isError).toBe(true))

    result.current.refetch()

    await waitFor(() => expect(result.current.run).toBeDefined())
  })
})
