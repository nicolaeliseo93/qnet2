import { beforeAll, describe, expect, it } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { StatsToggleButton } from '@/features/stats/stats-toggle-button'

/**
 * Spec 0026 — the toggle is icon-only: its accessible name comes from
 * `aria-label` (never from the tooltip, not reliably reachable by assistive
 * tech), while the tooltip gives sighted mouse users a state-aware hint.
 */

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('StatsToggleButton', () => {
  it('exposes an accessible name via aria-label with no visible text', () => {
    render(<StatsToggleButton domain="leads" isOpen={false} onToggle={() => {}} />)

    const toggle = screen.getByRole('button', { name: 'Statistics' })

    expect(toggle).toHaveAccessibleName('Statistics')
    expect(toggle).toHaveTextContent('')
  })

  it('wires aria-expanded/aria-controls to the panel it drives', () => {
    render(<StatsToggleButton domain="leads" isOpen onToggle={() => {}} />)

    const toggle = screen.getByRole('button', { name: 'Statistics' })

    expect(toggle).toHaveAttribute('aria-expanded', 'true')
    expect(toggle).toHaveAttribute('aria-controls', 'stats-panel-leads')
  })

  it('invokes onToggle on click', () => {
    let toggled = false
    render(<StatsToggleButton domain="leads" isOpen={false} onToggle={() => (toggled = true)} />)

    fireEvent.click(screen.getByRole('button', { name: 'Statistics' }))

    expect(toggled).toBe(true)
  })

  it('shows a tooltip hint that reflects the closed state', async () => {
    render(<StatsToggleButton domain="leads" isOpen={false} onToggle={() => {}} />)

    fireEvent.focus(screen.getByRole('button', { name: 'Statistics' }))

    expect(await screen.findByRole('tooltip')).toHaveTextContent('Show statistics')
  })

  it('shows a tooltip hint that reflects the open state', async () => {
    render(<StatsToggleButton domain="leads" isOpen onToggle={() => {}} />)

    fireEvent.focus(screen.getByRole('button', { name: 'Statistics' }))

    expect(await screen.findByRole('tooltip')).toHaveTextContent('Hide statistics')
  })
})
