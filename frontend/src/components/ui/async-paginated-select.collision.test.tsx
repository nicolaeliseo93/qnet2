import { describe, expect, it, vi, beforeEach } from 'vitest'
import { render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import type { ReactNode } from 'react'
import i18n from '@/i18n'
import {
  AsyncPaginatedSelect,
  type AsyncPaginatedSelectLabels,
} from '@/components/ui/async-paginated-select'
import type { ForSelectItem, PaginatedResponse } from '@/features/for-select/types'

// Real useForSelect / useForSelectLabels against a controlled fetch layer, so
// the query-key collision (fixed by the ids-keyed label query) is exercised for
// real instead of being mocked away.
const fetchForSelectMock = vi.fn()
vi.mock('@/features/for-select/api', () => ({
  FOR_SELECT_PAGE_SIZE: 25,
  fetchForSelect: (resource: string, params: unknown) =>
    fetchForSelectMock(resource, params),
}))

const labels: AsyncPaginatedSelectLabels = {
  placeholder: 'Select…',
  searchPlaceholder: 'Search…',
  empty: 'None.',
  error: 'Error.',
  retry: 'Retry',
  clearLabel: 'Clear',
  triggerLabel: 'Manager',
}

function page(items: ForSelectItem[]): PaginatedResponse<ForSelectItem> {
  return {
    items,
    export_link: null,
    pagination: { total: items.length, offset: 0, limit: 25, total_pages: 1 },
  }
}

beforeEach(async () => {
  await i18n.changeLanguage('en')
  fetchForSelectMock.mockReset()
})

describe('AsyncPaginatedSelect sibling hydration (no #id collision)', () => {
  it('resolves each sibling select label even when both need id hydration', async () => {
    // Server returns only the users whose ids it was actually asked for. With a
    // shared, ids-less list cache the two siblings would collide (only one id
    // fetched); the ids-keyed label query gives each its own entry.
    const known: Record<number, ForSelectItem> = {
      5: { id: 5, label: 'Alice' },
      88: { id: 88, label: 'Bob' },
    }
    fetchForSelectMock.mockImplementation((_resource, params: { ids?: number[] }) =>
      Promise.resolve(page((params.ids ?? []).map((id) => known[id]).filter(Boolean))),
    )

    const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
    const Wrapper = ({ children }: { children: ReactNode }) => (
      <QueryClientProvider client={client}>{children}</QueryClientProvider>
    )

    render(
      <Wrapper>
        <AsyncPaginatedSelect resource="users" value={5} onChange={vi.fn()} labels={labels} />
        <AsyncPaginatedSelect resource="users" value={88} onChange={vi.fn()} labels={labels} />
      </Wrapper>,
    )

    await waitFor(() => expect(screen.getByText('Alice')).toBeInTheDocument())
    await waitFor(() => expect(screen.getByText('Bob')).toBeInTheDocument())
    expect(screen.queryByText('#5')).toBeNull()
    expect(screen.queryByText('#88')).toBeNull()
  })
})
