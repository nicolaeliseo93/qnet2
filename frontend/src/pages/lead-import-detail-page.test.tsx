import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { render, screen } from '@testing-library/react'
import { MemoryRouter, Route, Routes } from 'react-router-dom'
import i18n from '@/i18n'
import LeadImportDetailPage from '@/pages/lead-import-detail-page'
import type { ImportRunDetail } from '@/features/imports/wizard/types'

/**
 * Spec 0034 AC-013: the dedicated lead-import detail page — fetches the run
 * fresh for the `:runId` param, renders the (separately covered)
 * presentational view, shows a "Resume import" action only for a resumable
 * run, never a blank page on a failed fetch, a not-found page on an invalid
 * id, and is gated behind `import-runs.view`. The data hook and the
 * presentational view are stubbed: what is under test is the page wiring.
 */

const useLeadImportDetailMock = vi.fn()
vi.mock('@/features/imports/use-lead-import-detail', () => ({
  useLeadImportDetail: (runId: number | null) => useLeadImportDetailMock(runId),
}))

vi.mock('@/features/imports/lead-import-detail', () => ({
  LeadImportDetailView: ({ run }: { run: ImportRunDetail }) => <h2>{run.original_filename}</h2>,
}))

vi.mock('@/components/page-header', () => ({
  PageHeader: ({ actions }: { actions?: ReactNode }) => <div>{actions}</div>,
}))

const canMock = vi.fn<(permission: string) => boolean>()
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({ can: canMock, hasRole: () => false, roles: [], isLoading: false }),
}))

function run(overrides: Partial<ImportRunDetail> = {}): ImportRunDetail {
  return {
    id: 12,
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

function mockResult(overrides: Partial<ReturnType<typeof baseResult>> = {}) {
  return { ...baseResult(), ...overrides }
}

function baseResult() {
  return {
    run: run(),
    summary: undefined,
    isLoading: false,
    isError: false,
    refetch: vi.fn(),
    summaryIsLoading: false,
    summaryIsError: false,
    isResumable: false,
  }
}

function renderAt(path: string) {
  return render(
    <MemoryRouter initialEntries={[path]}>
      <Routes>
        <Route path="/imports/:runId" element={<LeadImportDetailPage />} />
      </Routes>
    </MemoryRouter>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  canMock.mockReset()
  canMock.mockReturnValue(true)
  useLeadImportDetailMock.mockReset()
  useLeadImportDetailMock.mockReturnValue(mockResult())
})

describe('LeadImportDetailPage', () => {
  it('renders the not-found page for a non-numeric runId, disabling the fetch', () => {
    renderAt('/imports/abc')

    expect(screen.getByText('Page not found')).toBeInTheDocument()
    expect(useLeadImportDetailMock).toHaveBeenCalledWith(null)
  })

  it('shows the forbidden fallback without import-runs.view', () => {
    canMock.mockReturnValue(false)

    renderAt('/imports/12')

    expect(screen.getByText("You don't have permission to view imports.")).toBeInTheDocument()
    expect(screen.queryByText('leads.csv')).not.toBeInTheDocument()
  })

  it('fetches the run of the :runId param and renders its detail', () => {
    renderAt('/imports/12')

    expect(useLeadImportDetailMock).toHaveBeenCalledWith(12)
    expect(screen.getByText('leads.csv')).toBeInTheDocument()
  })

  it('shows the error state (never a blank page) when the fetch fails', () => {
    useLeadImportDetailMock.mockReturnValue(mockResult({ isError: true, run: undefined }))

    renderAt('/imports/12')

    expect(screen.getByText('Unable to load the import run. Please try again.')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Retry' })).toBeInTheDocument()
  })

  it('shows "Resume import" only when the run is resumable', () => {
    useLeadImportDetailMock.mockReturnValue(mockResult({ isResumable: true }))

    renderAt('/imports/12')

    expect(screen.getByRole('button', { name: 'Resume import' })).toBeInTheDocument()
  })

  it('does not show "Resume import" for a concluded run', () => {
    renderAt('/imports/12')

    expect(screen.queryByRole('button', { name: 'Resume import' })).not.toBeInTheDocument()
  })
})
