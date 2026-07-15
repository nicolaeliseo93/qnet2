import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
// The base `importWizard` bundle (owned by F1) must register before this
// lane's extension deep-merges on top of it (see
// `import-wizard-i18n-summary.ts`'s module doc comment) — mirrors the real
// load order (`import-wizard.tsx` imports the base bundle before mounting
// this step).
import '@/features/imports/wizard/i18n'
import { ImportStepSummary } from '@/features/imports/wizard/import-step-summary'
import type { ImportRunDetail, ImportRunSummaryReport } from '@/features/imports/wizard/types'

/**
 * Spec 0033 AC-024: while `reviewing`, the summary step fetches and renders
 * the pre-confirm report (totals, selected global values, mapped columns,
 * extra fields, warnings) and confirming calls `onConfirm`; once the run
 * leaves `reviewing` (processing/completed/failed) it shows the progress/
 * outcome view instead.
 */

const getImportRunSummaryMock = vi.fn()
vi.mock('@/features/imports/wizard/api', () => ({
  getImportRunSummary: (...args: unknown[]) => getImportRunSummaryMock(...args),
}))

function baseRun(overrides: Partial<ImportRunDetail> = {}): ImportRunDetail {
  return {
    id: 1,
    resource: 'leads',
    status: 'reviewing',
    original_filename: 'leads.csv',
    total_rows: 10,
    valid_rows: 7,
    warning_rows: 2,
    error_rows: 1,
    duplicate_rows: 1,
    imported_rows: null,
    modified_rows: 1,
    has_error_report: false,
    created_at: '2026-07-15T00:00:00Z',
    error_count: 0,
    detected_columns: null,
    column_mapping: null,
    global_config: null,
    dedup_strategy: null,
    suggested_mapping: null,
    fields: [{ id: 'full_name', label: 'Full name (contact)', required: true, group: 'contact', type: 'string' }],
    global_fields: [
      { id: 'campaign_id', label: 'Campaign', required: true, for_select_resource: 'campaigns', default: null },
    ],
    dedup_modes: [],
    ...overrides,
  }
}

function baseSummary(overrides: Partial<ImportRunSummaryReport> = {}): ImportRunSummaryReport {
  return {
    total_rows: 10,
    valid_rows: 7,
    warning_rows: 2,
    error_rows: 1,
    duplicate_rows: 1,
    modified_rows: 1,
    mapped_fields: [{ column: 'Full name', field: 'full_name' }],
    extra_fields: ['Loyalty tier'],
    global_config: { campaign_id: 9 },
    dedup_strategy: 'create_new',
    warnings: ['Row 4: ambiguous city match'],
    ...overrides,
  }
}

function renderStep({
  run = baseRun(),
  onConfirm = vi.fn(),
  isConfirming = false,
  confirmError = null as string | null,
} = {}) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <ImportStepSummary
        domain="leads"
        run={run}
        onConfirm={onConfirm}
        isConfirming={isConfirming}
        confirmError={confirmError}
      />
    </QueryClientProvider>,
  )
  return { onConfirm }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  getImportRunSummaryMock.mockReset()
})

describe('ImportStepSummary', () => {
  it('renders totals, selected global values, mapped columns, extra fields and warnings', async () => {
    getImportRunSummaryMock.mockResolvedValue(baseSummary())

    renderStep()

    expect(await screen.findByText('Row 4: ambiguous city match')).toBeInTheDocument()
    expect(screen.getByText('Summary')).toBeInTheDocument()
    // Totals.
    expect(screen.getByText('Total rows')).toBeInTheDocument()
    expect(screen.getAllByText('7')).not.toHaveLength(0)
    // Selected global config, resolved through the run's field catalog label.
    expect(screen.getByText('Campaign')).toBeInTheDocument()
    expect(screen.getByText('9')).toBeInTheDocument()
    // Mapped columns: the file column name paired with the resolved field label (not the raw id).
    expect(screen.getByText('Full name')).toBeInTheDocument()
    expect(screen.getByText('Full name (contact)')).toBeInTheDocument()
    // Extra fields.
    expect(screen.getByText('Loyalty tier')).toBeInTheDocument()

    expect(getImportRunSummaryMock).toHaveBeenCalledWith('leads', 1)
  })

  it('shows the empty-config message when no global value is set', async () => {
    getImportRunSummaryMock.mockResolvedValue(baseSummary({ global_config: {} }))

    renderStep()

    expect(await screen.findByText('No global values configured.')).toBeInTheDocument()
  })

  it('calls onConfirm when the confirm action is clicked', async () => {
    getImportRunSummaryMock.mockResolvedValue(baseSummary())
    const { onConfirm } = renderStep()

    fireEvent.click(await screen.findByRole('button', { name: 'Confirm and import' }))

    expect(onConfirm).toHaveBeenCalledTimes(1)
  })

  it('disables the confirm action while confirming', async () => {
    getImportRunSummaryMock.mockResolvedValue(baseSummary())
    renderStep({ isConfirming: true })

    expect(await screen.findByRole('button', { name: 'Confirming…' })).toBeDisabled()
  })

  it('shows the confirm error as an alert', async () => {
    getImportRunSummaryMock.mockResolvedValue(baseSummary())
    renderStep({ confirmError: 'Something went wrong. Please try again.' })

    expect(await screen.findByRole('alert')).toHaveTextContent('Something went wrong. Please try again.')
  })

  it('shows a loading state while the summary is pending', () => {
    getImportRunSummaryMock.mockReturnValue(new Promise(() => {}))

    renderStep()

    expect(screen.getByRole('status')).toHaveTextContent('Loading the summary…')
  })

  it('shows a retry action when loading the summary fails', async () => {
    getImportRunSummaryMock.mockRejectedValueOnce(new Error('network')).mockResolvedValue(baseSummary())

    renderStep()

    expect(await screen.findByRole('alert')).toHaveTextContent('Unable to load the summary. Please try again.')
    fireEvent.click(screen.getByRole('button', { name: 'Retry' }))

    await waitFor(() => expect(screen.getByText('Summary')).toBeInTheDocument())
  })

  it('shows the progress view once the run leaves reviewing (processing)', () => {
    renderStep({ run: baseRun({ status: 'processing' }) })

    expect(screen.getByRole('status')).toHaveTextContent('Import in progress…')
    expect(getImportRunSummaryMock).not.toHaveBeenCalled()
  })

  it('shows the outcome once completed, including the imported/error counts', () => {
    renderStep({ run: baseRun({ status: 'completed', imported_rows: 8, error_count: 1 }) })

    expect(screen.getByText('8 leads imported, 1 errors.')).toBeInTheDocument()
    expect(screen.getByText('A notification was sent once the import completed.')).toBeInTheDocument()
  })

  it('shows the failure notice once failed', () => {
    renderStep({ run: baseRun({ status: 'failed' }) })

    expect(screen.getByRole('alert')).toHaveTextContent('The import failed. Please check the file and try again.')
  })
})
