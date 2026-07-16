import { afterEach, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'

import { SortableList, type SortableListItem } from '@/components/ui/sortable-list'

interface TestItem extends SortableListItem {
  label: string
  pinned?: boolean
}

const ITEMS: TestItem[] = [
  { id: 'new', label: 'New', pinned: true },
  { id: 'a', label: 'Alpha' },
  { id: 'b', label: 'Bravo' },
  { id: 'c', label: 'Charlie' },
  { id: 'closed', label: 'Closed', pinned: true },
]

const ROW_HEIGHT = 40
const DRAG_HANDLE_LABEL = 'Drag to reorder'

/**
 * @dnd-kit's keyboard coordinate getter picks the next target by comparing
 * `getBoundingClientRect()` of every row. jsdom always returns an all-zero
 * rect, so every row would tie; this stubs a rect whose `top` follows DOM
 * order, matching the real layout closely enough for direction detection.
 */
function mockRowRects() {
  vi.spyOn(HTMLElement.prototype, 'getBoundingClientRect').mockImplementation(function (
    this: HTMLElement
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

function renderList(onReorder: (orderedIds: string[]) => void) {
  return render(
    <SortableList
      items={ITEMS}
      isPinned={(item) => Boolean(item.pinned)}
      renderItem={(item) => item.label}
      onReorder={onReorder}
      dragHandleLabel={DRAG_HANDLE_LABEL}
    />
  )
}

describe('SortableList', () => {
  beforeEach(() => {
    mockRowRects()
  })

  afterEach(() => {
    vi.restoreAllMocks()
  })

  it('renders every item, pinned and sortable alike', () => {
    renderList(vi.fn())

    ITEMS.forEach((item) => expect(screen.getByText(item.label)).toBeInTheDocument())
  })

  it('gives a drag handle only to non-pinned rows', () => {
    renderList(vi.fn())

    expect(screen.getAllByRole('button', { name: DRAG_HANDLE_LABEL })).toHaveLength(3)
  })

  it('reorders via keyboard and reports the full ordered id list, pinned ids unchanged', async () => {
    const onReorder = vi.fn()
    renderList(onReorder)

    // handles are the 3 non-pinned rows in order: Alpha, Bravo, Charlie.
    const [, bravoHandle] = screen.getAllByRole('button', { name: DRAG_HANDLE_LABEL })
    bravoHandle.focus()

    fireEvent.keyDown(bravoHandle, { code: 'Space' })
    await flushSensorAttach()
    fireEvent.keyDown(document, { code: 'ArrowUp' })
    fireEvent.keyDown(document, { code: 'Space' })

    expect(onReorder).toHaveBeenCalledWith(['new', 'b', 'a', 'c', 'closed'])
  })

  it('does not call onReorder when the drag ends on the same position', async () => {
    const onReorder = vi.fn()
    renderList(onReorder)

    const [alphaHandle] = screen.getAllByRole('button', { name: DRAG_HANDLE_LABEL })
    alphaHandle.focus()

    fireEvent.keyDown(alphaHandle, { code: 'Space' })
    await flushSensorAttach()
    fireEvent.keyDown(document, { code: 'Space' })

    expect(onReorder).not.toHaveBeenCalled()
  })
})
