import { useState } from 'react'
import { describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import { DurationInput } from '@/features/users/duration-input'

const label = 'Standard daily duration'

/** Stateful wrapper so sequential edits build on the same live value, like a real form field. */
function ControlledDurationInput({ initial }: { initial: number | null }) {
  const [value, setValue] = useState(initial)
  return <DurationInput value={value} onChange={setValue} label={label} />
}

describe('DurationInput', () => {
  it('AC-017 — shows 480 minutes as 08:00', () => {
    render(<DurationInput value={480} onChange={vi.fn()} label={label} />)

    expect(screen.getByLabelText(label)).toHaveValue('08:00')
  })

  it('AC-017 — emits 480 total minutes for 08:00', () => {
    const onChange = vi.fn()
    render(<DurationInput value={null} onChange={onChange} label={label} />)

    fireEvent.change(screen.getByLabelText(label), { target: { value: '08:00' } })
    expect(onChange).toHaveBeenLastCalledWith(480)
  })

  it('emits total minutes including the minutes part (90 for 01:30)', () => {
    const onChange = vi.fn()
    render(<DurationInput value={null} onChange={onChange} label={label} />)

    fireEvent.change(screen.getByLabelText(label), { target: { value: '01:30' } })
    expect(onChange).toHaveBeenLastCalledWith(90)
  })

  it('renders a blank field when the value is null', () => {
    render(<DurationInput value={null} onChange={vi.fn()} label={label} />)

    expect(screen.getByLabelText(label)).toHaveValue('')
  })

  it('returns to null once the field is cleared', () => {
    const onChange = vi.fn()
    render(<ControlledDurationInput initial={90} />)

    fireEvent.change(screen.getByLabelText(label), { target: { value: '' } })
    expect(screen.getByLabelText(label)).toHaveValue('')
  })

  it('caps the displayed value at the 23:59 ceiling', () => {
    render(<DurationInput value={2000} onChange={vi.fn()} label={label} />)

    expect(screen.getByLabelText(label)).toHaveValue('23:59')
  })

  it('disables the control when disabled', () => {
    render(<DurationInput value={null} onChange={vi.fn()} label={label} disabled />)

    expect(screen.getByLabelText(label)).toBeDisabled()
  })
})
