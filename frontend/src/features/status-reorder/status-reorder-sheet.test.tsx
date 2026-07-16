import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import { AxiosError } from 'axios'
import { StatusReorderSheet } from '@/features/status-reorder/status-reorder-sheet'
import type { StatusReorderItem } from '@/features/status-reorder/types'

/**
 * Spec 0039 AC-011. Mirrors `sortable-list.test.tsx`'s jsdom rect/keyboard-
 * sensor workarounds — this suite drives the SAME underlying `<SortableList>`
 * through the real `useStatusReorder` hook, mocking only the HTTP boundary
 * (`@/features/status-reorder/api`) and `sonner`.
 */

const fetchStatusesForReorderMock = vi.fn()
const reorderStatusesMock = vi.fn()

vi.mock('@/features/status-reorder/api', () => ({
  fetchStatusesForReorder: (...args: unknown[]) => fetchStatusesForReorderMock(...args),
  reorderStatuses: (...args: unknown[]) => reorderStatusesMock(...args),
}))

const toastSuccessMock = vi.fn()
const toastErrorMock = vi.fn()
vi.mock('sonner', () => ({
  toast: {
    success: (...args: unknown[]) => toastSuccessMock(...args),
    error: (...args: unknown[]) => toastErrorMock(...args),
  },
}))

const ITEMS: StatusReorderItem[] = [
  { id: 1, name: 'New', systemKey: 'new' },
  { id: 2, name: 'Alpha', systemKey: null },
  { id: 3, name: 'Bravo', systemKey: null },
  { id: 4, name: 'Closed', systemKey: 'closed' },
]

const LABELS = {
  title: 'Reorder statuses',
  subtitle: 'Drag to reorder.',
  dragHandleLabel: 'Drag to reorder',
  loadError: 'Unable to load the statuses.',
  saved: 'Order updated successfully.',
  forbidden: 'You cannot reorder these statuses.',
  genericError: 'Unable to update the order.',
}

const ROW_HEIGHT = 40

/** See `sortable-list.test.tsx`: jsdom's all-zero rects tie every row, so this stubs a rect whose `top` follows DOM order. */
function mockRowRects() {
  vi.spyOn(HTMLElement.prototype, 'getBoundingClientRect').mockImplementation(function (
    this: HTMLElement,
  ) {
    const rows = Array.from(document.querySelectorAll('li'))
    const index = rows.indexOf(this as HTMLLIElement)
    const top = index === -1 ? 0 : index * ROW_HEIGHT
    return {
      width: 280,
      height: ROW_HEIGHT,
      top,
      bottom: top + ROW_HEIGHT,
      left: 0,
      right: 280,
      x: 0,
      y: top,
      toJSON: () => ({}),
    } as DOMRect
  })
}

/** The keyboard sensor attaches its move/drop listeners in a macrotask. */
async function flushSensorAttach() {
  await new Promise((resolve) => setTimeout(resolve, 0))
}

function renderSheet(onReordered = vi.fn()) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <StatusReorderSheet
        open
        onOpenChange={vi.fn()}
        resource="lead-statuses"
        labels={LABELS}
        onReordered={onReordered}
      />
    </QueryClientProvider>,
  )
}

/** Drags the first non-pinned handle (Alpha) one position down via keyboard, mirroring `sortable-list.test.tsx`. */
async function dragFirstCustomDown() {
  const [alphaHandle] = screen.getAllByRole('button', { name: LABELS.dragHandleLabel })
  alphaHandle.focus()
  fireEvent.keyDown(alphaHandle, { code: 'Space' })
  await flushSensorAttach()
  fireEvent.keyDown(document, { code: 'ArrowDown' })
  fireEvent.keyDown(document, { code: 'Space' })
}

beforeEach(() => {
  mockRowRects()
  fetchStatusesForReorderMock.mockReset().mockResolvedValue(ITEMS)
  reorderStatusesMock.mockReset()
  toastSuccessMock.mockReset()
  toastErrorMock.mockReset()
})

afterEach(() => {
  vi.restoreAllMocks()
})

describe('StatusReorderSheet (spec 0039 AC-011)', () => {
  it('pins New first and Closed last, without a drag handle', async () => {
    renderSheet()

    await screen.findByText('New')
    expect(screen.getAllByRole('button', { name: LABELS.dragHandleLabel })).toHaveLength(2)
    const rows = screen.getAllByRole('listitem')
    expect(rows[0]).toHaveTextContent('New')
    expect(rows[rows.length - 1]).toHaveTextContent('Closed')
  })

  it('persists a drag with only the custom ids, in visual order', async () => {
    reorderStatusesMock.mockResolvedValue([
      { id: 3, sort_order: 10, system_key: null },
      { id: 2, sort_order: 20, system_key: null },
    ])
    const onReordered = vi.fn()
    renderSheet(onReordered)
    await screen.findByText('New')

    await dragFirstCustomDown()

    await waitFor(() => expect(reorderStatusesMock).toHaveBeenCalledWith('lead-statuses', [3, 2]))
    await waitFor(() => expect(toastSuccessMock).toHaveBeenCalledWith(LABELS.saved))
    expect(onReordered).toHaveBeenCalledTimes(1)
  })

  it('reverts the order and shows a toast on a 403', async () => {
    reorderStatusesMock.mockRejectedValue(
      new AxiosError('Forbidden', '403', undefined, undefined, {
        status: 403,
        data: { success: false, message: 'Forbidden' },
      } as never),
    )
    renderSheet()
    await screen.findByText('New')

    await dragFirstCustomDown()

    await waitFor(() => expect(toastErrorMock).toHaveBeenCalledWith(LABELS.forbidden))
    const rows = screen.getAllByRole('listitem')
    expect(rows.map((row) => row.textContent)).toEqual(['New', 'Alpha', 'Bravo', 'Closed'])
  })

  it('reverts the order and shows a generic toast on a 422', async () => {
    reorderStatusesMock.mockRejectedValue(
      new AxiosError('Unprocessable', '422', undefined, undefined, {
        status: 422,
        data: { success: false, message: 'Invalid ids' },
      } as never),
    )
    renderSheet()
    await screen.findByText('New')

    await dragFirstCustomDown()

    await waitFor(() => expect(toastErrorMock).toHaveBeenCalledWith(LABELS.genericError))
    const rows = screen.getAllByRole('listitem')
    expect(rows.map((row) => row.textContent)).toEqual(['New', 'Alpha', 'Bravo', 'Closed'])
  })
})
