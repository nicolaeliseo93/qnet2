import { render, screen } from '@testing-library/react'
import type { ICellRendererParams } from 'ag-grid-community'
import { describe, expect, it } from 'vitest'
import { operationalSiteColumnRenderers } from '@/features/operational-sites/column-renderers'

/** AC-016: the derived address columns render readable, truncated text cells. */

function renderCell(columnId: string, value: unknown) {
  const renderer = operationalSiteColumnRenderers[columnId]
  if (!renderer) {
    throw new Error(`Missing renderer for column "${columnId}"`)
  }
  const params = { value } as unknown as ICellRendererParams
  return render(<>{renderer(params)}</>)
}

describe.each(['city', 'street', 'postal_code', 'province', 'region'])(
  'operationalSiteColumnRenderers.%s',
  (columnId) => {
    it('renders the derived text value truncated with a title tooltip', () => {
      renderCell(columnId, 'A very long address value that could overflow the cell')
      const cell = screen.getByTitle('A very long address value that could overflow the cell')
      expect(cell).toBeInTheDocument()
      expect(cell.querySelector('span')).toHaveClass('truncate')
    })

    it('renders an em dash when the value is null', () => {
      renderCell(columnId, null)
      expect(screen.getByText('—')).toBeInTheDocument()
    })

    it('renders an em dash when the value is an empty string', () => {
      renderCell(columnId, '')
      expect(screen.getByText('—')).toBeInTheDocument()
    })
  },
)

describe('operationalSiteColumnRenderers.created_at', () => {
  it('renders the formatted datetime', () => {
    renderCell('created_at', '2026-01-15T10:30:00Z')
    expect(screen.getByText(/2026/)).toBeInTheDocument()
  })

  it('renders an em dash when missing', () => {
    renderCell('created_at', null)
    expect(screen.getByText('—')).toBeInTheDocument()
  })
})
