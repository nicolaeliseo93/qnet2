import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import CustomFieldsPage from '@/pages/custom-fields-page'

/**
 * AC-025 — permission gating of the Custom Fields admin page. The generic
 * `<TableView>` (AG Grid + SSRM) and the app chrome (`PageHeader`) are
 * framework pieces outside this microtask's ownership: they are stubbed so
 * the suite stays focused on what THIS adapter is responsible for — wiring
 * `<Can>` around the table and mounting `<TableView domain="custom-fields">`
 * with the right domain (mirrors `AttributesPage`'s suite).
 */
const canMock = vi.fn<(permission: string) => boolean>()

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({
    can: (permission: string) => canMock(permission),
    hasRole: () => false,
    roles: [],
    isLoading: false,
  }),
}))

vi.mock('@/components/page-header', () => ({
  PageHeader: ({ actions }: { actions?: ReactNode }) => <div>{actions}</div>,
}))

// This suite exercises the default modal behaviour; force the resolved open
// mode so it never depends on an AuthProvider (spec 0042).
vi.mock('@/features/modules/use-module-open-mode', () => ({
  useModuleOpenMode: () => 'modal',
}))

vi.mock('@/features/table/table-view', () => ({
  TableView: forwardRef<{ refresh: () => void }, { domain: string }>(
    function TableViewStub({ domain }, ref) {
      useImperativeHandle(ref, () => ({ refresh: () => {} }))
      return <div role="region" aria-label={`table-${domain}`} />
    },
  ),
}))

function renderPage() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter>
        <CustomFieldsPage />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  canMock.mockReset()
})

describe('CustomFieldsPage — permission gating (AC-025)', () => {
  it('shows the forbidden fallback and does not mount the table without viewAny', () => {
    canMock.mockReturnValue(false)

    renderPage()

    expect(screen.getByText("You don't have permission to view custom fields.")).toBeInTheDocument()
    expect(screen.queryByRole('region', { name: 'table-custom-fields' })).not.toBeInTheDocument()
  })

  it('mounts <TableView domain="custom-fields"> with viewAny', () => {
    canMock.mockImplementation((permission) => permission === 'custom-fields.viewAny')

    renderPage()

    expect(screen.getByRole('region', { name: 'table-custom-fields' })).toBeInTheDocument()
    expect(
      screen.queryByText("You don't have permission to view custom fields."),
    ).not.toBeInTheDocument()
  })
})
