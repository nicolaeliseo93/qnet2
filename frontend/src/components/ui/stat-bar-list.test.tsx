import { describe, expect, it } from 'vitest'
import { render, screen, within } from '@testing-library/react'

import { StatBarList, type StatBarItem } from '@/components/ui/stat-bar-list'

const ITEMS: StatBarItem[] = [
  { key: 'web', label: 'Web', value: 51, color: '#22c55e' },
  { key: 'phone', label: 'Phone', value: 25, color: null },
]

const getMeters = () => screen.getAllByRole('meter')
const getBarFill = (meter: HTMLElement) => meter.firstElementChild as HTMLElement

describe('StatBarList', () => {
  it('renders the title as a heading and one list item per entry', () => {
    render(<StatBarList title="By source" items={ITEMS} total={100} />)

    expect(screen.getByRole('heading', { name: 'By source' })).toBeInTheDocument()
    expect(within(screen.getByRole('list')).getAllByRole('listitem')).toHaveLength(2)
  })

  it('exposes each bar as a labelled meter carrying the value and the percentage', () => {
    render(<StatBarList title="By source" items={ITEMS} total={100} />)

    const [web, phone] = getMeters()

    expect(web).toHaveAccessibleName('Web')
    expect(web).toHaveAttribute('aria-valuenow', '51')
    expect(web).toHaveAttribute('aria-valuemin', '0')
    expect(web).toHaveAttribute('aria-valuemax', '100')
    expect(web).toHaveAttribute('aria-valuetext', '51 (51%)')
    expect(phone).toHaveAccessibleName('Phone')
    expect(phone).toHaveAttribute('aria-valuenow', '25')
  })

  it('writes the value and the percentage as text, not just as color', () => {
    render(<StatBarList title="By source" items={ITEMS} total={100} />)

    expect(screen.getByText('51')).toBeInTheDocument()
    expect(screen.getByText('51%')).toBeInTheDocument()
    expect(screen.getByText('25%')).toBeInTheDocument()
  })

  it('sizes the bar proportionally and falls back to the theme color', () => {
    render(<StatBarList title="By source" items={ITEMS} total={100} />)

    const [web, phone] = getMeters()

    expect(getBarFill(web)).toHaveStyle({ width: '51%', backgroundColor: '#22c55e' })
    expect(getBarFill(phone)).toHaveStyle({ width: '25%', backgroundColor: 'var(--chart-1)' })
  })

  it('renders 0% without dividing by zero when the total is 0', () => {
    render(
      <StatBarList
        title="By source"
        items={[{ key: 'web', label: 'Web', value: 0 }]}
        total={0}
      />
    )

    const [meter] = getMeters()

    expect(meter).toHaveAttribute('aria-valuenow', '0')
    expect(meter).toHaveAttribute('aria-valuetext', '0 (0%)')
    expect(getBarFill(meter)).toHaveStyle({ width: '0%' })
    expect(screen.getByText('0%')).toBeInTheDocument()
  })

  it('clamps a value larger than the total to 100%', () => {
    render(
      <StatBarList title="By source" items={[{ key: 'web', label: 'Web', value: 12 }]} total={5} />
    )

    expect(getMeters()[0]).toHaveAttribute('aria-valuenow', '100')
    expect(getBarFill(getMeters()[0])).toHaveStyle({ width: '100%' })
  })

  it('applies formatValue to the displayed value and to the meter text', () => {
    render(
      <StatBarList
        title="Budget"
        items={[{ key: 'web', label: 'Web', value: 1200 }]}
        total={2400}
        formatValue={(value) => `${value} EUR`}
      />
    )

    expect(screen.getByText('1200 EUR')).toBeInTheDocument()
    expect(getMeters()[0]).toHaveAttribute('aria-valuetext', '1200 EUR (50%)')
  })

  it('shows a discreet placeholder and no meters when the list is empty', () => {
    render(<StatBarList title="By source" items={[]} total={0} emptyLabel="No data" />)

    expect(screen.getByText('No data')).toBeInTheDocument()
    expect(screen.queryAllByRole('meter')).toHaveLength(0)
    expect(screen.queryByRole('list')).not.toBeInTheDocument()
  })

  it('truncates long labels instead of overflowing the card', () => {
    render(
      <StatBarList
        title="By source"
        items={[
          {
            key: 'long',
            label: 'A very long distribution label that would otherwise overflow the card',
            value: 3,
          },
        ]}
        total={10}
      />
    )

    const label = screen.getByText(/A very long distribution label/)

    expect(label).toHaveClass('truncate')
    expect(label).toHaveClass('min-w-0')
  })
})
