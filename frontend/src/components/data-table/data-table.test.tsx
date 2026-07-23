import { render } from '@testing-library/react'
import type { ICellRendererParams } from 'ag-grid-community'
import { describe, expect, it } from 'vitest'
import {
  ACTIONS_COLUMN_ID,
  SkeletonLoadingCell,
} from '@/components/data-table/data-table'

/**
 * `SkeletonLoadingCell` is AG Grid's per-cell `loadingCellRenderer`. It only
 * reads `colDef.colId` to pick the bar width, so a minimal params stub keyed by
 * `colId` is enough to exercise the branch under test.
 */
function renderCell(colId: string) {
  const params = { colDef: { colId } } as ICellRendererParams
  const { container } = render(<SkeletonLoadingCell {...params} />)
  const skeleton = container.querySelector('[data-slot="skeleton"]')
  expect(skeleton).not.toBeNull()
  return skeleton as HTMLElement
}

describe('SkeletonLoadingCell', () => {
  it('renders a narrow (w-12) bar for the left-pinned actions column', () => {
    const skeleton = renderCell(ACTIONS_COLUMN_ID)
    expect(skeleton).toHaveClass('w-12')
    expect(skeleton).toHaveClass('h-4')
  })

  it('renders a wide (w-[70%]) bar for a data column', () => {
    const skeleton = renderCell('name')
    expect(skeleton).toHaveClass('w-[70%]')
    expect(skeleton).toHaveClass('h-4')
  })
})
