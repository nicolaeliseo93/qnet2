import '@testing-library/jest-dom/vitest'
import { afterEach } from 'vitest'
import { cleanup } from '@testing-library/react'

// jsdom does not implement these layout/pointer APIs that Radix primitives
// (e.g. Select) call when opening their popups. Stub them so component tests
// can drive the real UI instead of mocking the primitive.
if (!Element.prototype.scrollIntoView) {
  Element.prototype.scrollIntoView = () => {}
}
if (!Element.prototype.hasPointerCapture) {
  Element.prototype.hasPointerCapture = () => false
}
if (!Element.prototype.releasePointerCapture) {
  Element.prototype.releasePointerCapture = () => {}
}
// Radix popper-based popups (DropdownMenu, Popover) observe their trigger/content
// size to position themselves; jsdom ships no ResizeObserver implementation.
if (!globalThis.ResizeObserver) {
  globalThis.ResizeObserver = class {
    observe() {}
    unobserve() {}
    disconnect() {}
  }
}
// The notification list watches a sentinel element to trigger infinite-scroll
// loading; jsdom has no IntersectionObserver, so the observer never fires.
if (!globalThis.IntersectionObserver) {
  globalThis.IntersectionObserver = class {
    readonly root = null
    readonly rootMargin = ''
    readonly scrollMargin = ''
    readonly thresholds: ReadonlyArray<number> = []
    observe() {}
    unobserve() {}
    disconnect() {}
    takeRecords(): IntersectionObserverEntry[] {
      return []
    }
  }
}

// Unmount React trees and reset jsdom between tests.
afterEach(() => {
  cleanup()
})
