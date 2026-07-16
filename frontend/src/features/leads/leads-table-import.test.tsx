import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { render, screen } from '@testing-library/react'
import { MemoryRouter } from 'react-router-dom'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { LeadsTable } from '@/features/leads/leads-table'

/**
 * The Import module is now a standalone top-level module (`/imports*`, spec
 * 2026-07-16), no longer reachable from the Lead module: `LeadsTable` used to
 * carry an "Import lead" action wired to the generic table's `importSlot`
 * (spec 0034 AC-014). This regression guard asserts the leftover has been
 * fully removed rather than just hidden — no `importSlot` prop is threaded
 * to `<TableView>` at all, and no import-related affordance renders anywhere
 * in the leads page.
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

const capturedProps: { importSlot?: ReactNode }[] = []

vi.mock('@/features/table/table-view', () => ({
  TableView: ({ importSlot }: { importSlot?: ReactNode }) => {
    capturedProps.push({ importSlot })
    return <div role="region" aria-label="table-leads" />
  },
}))

function renderTable() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <MemoryRouter initialEntries={['/leads']}>
        <LeadsTable />
      </MemoryRouter>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  canMock.mockReset()
  canMock.mockReturnValue(true)
  capturedProps.length = 0
})

describe('LeadsTable — no import affordance', () => {
  it('does not thread an importSlot prop to the generic table', () => {
    renderTable()

    expect(capturedProps).toHaveLength(1)
    expect(capturedProps[0].importSlot).toBeUndefined()
  })

  it('does not render any "Import lead" affordance', () => {
    renderTable()

    expect(screen.queryByText('Import lead')).not.toBeInTheDocument()
    expect(screen.queryByRole('menuitem', { name: /import/i })).not.toBeInTheDocument()
  })
})
