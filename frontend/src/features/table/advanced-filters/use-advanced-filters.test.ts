import { beforeEach, describe, expect, it, vi } from 'vitest'
import { renderHook, act } from '@testing-library/react'
import { useAdvancedFilters } from '@/features/table/advanced-filters/use-advanced-filters'
import type { AdvancedFilterDescriptor } from '@/features/table/advanced-filters/types'

const mutateMock = vi.fn()

vi.mock('@/features/table/use-table-filters', () => ({
  useSaveTableFilters: () => ({ mutate: mutateMock, isPending: false }),
}))

/** Minimal, schema-valid descriptor fixture; each test overrides only what it exercises. */
function descriptor(
  overrides: Partial<AdvancedFilterDescriptor> & Pick<AdvancedFilterDescriptor, 'name' | 'type'>,
): AdvancedFilterDescriptor {
  return {
    label: 'table.test.label',
    order: 0,
    required: false,
    visible: true,
    width: 'md',
    multiple: false,
    ...overrides,
  }
}

beforeEach(() => {
  mutateMock.mockReset()
})

describe('useAdvancedFilters', () => {
  it('seeds draft/applied from defaultValue when there is no persisted state', () => {
    const descriptors = [descriptor({ name: 'status', type: 'text', defaultValue: 'active' })]

    const { result } = renderHook(() =>
      useAdvancedFilters({ domain: 'leads', descriptors, applied: null, onApplied: vi.fn() }),
    )

    expect(result.current.draft.status).toBe('active')
  })

  it('seeds draft/applied from the persisted appliedAdvancedFilters, overriding defaults', () => {
    const descriptors = [descriptor({ name: 'status', type: 'text', defaultValue: 'active' })]

    const { result } = renderHook(() =>
      useAdvancedFilters({
        domain: 'leads',
        descriptors,
        applied: { status: 'won' },
        onApplied: vi.fn(),
      }),
    )

    expect(result.current.draft.status).toBe('won')
  })

  it('setFieldValue updates only the draft, leaving applied/getApplied untouched until apply()', () => {
    const descriptors = [descriptor({ name: 'status', type: 'text' })]
    const { result } = renderHook(() =>
      useAdvancedFilters({ domain: 'leads', descriptors, applied: null, onApplied: vi.fn() }),
    )

    act(() => result.current.setFieldValue('status', 'won'))

    expect(result.current.draft.status).toBe('won')
    expect(result.current.getApplied()).toEqual({})
  })

  it('apply() applies the draft, persists the active subset once, and refreshes once', () => {
    const descriptors = [descriptor({ name: 'status', type: 'text' })]
    const onApplied = vi.fn()
    const { result } = renderHook(() =>
      useAdvancedFilters({ domain: 'leads', descriptors, applied: null, onApplied }),
    )

    act(() => result.current.setFieldValue('status', 'won'))
    act(() => result.current.apply())

    expect(result.current.getApplied()).toEqual({ status: 'won' })
    expect(mutateMock).toHaveBeenCalledTimes(1)
    expect(mutateMock).toHaveBeenCalledWith({ advancedFilters: { status: 'won' } })
    expect(onApplied).toHaveBeenCalledTimes(1)
  })

  it('reset() reverts to defaults, persists an empty map once, and refreshes once', () => {
    const descriptors = [descriptor({ name: 'status', type: 'text', defaultValue: 'active' })]
    const onApplied = vi.fn()
    // Hoisted (not inlined in the renderHook callback): a fresh object literal
    // there would be a new reference every render, which is exactly the
    // unstable-`applied`-reference case the hook's content-compare guards.
    const applied = { status: 'won' }
    const { result } = renderHook(() =>
      useAdvancedFilters({ domain: 'leads', descriptors, applied, onApplied }),
    )

    act(() => result.current.reset())

    expect(result.current.draft.status).toBe('active')
    expect(mutateMock).toHaveBeenCalledWith({ advancedFilters: {} })
    expect(onApplied).toHaveBeenCalledTimes(1)
  })

  it('disables Apply while a required field is empty, and does not apply/refresh on attempt', () => {
    const descriptors = [descriptor({ name: 'status', type: 'text', required: true })]
    const onApplied = vi.fn()
    const { result } = renderHook(() =>
      useAdvancedFilters({ domain: 'leads', descriptors, applied: null, onApplied }),
    )

    expect(result.current.canApply).toBe(false)
    expect(result.current.isFieldInvalid(descriptors[0])).toBe(true)

    act(() => result.current.apply())

    expect(mutateMock).not.toHaveBeenCalled()
    expect(onApplied).not.toHaveBeenCalled()
  })

  it('enables Apply once the required field is filled', () => {
    const descriptors = [descriptor({ name: 'status', type: 'text', required: true })]
    const { result } = renderHook(() =>
      useAdvancedFilters({ domain: 'leads', descriptors, applied: null, onApplied: vi.fn() }),
    )

    act(() => result.current.setFieldValue('status', 'won'))

    expect(result.current.canApply).toBe(true)
  })

  it('disables a dependent field while its parent is empty, and clears it once the parent changes', () => {
    const descriptors = [
      descriptor({ name: 'project', type: 'relation' }),
      descriptor({
        name: 'campaign',
        type: 'relation',
        dependency: { on: 'project' },
      }),
    ]
    const { result } = renderHook(() =>
      useAdvancedFilters({ domain: 'projects', descriptors, applied: null, onApplied: vi.fn() }),
    )

    expect(result.current.isFieldDisabled(descriptors[1])).toBe(true)

    act(() => result.current.setFieldValue('campaign', 7))
    act(() => result.current.setFieldValue('project', 1))

    expect(result.current.isFieldDisabled(descriptors[1])).toBe(false)
    // Changing the parent clears whatever the (disabled) child previously held.
    expect(result.current.draft.campaign).toBeNull()
  })

  it('resolves dependencyParamsFor to the parent value keyed by `dependency.param`', () => {
    const descriptors = [
      descriptor({ name: 'project', type: 'relation' }),
      descriptor({
        name: 'campaign',
        type: 'relation',
        dependency: { on: 'project', param: 'project_id' },
      }),
    ]
    const { result } = renderHook(() =>
      useAdvancedFilters({ domain: 'projects', descriptors, applied: null, onApplied: vi.fn() }),
    )

    expect(result.current.dependencyParamsFor(descriptors[1])).toBeUndefined()

    act(() => result.current.setFieldValue('project', 12))

    expect(result.current.dependencyParamsFor(descriptors[1])).toEqual({ project_id: 12 })
  })

  it('counts only filters whose applied value differs from their default', () => {
    const descriptors = [
      descriptor({ name: 'status', type: 'text', defaultValue: 'active' }),
      descriptor({ name: 'notes', type: 'text' }),
    ]
    const { result } = renderHook(() =>
      useAdvancedFilters({
        domain: 'leads',
        descriptors,
        applied: { status: 'active', notes: '' },
        onApplied: vi.fn(),
      }),
    )

    expect(result.current.activeCount).toBe(0)

    act(() => result.current.setFieldValue('status', 'won'))
    act(() => result.current.apply())

    expect(result.current.activeCount).toBe(1)
  })

  it('exposes the active values map alongside the count', () => {
    const descriptors = [descriptor({ name: 'status', type: 'text' })]
    const { result } = renderHook(() =>
      useAdvancedFilters({ domain: 'leads', descriptors, applied: null, onApplied: vi.fn() }),
    )

    act(() => result.current.setFieldValue('status', 'won'))
    act(() => result.current.apply())

    expect(result.current.activeValues).toEqual({ status: 'won' })
  })

  it('applyValues() restores an externally-supplied set (e.g. a saved filter view), persists and refreshes once (spec AC-009)', () => {
    const descriptors = [
      descriptor({ name: 'status', type: 'text' }),
      descriptor({ name: 'notes', type: 'text', defaultValue: 'n/a' }),
    ]
    const onApplied = vi.fn()
    const { result } = renderHook(() =>
      useAdvancedFilters({ domain: 'leads', descriptors, applied: null, onApplied }),
    )

    act(() => result.current.applyValues({ status: 'won' }))

    // The unspecified `notes` falls back to its own default, like the initial seed.
    expect(result.current.draft).toEqual({ status: 'won', notes: 'n/a' })
    expect(result.current.getApplied()).toEqual({ status: 'won' })
    expect(mutateMock).toHaveBeenCalledWith({ advancedFilters: { status: 'won' } })
    expect(onApplied).toHaveBeenCalledTimes(1)
  })

  it('applyValues() bypasses required-gating (a saved view is assumed already valid)', () => {
    const descriptors = [descriptor({ name: 'status', type: 'text', required: true })]
    const onApplied = vi.fn()
    const { result } = renderHook(() =>
      useAdvancedFilters({ domain: 'leads', descriptors, applied: null, onApplied }),
    )

    expect(result.current.canApply).toBe(false)

    act(() => result.current.applyValues({ status: 'won' }))

    expect(result.current.draft.status).toBe('won')
    expect(onApplied).toHaveBeenCalledTimes(1)
  })
})
