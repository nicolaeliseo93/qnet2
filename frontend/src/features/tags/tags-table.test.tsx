import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { forwardRef, useImperativeHandle, type ReactNode } from 'react'
import { render, screen } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import TagsPage from '@/pages/tags-page'

/**
 * Permission gating of the Tags page (spec 0019, mirrors
 * `referent-types-table.test.tsx`). The generic `<TableView>` (AG Grid +
 * SSRM) and the app chrome (`PageHeader`) are framework pieces outside this
 * module's ownership: they are stubbed so the suite stays focused on what
 * THIS adapter is responsible for — wiring `<Can>` around the table and
 * mounting `<TableView domain="tags">` with the right domain.
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
      <TagsPage />
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  canMock.mockReset()
})

describe('TagsPage — permission gating', () => {
  it('shows the forbidden fallback and does not mount the table without viewAny', () => {
    canMock.mockReturnValue(false)

    renderPage()

    expect(screen.getByText("You don't have permission to view tags.")).toBeInTheDocument()
    expect(screen.queryByRole('region', { name: 'table-tags' })).not.toBeInTheDocument()
  })

  it('mounts <TableView domain="tags"> with viewAny', () => {
    canMock.mockImplementation((permission) => permission === 'tags.viewAny')

    renderPage()

    expect(screen.getByRole('region', { name: 'table-tags' })).toBeInTheDocument()
    expect(screen.queryByText("You don't have permission to view tags.")).not.toBeInTheDocument()
  })
})
