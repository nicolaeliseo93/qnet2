import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import type { ReactNode } from 'react'
import i18n from '@/i18n'
import {
  AsyncPaginatedMultiSelect,
  type AsyncPaginatedMultiSelectLabels,
} from '@/components/ui/async-paginated-multi-select'
import type { ForSelectItem } from '@/features/for-select/types'

const useForSelectMock = vi.fn()
const fetchNextPage = vi.fn()
const refetch = vi.fn()

vi.mock('@/features/for-select/use-for-select', async () => {
  const actual = await vi.importActual<
    typeof import('@/features/for-select/use-for-select')
  >('@/features/for-select/use-for-select')
  return {
    // Keep the real flatten helper; only the hook is controlled.
    flattenForSelectPages: actual.flattenForSelectPages,
    useForSelect: (args: unknown) => useForSelectMock(args),
  }
})

const labels: AsyncPaginatedMultiSelectLabels = {
  placeholder: 'Select users…',
  searchPlaceholder: 'Search users…',
  empty: 'No users found.',
  error: 'Unable to load users.',
  retry: 'Retry',
  removeLabel: 'Remove member',
  triggerLabel: 'Members',
}

function queryState(
  overrides: Partial<ReturnType<typeof baseState>> = {},
): ReturnType<typeof baseState> {
  return { ...baseState(), ...overrides }
}

function baseState() {
  return {
    data: undefined as { pages: { items: ForSelectItem[] }[] } | undefined,
    isPending: false,
    isError: false,
    fetchNextPage,
    hasNextPage: false,
    isFetchingNextPage: false,
    refetch,
  }
}

function pagesOf(items: ForSelectItem[]) {
  return { pages: [{ items }] }
}

function renderSelect(props: Partial<Parameters<typeof AsyncPaginatedMultiSelect>[0]> = {}) {
  const onChange = vi.fn()
  render(
    <AsyncPaginatedMultiSelect
      resource="users"
      value={[]}
      onChange={onChange}
      labels={labels}
      {...props}
    />,
  )
  return { onChange }
}

function renderSelectInSheet(
  props: Partial<Parameters<typeof AsyncPaginatedMultiSelect>[0]> = {},
) {
  const onChange = vi.fn()
  let sheetContent: HTMLDivElement | null = null

  function SheetWrapper({ children }: { children: ReactNode }) {
    return (
      <div
        data-slot="sheet-content"
        ref={(node) => {
          sheetContent = node
        }}
      >
        {children}
      </div>
    )
  }

  render(
    <SheetWrapper>
      <AsyncPaginatedMultiSelect
        resource="users"
        value={[]}
        onChange={onChange}
        labels={labels}
        {...props}
      />
    </SheetWrapper>,
  )

  return {
    onChange,
    getSheetContent: () => sheetContent,
  }
}

function open() {
  fireEvent.click(screen.getByRole('button', { name: 'Members' }))
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  useForSelectMock.mockReset()
  fetchNextPage.mockReset()
  refetch.mockReset()
  useForSelectMock.mockReturnValue(queryState())
})

describe('AsyncPaginatedMultiSelect', () => {
  it('shows the placeholder when nothing is selected', () => {
    renderSelect()
    expect(screen.getByText('Select users…')).toBeInTheDocument()
  })

  it('renders a skeleton (not a spinner) while the first page loads', () => {
    useForSelectMock.mockReturnValue(queryState({ isPending: true }))
    renderSelect()
    open()
    expect(
      screen.getByTestId('async-multi-select-skeleton'),
    ).toBeInTheDocument()
  })

  it('renders the loaded options with subtitles', () => {
    useForSelectMock.mockReturnValue(
      queryState({
        data: pagesOf([
          { id: 1, label: 'Jane Doe', subtitle: 'jane@acme.test' },
        ]),
      }),
    )
    renderSelect()
    open()
    expect(screen.getByRole('option', { name: /Jane Doe/ })).toBeInTheDocument()
    expect(screen.getByText('jane@acme.test')).toBeInTheDocument()
  })

  it('renders avatars (initials fallback) in options when showAvatar is set', () => {
    useForSelectMock.mockReturnValue(
      queryState({
        data: pagesOf([
          { id: 1, label: 'Jane Doe', subtitle: 'jane@acme.test', avatar_url: null },
        ]),
      }),
    )
    renderSelect({ showAvatar: true })
    open()
    // UserAvatar shows the label initials when no image is available.
    expect(screen.getByText('JD')).toBeInTheDocument()
  })

  it('does not render avatars by default', () => {
    useForSelectMock.mockReturnValue(
      queryState({
        data: pagesOf([{ id: 1, label: 'Jane Doe', subtitle: 'jane@acme.test' }]),
      }),
    )
    renderSelect()
    open()
    expect(screen.queryByText('JD')).not.toBeInTheDocument()
  })

  it('renders an avatar in a selected badge when showAvatar is set', () => {
    renderSelect({
      showAvatar: true,
      value: [42],
      selectedItems: [{ id: 42, label: 'Hydrated User' }],
    })
    // Badge shows the member with its avatar initials fallback.
    expect(screen.getByText('Hydrated User')).toBeInTheDocument()
    expect(screen.getByText('HU')).toBeInTheDocument()
  })

  it('drives server search through the hook (debounced)', async () => {
    useForSelectMock.mockReturnValue(queryState({ data: pagesOf([]) }))
    renderSelect()
    open()

    fireEvent.change(screen.getByLabelText('Search users…'), {
      target: { value: 'jane' },
    })

    await waitFor(() =>
      expect(useForSelectMock).toHaveBeenCalledWith(
        expect.objectContaining({ search: 'jane' }),
      ),
    )
  })

  it('selecting an option adds its id to the value', () => {
    useForSelectMock.mockReturnValue(
      queryState({ data: pagesOf([{ id: 5, label: 'Bob' }]) }),
    )
    const { onChange } = renderSelect({ value: [] })
    open()
    fireEvent.click(screen.getByRole('option', { name: /Bob/ }))
    expect(onChange).toHaveBeenCalledWith([5])
  })

  it('clicking a selected option removes its id from the value', () => {
    useForSelectMock.mockReturnValue(
      queryState({ data: pagesOf([{ id: 5, label: 'Bob' }]) }),
    )
    const { onChange } = renderSelect({ value: [5] })
    open()
    fireEvent.click(screen.getByRole('option', { name: /Bob/ }))
    expect(onChange).toHaveBeenCalledWith([])
  })

  it('renders selected ids as removable badges using hydrated labels', () => {
    const { onChange } = renderSelect({
      value: [42],
      selectedItems: [{ id: 42, label: 'Hydrated User' }],
    })
    expect(screen.getByText('Hydrated User')).toBeInTheDocument()

    fireEvent.click(
      screen.getByRole('button', { name: 'Remove member Hydrated User' }),
    )
    expect(onChange).toHaveBeenCalledWith([])
  })

  it('falls back to #id for a selected badge with no known label', () => {
    renderSelect({ value: [7] })
    expect(screen.getByText('#7')).toBeInTheDocument()
  })

  it('hydrates selected labels on mount when some selected ids are unknown', () => {
    renderSelect({ value: [7], selectedItems: [{ id: 8, label: 'Other User' }] })
    expect(useForSelectMock).toHaveBeenCalledWith(
      expect.objectContaining({
        ids: [7],
        enabled: true,
      }),
    )
  })

  it('shows the empty state when the query returns no options', () => {
    useForSelectMock.mockReturnValue(queryState({ data: pagesOf([]) }))
    renderSelect()
    open()
    expect(screen.getByText('No users found.')).toBeInTheDocument()
  })

  it('shows the error state with a retry that refetches', () => {
    useForSelectMock.mockReturnValue(queryState({ isError: true }))
    renderSelect()
    open()
    expect(screen.getByText('Unable to load users.')).toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: 'Retry' }))
    expect(refetch).toHaveBeenCalled()
  })

  it('loads the next page when the sentinel intersects (infinite scroll)', async () => {
    useForSelectMock.mockReturnValue(
      queryState({
        data: pagesOf([{ id: 1, label: 'A' }]),
        hasNextPage: true,
      }),
    )

    // Capture the IntersectionObserver callback and fire it manually.
    type ObserverCallback = (entries: { isIntersecting: boolean }[]) => void
    let trigger: ObserverCallback | null = null
    const observe = vi.fn()
    vi.stubGlobal(
      'IntersectionObserver',
      class {
        constructor(cb: ObserverCallback) {
          trigger = cb
        }
        observe = observe
        unobserve() {}
        disconnect() {}
        takeRecords() {
          return []
        }
      },
    )

    renderSelect()
    open()

    // Wait for the popover content (and its sentinel) to mount.
    await screen.findByRole('option', { name: /A/ })
    await waitFor(() => expect(observe).toHaveBeenCalled())
    const fire = trigger as ObserverCallback | null
    fire?.([{ isIntersecting: true }])
    expect(fetchNextPage).toHaveBeenCalled()

    vi.unstubAllGlobals()
  })

  it('portals the popup into the sheet content when used inside a sheet', async () => {
    useForSelectMock.mockReturnValue(
      queryState({ data: pagesOf([{ id: 1, label: 'Jane Doe' }]) }),
    )

    const { getSheetContent } = renderSelectInSheet()
    open()

    const listbox = await screen.findByRole('listbox')
    expect(getSheetContent()).not.toBeNull()
    expect(getSheetContent()?.contains(listbox)).toBe(true)
  })

  it('toggles an option via the keyboard (Enter)', () => {
    useForSelectMock.mockReturnValue(
      queryState({ data: pagesOf([{ id: 5, label: 'Bob' }]) }),
    )
    const { onChange } = renderSelect({ value: [] })
    open()
    fireEvent.keyDown(screen.getByRole('option', { name: /Bob/ }), {
      key: 'Enter',
    })
    expect(onChange).toHaveBeenCalledWith([5])
  })
})
