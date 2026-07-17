import { render, screen } from '@testing-library/react'
import type { ICellRendererParams } from 'ag-grid-community'
import { beforeAll, describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import { vatRateColumnRenderers } from '@/features/vat-rates/column-renderers'

/** The `rate` cell formats a percentage; em dash when null/invalid. */

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

function renderCell(columnId: string, value: unknown) {
  const renderer = vatRateColumnRenderers[columnId]
  if (!renderer) {
    throw new Error(`Missing renderer for column "${columnId}"`)
  }
  const params = { value } as unknown as ICellRendererParams
  return render(<>{renderer(params)}</>)
}

describe('vatRateColumnRenderers.rate', () => {
  it('formats a decimal rate to two fraction digits with a percent sign', () => {
    renderCell('rate', 22)
    expect(screen.getByText('22.00%')).toBeInTheDocument()
  })

  it('formats a numeric-string rate (decimal:2 cast)', () => {
    renderCell('rate', '10.00')
    expect(screen.getByText('10.00%')).toBeInTheDocument()
  })

  it('renders an em dash when null', () => {
    renderCell('rate', null)
    expect(screen.getByText('—')).toBeInTheDocument()
  })
})
