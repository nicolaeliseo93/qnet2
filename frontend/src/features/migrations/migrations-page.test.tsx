import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import MigrationsPage from '@/features/migrations/migrations-page'
import type { MigrationPreviewPage, MigrationSourceSummary } from '@/features/migrations/types'

/**
 * Spec 0013 AC-020: the Migrations page fetches the registered sources into
 * the selector, then columns + a read-only paginated preview for the chosen
 * source. The API module is mocked (same convention as
 * `company-form.test.tsx`); every assertion queries by accessible role/text,
 * never `data-testid`. The breadcrumb (router + navigation query) is stubbed
 * out entirely, matching `companies-table.test.tsx`.
 */

const fetchMigrationSourcesMock = vi.fn()
const fetchMigrationColumnsMock = vi.fn()
const fetchMigrationPreviewMock = vi.fn()
const startMigrationImportMock = vi.fn()
const fetchMigrationRunMock = vi.fn()

vi.mock('@/features/migrations/api', () => ({
  fetchMigrationSources: (...args: unknown[]) => fetchMigrationSourcesMock(...args),
  fetchMigrationColumns: (...args: unknown[]) => fetchMigrationColumnsMock(...args),
  fetchMigrationPreview: (...args: unknown[]) => fetchMigrationPreviewMock(...args),
  startMigrationImport: (...args: unknown[]) => startMigrationImportMock(...args),
  fetchMigrationRun: (...args: unknown[]) => fetchMigrationRunMock(...args),
}))

vi.mock('@/routes/breadcrumbs', () => ({
  AppBreadcrumbs: () => null,
}))

const SOURCES: MigrationSourceSummary[] = [
  { key: 'roles', label: 'Roles' },
  { key: 'users', label: 'Users' },
]

function previewPage(overrides: Partial<MigrationPreviewPage> = {}): MigrationPreviewPage {
  return {
    rows: [{ name: 'Editor' }, { name: 'Viewer' }],
    pagination: { page: 1, per_page: 20, total: 2, has_more: false },
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchMigrationSourcesMock.mockReset().mockResolvedValue(SOURCES)
  fetchMigrationColumnsMock.mockReset().mockResolvedValue([{ id: 'name', label: 'Name', type: 'string' }])
  fetchMigrationPreviewMock.mockReset().mockResolvedValue(previewPage())
  startMigrationImportMock.mockReset()
  fetchMigrationRunMock.mockReset()
})

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

describe('MigrationsPage', () => {
  it('populates the source selector and renders the read-only preview on selection', async () => {
    render(<MigrationsPage />, { wrapper: wrapper() })

    await waitFor(() => expect(fetchMigrationSourcesMock).toHaveBeenCalled())

    const select = await screen.findByRole('combobox', { name: 'Source' })
    fireEvent.click(select)
    fireEvent.click(await screen.findByRole('option', { name: 'Roles' }))

    await waitFor(() => expect(fetchMigrationColumnsMock).toHaveBeenCalledWith('roles'))
    await waitFor(() =>
      expect(fetchMigrationPreviewMock).toHaveBeenCalledWith('roles', 1, 20),
    )

    expect(await screen.findByRole('columnheader', { name: 'Name' })).toBeInTheDocument()
    expect(await screen.findByText('Editor')).toBeInTheDocument()
    expect(screen.getByText('Viewer')).toBeInTheDocument()

    // No edit/delete/create control ever appears on the read-only preview.
    expect(screen.queryByRole('button', { name: /edit/i })).not.toBeInTheDocument()
    expect(screen.queryByRole('button', { name: /delete/i })).not.toBeInTheDocument()
  })

  it('paginates the preview, disabling Next when there is no further page (has_more=false)', async () => {
    fetchMigrationPreviewMock.mockResolvedValue(
      previewPage({ pagination: { page: 1, per_page: 20, total: 2, has_more: false } }),
    )

    render(<MigrationsPage />, { wrapper: wrapper() })

    const select = await screen.findByRole('combobox', { name: 'Source' })
    fireEvent.click(select)
    fireEvent.click(await screen.findByRole('option', { name: 'Roles' }))

    await screen.findByText('Editor')

    expect(screen.getByRole('button', { name: /previous/i })).toBeDisabled()
    expect(screen.getByRole('button', { name: /next/i })).toBeDisabled()
  })

  it('advances to the next page when has_more is true', async () => {
    fetchMigrationPreviewMock.mockResolvedValueOnce(
      previewPage({ pagination: { page: 1, per_page: 20, total: null, has_more: true } }),
    )

    render(<MigrationsPage />, { wrapper: wrapper() })

    const select = await screen.findByRole('combobox', { name: 'Source' })
    fireEvent.click(select)
    fireEvent.click(await screen.findByRole('option', { name: 'Roles' }))

    await screen.findByText('Editor')
    expect(screen.getByRole('button', { name: /next/i })).toBeEnabled()

    fetchMigrationPreviewMock.mockResolvedValueOnce(
      previewPage({ rows: [{ name: 'Auditor' }], pagination: { page: 2, per_page: 20, total: null, has_more: false } }),
    )
    fireEvent.click(screen.getByRole('button', { name: /next/i }))

    await waitFor(() => expect(fetchMigrationPreviewMock).toHaveBeenCalledWith('roles', 2, 20))
    expect(await screen.findByText('Auditor')).toBeInTheDocument()
  })
})
