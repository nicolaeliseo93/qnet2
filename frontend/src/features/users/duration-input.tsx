import type { ChangeEvent, ComponentProps } from 'react'
import { Input } from '@/components/ui/input'

/** Minutes in one hour, used for the total-minutes <-> HH:MM conversion. */
const MINUTES_PER_HOUR = 60
/**
 * Contract bound (spec 0015): a duration never reaches a full day. The native
 * time control tops out at 23:59, which is also the sensible ceiling for a
 * daily work/break duration — the schema's 1440 stays a harmless superset.
 */
const MAX_TOTAL_MINUTES = 23 * MINUTES_PER_HOUR + 59

interface DurationInputProps
  extends Omit<ComponentProps<typeof Input>, 'value' | 'onChange' | 'type'> {
  /** Total duration in minutes, or `null` when unset. */
  value: number | null
  /** Called with the next total in minutes, or `null` when the field is cleared. */
  onChange: (minutes: number | null) => void
  /** Accessible name for the single time control. */
  label: string
}

/** Two-digit zero-padded string for the HH and MM parts. */
function pad(value: number): string {
  return String(value).padStart(2, '0')
}

/** Total minutes -> "HH:MM" for the native time input; empty string when unset. */
function toTimeValue(value: number | null): string {
  if (value === null) return ''
  const clamped = Math.min(Math.max(value, 0), MAX_TOTAL_MINUTES)
  return `${pad(Math.floor(clamped / MINUTES_PER_HOUR))}:${pad(clamped % MINUTES_PER_HOUR)}`
}

/** "HH:MM" -> total minutes; `null` when the field is cleared. */
function fromTimeValue(raw: string): number | null {
  if (raw === '') return null
  const [rawHours, rawMinutes] = raw.split(':')
  const minutes = (Number(rawHours) || 0) * MINUTES_PER_HOUR + (Number(rawMinutes) || 0)
  return Math.min(Math.max(minutes, 0), MAX_TOTAL_MINUTES)
}

/**
 * Single HH:MM duration control backed by a total `minutes` value (spec 0015
 * stores durations as a plain integer, not a TIME column). A native
 * `type="time"` input renders the familiar "08:00" field/stepper; each edit
 * converts the HH:MM string back to total minutes. `null` represents "unset"
 * (blank field); clearing the field returns to `null`. Extra props (the id and
 * ARIA wired by `FormControl`) are forwarded onto the real input.
 */
export function DurationInput({ value, onChange, label, disabled, ...rest }: DurationInputProps) {
  const handleChange = (event: ChangeEvent<HTMLInputElement>) => {
    onChange(fromTimeValue(event.target.value))
  }

  return (
    <Input
      type="time"
      value={toTimeValue(value)}
      onChange={handleChange}
      disabled={disabled}
      aria-label={label}
      max={toTimeValue(MAX_TOTAL_MINUTES)}
      className="w-32"
      {...rest}
    />
  )
}
