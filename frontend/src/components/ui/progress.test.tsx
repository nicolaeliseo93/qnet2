import { describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'

import { Progress } from '@/components/ui/progress'

describe('Progress', () => {
  it('exposes the accessible progressbar role with the correct aria-value bounds', () => {
    render(<Progress value={40} aria-label="Import progress" />)

    const bar = screen.getByRole('progressbar', { name: 'Import progress' })

    expect(bar).toHaveAttribute('aria-valuenow', '40')
    expect(bar).toHaveAttribute('aria-valuemin', '0')
    expect(bar).toHaveAttribute('aria-valuemax', '100')
  })

  it('renders the indicator fill proportional to value/max', () => {
    render(<Progress value={25} aria-label="Progress" />)

    const bar = screen.getByRole('progressbar')
    const fill = bar.firstElementChild as HTMLElement

    expect(fill).toHaveStyle({ transform: 'translateX(-75%)' })
  })

  it('clamps a value above max to 100%', () => {
    render(<Progress value={999} max={100} aria-label="Progress" />)

    const bar = screen.getByRole('progressbar')
    const fill = bar.firstElementChild as HTMLElement

    expect(fill).toHaveStyle({ transform: 'translateX(-0%)' })
  })

  it('renders a 0% fill when value is not provided (indeterminate-safe default)', () => {
    render(<Progress aria-label="Progress" />)

    const bar = screen.getByRole('progressbar')
    const fill = bar.firstElementChild as HTMLElement

    expect(fill).toHaveStyle({ transform: 'translateX(-100%)' })
  })
})
