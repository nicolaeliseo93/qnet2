import { describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import { Building2 } from 'lucide-react'
import { FormSection } from '@/components/form-section'

/**
 * `collapsible` defaults to false, reproducing today's static-section markup
 * byte-for-byte (no toggle, body always mounted). When `collapsible` is true
 * the header becomes a `Collapsible` trigger; jsdom never plays the CSS
 * animation the content is classed with, so Radix `Presence` mounts/unmounts
 * the body synchronously with the open state (see
 * `features/stats/module-stats-panel.test.tsx` for the same reasoning).
 */

describe('FormSection', () => {
  it('renders the static section with no toggle when collapsible is not set', () => {
    render(
      <FormSection icon={Building2} title="General">
        <p>content</p>
      </FormSection>,
    )

    expect(screen.getByText('content')).toBeInTheDocument()
    expect(screen.queryByRole('button')).not.toBeInTheDocument()
  })

  it('collapsible, uncontrolled, defaults open and collapses on click', () => {
    render(
      <FormSection icon={Building2} title="General" collapsible>
        <p>content</p>
      </FormSection>,
    )

    expect(screen.getByText('content')).toBeInTheDocument()
    const trigger = screen.getByRole('button', { name: 'General' })
    expect(trigger).toHaveAttribute('data-state', 'open')

    fireEvent.click(trigger)

    expect(trigger).toHaveAttribute('data-state', 'closed')
    expect(screen.queryByText('content')).not.toBeInTheDocument()
  })

  it('collapsible with defaultOpen=false starts closed and opens on click', () => {
    render(
      <FormSection icon={Building2} title="General" collapsible defaultOpen={false}>
        <p>content</p>
      </FormSection>,
    )

    expect(screen.queryByText('content')).not.toBeInTheDocument()
    const trigger = screen.getByRole('button', { name: 'General' })
    expect(trigger).toHaveAttribute('data-state', 'closed')

    fireEvent.click(trigger)

    expect(trigger).toHaveAttribute('data-state', 'open')
    expect(screen.getByText('content')).toBeInTheDocument()
  })

  it('controlled: clicking the trigger calls onOpenChange, state is driven by the caller', () => {
    const onOpenChange = vi.fn()
    const { rerender } = render(
      <FormSection
        icon={Building2}
        title="General"
        collapsible
        open
        onOpenChange={onOpenChange}
      >
        <p>content</p>
      </FormSection>,
    )

    expect(screen.getByText('content')).toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: 'General' }))

    expect(onOpenChange).toHaveBeenCalledWith(false)
    // Uncontrolled Radix state would have flipped already; a truly controlled
    // consumer must re-render with the new `open` prop for it to take effect.
    expect(screen.getByText('content')).toBeInTheDocument()

    rerender(
      <FormSection
        icon={Building2}
        title="General"
        collapsible
        open={false}
        onOpenChange={onOpenChange}
      >
        <p>content</p>
      </FormSection>,
    )

    expect(screen.queryByText('content')).not.toBeInTheDocument()
  })
})
