import { beforeEach, describe, expect, it } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from '@/components/ui/sheet'

// jsdom's viewport is 1024px wide, so the 95vw cap resolves to 973px.
const MAX_WIDTH = 973
const MIN_WIDTH = 380
const DEFAULT_WIDTH = 640

type SheetContentProps = React.ComponentProps<typeof SheetContent>

function renderSheet(props: SheetContentProps = {}) {
  return render(
    <Sheet open>
      <SheetContent {...props}>
        <SheetHeader>
          <SheetTitle>Panel</SheetTitle>
          <SheetDescription>Panel body</SheetDescription>
        </SheetHeader>
      </SheetContent>
    </Sheet>
  )
}

const getPanel = () => screen.getByRole('dialog')
const getHandle = () => screen.getByRole('separator')

describe('SheetContent resizing', () => {
  beforeEach(() => {
    window.localStorage.clear()
  })

  it('opens at the default width when nothing is persisted', () => {
    renderSheet({ storageKey: 'sheet-width:users' })

    expect(getPanel()).toHaveStyle({ width: `${DEFAULT_WIDTH}px` })
    expect(window.localStorage.getItem('sheet-width:users')).toBeNull()
  })

  it('restores the width persisted under its own storage key', () => {
    window.localStorage.setItem('sheet-width:users', '700')
    window.localStorage.setItem('sheet-width:products', '500')

    const { unmount } = renderSheet({ storageKey: 'sheet-width:users' })
    expect(getPanel()).toHaveStyle({ width: '700px' })
    unmount()

    renderSheet({ storageKey: 'sheet-width:products' })
    expect(getPanel()).toHaveStyle({ width: '500px' })
  })

  it('leaves the other sheets untouched when one is resized', () => {
    window.localStorage.setItem('sheet-width:products', '500')

    const { unmount } = renderSheet({ storageKey: 'sheet-width:users' })
    fireEvent.keyDown(getHandle(), { key: 'ArrowLeft' })
    unmount()

    expect(window.localStorage.getItem('sheet-width:users')).toBe(
      String(DEFAULT_WIDTH + 16)
    )
    expect(window.localStorage.getItem('sheet-width:products')).toBe('500')
  })

  it('clamps a persisted width to the min and max bounds', () => {
    window.localStorage.setItem('sheet-width:wide', '9999')
    const { unmount } = renderSheet({ storageKey: 'sheet-width:wide' })
    expect(getPanel()).toHaveStyle({ width: `${MAX_WIDTH}px` })
    unmount()

    window.localStorage.setItem('sheet-width:narrow', '50')
    renderSheet({ storageKey: 'sheet-width:narrow' })
    expect(getPanel()).toHaveStyle({ width: `${MIN_WIDTH}px` })
  })

  it('resizes on pointer drag and persists only on pointerup', () => {
    renderSheet({ storageKey: 'sheet-width:users' })

    fireEvent.pointerDown(getHandle(), { button: 0, clientX: 384 })
    fireEvent.pointerMove(window, { clientX: 524 })

    // 1024 (viewport) - 524 (pointer) = 500px of panel to the right of the handle.
    expect(getPanel()).toHaveStyle({ width: '500px' })
    expect(window.localStorage.getItem('sheet-width:users')).toBeNull()

    fireEvent.pointerUp(window)
    expect(window.localStorage.getItem('sheet-width:users')).toBe('500')
  })

  it('clamps the drag to the max width', () => {
    renderSheet({ storageKey: 'sheet-width:users' })

    fireEvent.pointerDown(getHandle(), { button: 0, clientX: 384 })
    fireEvent.pointerMove(window, { clientX: 0 })
    fireEvent.pointerUp(window)

    expect(getPanel()).toHaveStyle({ width: `${MAX_WIDTH}px` })
    expect(window.localStorage.getItem('sheet-width:users')).toBe(String(MAX_WIDTH))
  })

  it('adjusts the width with the arrow keys and exposes the aria range', () => {
    renderSheet({ storageKey: 'sheet-width:users' })
    const handle = getHandle()

    expect(handle).toHaveAttribute('aria-valuemin', String(MIN_WIDTH))
    expect(handle).toHaveAttribute('aria-valuemax', String(MAX_WIDTH))
    expect(handle).toHaveAttribute('aria-valuenow', String(DEFAULT_WIDTH))
    expect(handle).toHaveAttribute('tabindex', '0')

    // On a right-anchored sheet, dragging the handle left grows the panel.
    fireEvent.keyDown(handle, { key: 'ArrowLeft' })
    expect(getPanel()).toHaveStyle({ width: `${DEFAULT_WIDTH + 16}px` })

    fireEvent.keyDown(handle, { key: 'ArrowRight' })
    expect(getPanel()).toHaveStyle({ width: `${DEFAULT_WIDTH}px` })
    expect(handle).toHaveAttribute('aria-valuenow', String(DEFAULT_WIDTH))
    expect(window.localStorage.getItem('sheet-width:users')).toBe(String(DEFAULT_WIDTH))
  })

  it('grows a left-anchored sheet when the handle moves right', () => {
    renderSheet({ side: 'left', storageKey: 'sheet-width:sidebar' })

    fireEvent.keyDown(getHandle(), { key: 'ArrowRight' })

    expect(getPanel()).toHaveStyle({ width: `${DEFAULT_WIDTH + 16}px` })
  })

  it('resets to the default width on double-click', () => {
    window.localStorage.setItem('sheet-width:exports', '900')
    renderSheet({ storageKey: 'sheet-width:exports', defaultWidth: 448 })
    expect(getPanel()).toHaveStyle({ width: '900px' })

    fireEvent.doubleClick(getHandle())

    expect(getPanel()).toHaveStyle({ width: '448px' })
    expect(window.localStorage.getItem('sheet-width:exports')).toBe('448')
  })

  it('re-clamps the width when the viewport shrinks', () => {
    window.localStorage.setItem('sheet-width:users', '900')
    renderSheet({ storageKey: 'sheet-width:users' })

    window.innerWidth = 600
    fireEvent(window, new Event('resize'))

    expect(getPanel()).toHaveStyle({ width: '570px' })
    window.innerWidth = 1024
  })

  it('renders no handle and no inline width when resizable is false', () => {
    renderSheet({ resizable: false, storageKey: 'sheet-width:users' })

    expect(screen.queryByRole('separator')).not.toBeInTheDocument()
    expect(getPanel().style.width).toBe('')
    expect(window.localStorage.getItem('sheet-width:users')).toBeNull()
  })

  it('renders no handle on a top or bottom sheet', () => {
    const { unmount } = renderSheet({ side: 'top' })
    expect(screen.queryByRole('separator')).not.toBeInTheDocument()
    expect(getPanel().style.width).toBe('')
    unmount()

    renderSheet({ side: 'bottom' })
    expect(screen.queryByRole('separator')).not.toBeInTheDocument()
  })

  it('keeps the sheet-content slot the popover portals look up', () => {
    renderSheet({ storageKey: 'sheet-width:users' })

    expect(document.querySelector('[data-slot="sheet-content"]')).toBe(getPanel())
  })
})
