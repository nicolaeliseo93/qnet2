import { describe, expect, it, beforeAll } from 'vitest'
import { useForm } from 'react-hook-form'
import { render, screen, fireEvent } from '@testing-library/react'
import i18n from '@/i18n'
import { Form } from '@/components/ui/form'
import { OpportunityPlanningSection } from '@/features/opportunities/opportunity-planning-section'
import type { OpportunityFormValues } from '@/features/opportunities/use-opportunity-form'

/** AC-096: success_probability is a 0..100 slider that always holds a value, shown in %. */

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

function Harness({ initial = 0 }: { initial?: number }) {
  const form = useForm<OpportunityFormValues>({
    defaultValues: {
      start_date: null,
      expected_close_date: null,
      estimated_value: null,
      success_probability: initial,
    },
  })
  return (
    <Form {...form}>
      <OpportunityPlanningSection control={form.control} />
    </Form>
  )
}

describe('OpportunityPlanningSection — success probability slider (AC-096)', () => {
  it('renders the probability as a 0..100 slider defaulting to 0%', () => {
    render(<Harness />)

    const slider = screen.getByRole('slider', { name: /probability/i })
    expect(slider).toHaveAttribute('aria-valuenow', '0')
    expect(slider).toHaveAttribute('aria-valuemin', '0')
    expect(slider).toHaveAttribute('aria-valuemax', '100')
    expect(screen.getByText('0%')).toBeInTheDocument()
  })

  it('updates the displayed value on keyboard interaction', () => {
    render(<Harness initial={40} />)

    const slider = screen.getByRole('slider', { name: /probability/i })
    expect(screen.getByText('40%')).toBeInTheDocument()

    fireEvent.keyDown(slider, { key: 'ArrowRight' })

    expect(screen.getByText('41%')).toBeInTheDocument()
    expect(slider).toHaveAttribute('aria-valuenow', '41')
  })
})
