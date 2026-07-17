import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import ProductCategoriesPage from '@/pages/product-categories-page'

/**
 * Permission gating of the Product Categories page, now backed by the
 * generic AG Grid SSRM table instead of the removed tree view (mirrors
 * `ProductsPage`'s suite).
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
        <ProductCategoriesPage />
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

describe('ProductCategoriesPage — permission gating', () => {
  it('shows the forbidden fallback and does not mount the grid without viewAny', () => {
    canMock.mockReturnValue(false)

    renderPage()

    expect(
      screen.getByText("You don't have permission to view product categories."),
    ).toBeInTheDocument()
    expect(screen.queryByRole('region', { name: 'table-product-categories' })).not.toBeInTheDocument()
  })

  it('mounts <TableView domain="product-categories"> with viewAny', () => {
    canMock.mockImplementation((permission) => permission === 'product-categories.viewAny')

    renderPage()

    expect(screen.getByRole('region', { name: 'table-product-categories' })).toBeInTheDocument()
  })
})
