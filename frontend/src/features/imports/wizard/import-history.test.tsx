import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ImportHistory } from '@/features/imports/wizard/import-history'
import type { ImportRunHistoryPage, ImportRunSummary } from '@/features/imports/wizard/types'

/**
 * Spec 0033 AC-018 (F5 lane): the history list renders the actor's own
 * paginated runs, handles the empty state, and each row links back to the
 * wizard (`/{domain}/import?runId=...`) so a run can be reopened/resumed.
 */

const getImportRunHistoryMock = vi.fn<(domain: string, page: number, perPage: number) => Promise<ImportRunHistoryPage>>()

vi.mock('@/features/imports/wizard/api', () => ({
  getImportRunHistory: (domain: string, page: number, perPage: number) =>
    getImportRunHistoryMock(domain, page, perPage),
}))

function run(overrides: Partial<ImportRunSummary> = {}): ImportRunSummary {
  return {
    id: 1,
    resource: 'leads',
    status: 'completed',
    original_filename: 'leads-batch-1.csv',
    total_rows: 42,
    valid_rows: 40,
    warning_rows: 2,
    error_rows: 1,
    duplicate_rows: 0,
    imported_rows: 39,
    modified_rows: 0,
    has_error_report: true,
    created_at: '2026-07-10T09:30:00Z',
    ...overrides,
  }
}

function renderHistory() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <ImportHistory domain="leads" />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  getImportRunHistoryMock.mockReset()
})

describe('ImportHistory (AC-018)', () => {
  it('renders the paginated list of the actor own runs', async () => {
    getImportRunHistoryMock.mockResolvedValue({
      items: [run()],
      pagination: { total: 1, offset: 0, limit: 10, total_pages: 1 },
    })

    renderHistory()

    expect(await screen.findByText('leads-batch-1.csv')).toBeInTheDocument()
    expect(screen.getByText('42')).toBeInTheDocument()
    expect(screen.getByText('39')).toBeInTheDocument()
    expect(screen.getByText('1')).toBeInTheDocument()
    expect(screen.getByText('Completed')).toBeInTheDocument()
    await waitFor(() => expect(getImportRunHistoryMock).toHaveBeenCalledWith('leads', 1, 10))
  })

  it('shows the empty state when the actor has no runs', async () => {
    getImportRunHistoryMock.mockResolvedValue({
      items: [],
      pagination: { total: 0, offset: 0, limit: 10, total_pages: 0 },
    })

    renderHistory()

    expect(await screen.findByText('No import runs yet.')).toBeInTheDocument()
  })

  it('shows the error state and retries on demand', async () => {
    getImportRunHistoryMock.mockRejectedValue(new Error('network error'))

    renderHistory()

    expect(await screen.findByRole('alert')).toHaveTextContent(
      'Unable to load the import history. Please try again.',
    )

    getImportRunHistoryMock.mockResolvedValue({
      items: [run()],
      pagination: { total: 1, offset: 0, limit: 10, total_pages: 1 },
    })
    screen.getByRole('button', { name: 'Retry' }).click()

    expect(await screen.findByText('leads-batch-1.csv')).toBeInTheDocument()
  })

  it('links each run back to the wizard to resume/inspect it', async () => {
    getImportRunHistoryMock.mockResolvedValue({
      items: [run({ id: 7 })],
      pagination: { total: 1, offset: 0, limit: 10, total_pages: 1 },
    })

    renderHistory()

    const link = await screen.findByRole('link', { name: 'View' })
    expect(link).toHaveAttribute('href', '/leads/import?runId=7')
  })
})
