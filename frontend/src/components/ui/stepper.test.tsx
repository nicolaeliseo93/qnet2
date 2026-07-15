import { describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'

import { Stepper, getStepStatus, type StepperStep } from '@/components/ui/stepper'

const STEPS: StepperStep[] = [
  { key: 'upload', label: 'Upload' },
  { key: 'config', label: 'Configuration' },
  { key: 'mapping', label: 'Mapping' },
  { key: 'review', label: 'Review' },
  { key: 'summary', label: 'Summary' },
]

describe('getStepStatus', () => {
  it('derives completed/current/upcoming from the index vs currentStep', () => {
    expect(getStepStatus(0, 2)).toBe('completed')
    expect(getStepStatus(2, 2)).toBe('current')
    expect(getStepStatus(3, 2)).toBe('upcoming')
  })
})

describe('Stepper', () => {
  it('marks the current step with aria-current="step" and no other step', () => {
    render(<Stepper steps={STEPS} currentStep={2} />)

    const current = screen.getByRole('button', { name: /Mapping/ })
    expect(current).toHaveAttribute('aria-current', 'step')

    const others = screen
      .getAllByRole('button')
      .filter((button) => button !== current)
    others.forEach((button) => expect(button).not.toHaveAttribute('aria-current'))
  })

  it('renders a check icon (not just color) for completed steps', () => {
    render(<Stepper steps={STEPS} currentStep={2} />)

    const uploadStep = screen.getByRole('button', { name: /Upload/ })
    expect(uploadStep.querySelector('svg')).toBeInTheDocument()

    const summaryStep = screen.getByRole('button', { name: /Summary/ })
    expect(summaryStep.querySelector('svg')).not.toBeInTheDocument()
    expect(summaryStep).toHaveTextContent('5')
  })

  it('calls onStepClick only for reached steps (completed or current), not upcoming ones', () => {
    const onStepClick = vi.fn()
    render(<Stepper steps={STEPS} currentStep={2} onStepClick={onStepClick} />)

    fireEvent.click(screen.getByRole('button', { name: /Upload/ }))
    expect(onStepClick).toHaveBeenCalledWith(0)

    fireEvent.click(screen.getByRole('button', { name: /Mapping/ }))
    expect(onStepClick).toHaveBeenCalledWith(2)

    onStepClick.mockClear()
    const reviewButton = screen.getByRole('button', { name: /Review/ })
    expect(reviewButton).toBeDisabled()
    fireEvent.click(reviewButton)
    expect(onStepClick).not.toHaveBeenCalled()
  })

  it('disables every step (no navigation) when onStepClick is not provided', () => {
    render(<Stepper steps={STEPS} currentStep={2} />)

    screen.getAllByRole('button').forEach((button) => expect(button).toBeDisabled())
  })
})
