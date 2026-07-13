import * as React from 'react'

export const RESIZABLE_MIN_WIDTH = 380
export const RESIZABLE_MAX_VIEWPORT_RATIO = 0.95
const KEYBOARD_STEP = 16

type ResizableSide = 'left' | 'right'

type UseResizableWidthOptions = {
  enabled: boolean
  side: ResizableSide
  defaultWidth: number
  storageKey: string
}

function readMaxWidth(fallback: number) {
  if (typeof window === 'undefined') return fallback
  return Math.round(window.innerWidth * RESIZABLE_MAX_VIEWPORT_RATIO)
}

/** On viewports narrower than the minimum, the viewport cap wins over the minimum. */
function clampWidth(width: number, maxWidth: number) {
  return Math.min(Math.max(width, RESIZABLE_MIN_WIDTH), maxWidth)
}

function readStoredWidth(storageKey: string) {
  if (typeof window === 'undefined') return null
  try {
    const stored = window.localStorage.getItem(storageKey)
    const parsed = stored ? Number.parseInt(stored, 10) : Number.NaN
    return Number.isFinite(parsed) ? parsed : null
  } catch {
    return null
  }
}

/**
 * Drag/keyboard resizing of a horizontal panel, with the chosen width persisted
 * per `storageKey`. The stored value is written only when the gesture is
 * committed (pointerup, key press, reset) and re-clamped on every mount, so a
 * width saved on a wide screen never overflows a narrower one.
 */
export function useResizableWidth({
  enabled,
  side,
  defaultWidth,
  storageKey,
}: UseResizableWidthOptions) {
  const [maxWidth, setMaxWidth] = React.useState(() => readMaxWidth(defaultWidth))
  const [width, setWidth] = React.useState(() =>
    enabled
      ? clampWidth(readStoredWidth(storageKey) ?? defaultWidth, readMaxWidth(defaultWidth))
      : defaultWidth
  )

  // Read by the pointermove handler, which outlives the render that created it.
  const maxWidthRef = React.useRef(maxWidth)
  const widthRef = React.useRef(width)
  const endDragRef = React.useRef<(() => void) | null>(null)

  const applyWidth = React.useCallback((next: number) => {
    widthRef.current = next
    setWidth(next)
  }, [])

  const commitWidth = React.useCallback(
    (next: number) => {
      applyWidth(next)
      try {
        window.localStorage.setItem(storageKey, String(next))
      } catch {
        // Storage can be unavailable (private mode, quota): resizing still works.
      }
    },
    [applyWidth, storageKey]
  )

  React.useEffect(() => {
    if (!enabled) return

    const onWindowResize = () => {
      const nextMax = readMaxWidth(defaultWidth)
      maxWidthRef.current = nextMax
      setMaxWidth(nextMax)
      applyWidth(clampWidth(widthRef.current, nextMax))
    }

    window.addEventListener('resize', onWindowResize)
    return () => window.removeEventListener('resize', onWindowResize)
  }, [applyWidth, defaultWidth, enabled])

  React.useEffect(() => () => endDragRef.current?.(), [])

  const onPointerDown = React.useCallback(
    (event: React.PointerEvent<HTMLDivElement>) => {
      if (event.button !== 0) return
      event.preventDefault()
      document.body.style.userSelect = 'none'
      document.body.style.cursor = 'col-resize'

      const onPointerMove = (moveEvent: PointerEvent) => {
        const distance =
          side === 'right' ? window.innerWidth - moveEvent.clientX : moveEvent.clientX
        applyWidth(clampWidth(distance, maxWidthRef.current))
      }
      const onPointerUp = () => {
        endDrag()
        commitWidth(widthRef.current)
      }
      const endDrag = () => {
        endDragRef.current = null
        document.body.style.userSelect = ''
        document.body.style.cursor = ''
        window.removeEventListener('pointermove', onPointerMove)
        window.removeEventListener('pointerup', onPointerUp)
        window.removeEventListener('pointercancel', onPointerUp)
      }

      endDragRef.current = endDrag
      window.addEventListener('pointermove', onPointerMove)
      window.addEventListener('pointerup', onPointerUp)
      window.addEventListener('pointercancel', onPointerUp)
    },
    [applyWidth, commitWidth, side]
  )

  const onKeyDown = React.useCallback(
    (event: React.KeyboardEvent<HTMLDivElement>) => {
      const direction =
        event.key === 'ArrowLeft' ? -1 : event.key === 'ArrowRight' ? 1 : 0
      if (direction === 0) return
      event.preventDefault()

      // Moving the handle away from its anchored edge grows the panel.
      const delta = (side === 'right' ? -direction : direction) * KEYBOARD_STEP
      commitWidth(clampWidth(widthRef.current + delta, maxWidthRef.current))
    },
    [commitWidth, side]
  )

  const onDoubleClick = React.useCallback(() => {
    commitWidth(clampWidth(defaultWidth, maxWidthRef.current))
  }, [commitWidth, defaultWidth])

  return {
    width,
    handleProps: {
      role: 'separator' as const,
      'aria-orientation': 'vertical' as const,
      'aria-valuenow': width,
      'aria-valuemin': RESIZABLE_MIN_WIDTH,
      'aria-valuemax': maxWidth,
      tabIndex: 0,
      onPointerDown,
      onKeyDown,
      onDoubleClick,
    },
  }
}
