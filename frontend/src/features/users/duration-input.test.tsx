import { useState } from 'react'
import { describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import { DurationInput } from '@/features/users/duration-input'

const labels = { hours: 'Hours', minutes: 'Minutes' }

/** Stateful wrapper so two sequential edits build on the same live value, like a real form field. */
function ControlledDurationInput({ initial }: { initial: number | null }) {
  const [value, setValue] = useState(initial)
  return <DurationInput value={value} onChange={setValue} labels={labels} />
}

describe('DurationInput', () => {
  it('AC-017 — shows 480 minutes as 8 hours / 0 minutes', () => {
    render(<DurationInput value={480} onChange={vi.fn()} labels={labels} />)

    expect(screen.getByLabelText('Hours')).toHaveValue(8)
    expect(screen.getByLabelText('Minutes')).toHaveValue(0)
  })

  it('AC-017 — emits 480 total minutes for 8h 0m', () => {
    const onChange = vi.fn()
    render(<DurationInput value={null} onChange={onChange} labels={labels} />)

    fireEvent.change(screen.getByLabelText('Hours'), { target: { value: '8' } })
    expect(onChange).toHaveBeenLastCalledWith(480)
  })

  it('renders blank inputs when the value is null', () => {
    render(<DurationInput value={null} onChange={vi.fn()} labels={labels} />)

    expect(screen.getByLabelText('Hours')).toHaveValue(null)
    expect(screen.getByLabelText('Minutes')).toHaveValue(null)
  })

  it('converges to 0 (not null) once a set value is cleared part by part', () => {
    // `null` only represents "never touched"; once a real value exists,
    // clearing both parts lands on the explicit "0 minutes" value.
    render(<ControlledDurationInput initial={90} />)

    fireEvent.change(screen.getByLabelText('Hours'), { target: { value: '' } })
    fireEvent.change(screen.getByLabelText('Minutes'), { target: { value: '' } })
    expect(screen.getByLabelText('Hours')).toHaveValue(0)
    expect(screen.getByLabelText('Minutes')).toHaveValue(0)
  })

  it('clamps an out-of-range hours part to the 24h/1440min ceiling', () => {
    const onChange = vi.fn()
    render(<DurationInput value={0} onChange={onChange} labels={labels} />)

    fireEvent.change(screen.getByLabelText('Hours'), { target: { value: '99' } })
    // 99h clamped to 24h -> 1440 total minutes (the contract's max).
    expect(onChange).toHaveBeenLastCalledWith(1440)
  })

  it('clamps an out-of-range minutes part to 59', () => {
    const onChange = vi.fn()
    render(<DurationInput value={0} onChange={onChange} labels={labels} />)

    fireEvent.change(screen.getByLabelText('Minutes'), { target: { value: '99' } })
    expect(onChange).toHaveBeenLastCalledWith(59)
  })

  it('disables both parts when disabled', () => {
    render(<DurationInput value={null} onChange={vi.fn()} labels={labels} disabled />)

    expect(screen.getByLabelText('Hours')).toBeDisabled()
    expect(screen.getByLabelText('Minutes')).toBeDisabled()
  })
})
