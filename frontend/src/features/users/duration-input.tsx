import type { ChangeEvent } from 'react'
import { Input } from '@/components/ui/input'

/** Minutes in one hour, used for the total-minutes <-> hours/minutes split. */
const MINUTES_PER_HOUR = 60
/** Contract bound (spec 0015): a duration never exceeds a full day. */
const MAX_TOTAL_MINUTES = 1440
const MAX_HOURS = 24
const MAX_MINUTES = 59

export interface DurationInputLabels {
  hours: string
  minutes: string
}

interface DurationInputProps {
  /** Total duration in minutes, or `null` when unset. */
  value: number | null
  /** Called with the next total in minutes, or `null` when both parts are cleared. */
  onChange: (minutes: number | null) => void
  labels: DurationInputLabels
  disabled?: boolean
}

/** Clamps `value` to the inclusive `[min, max]` range. */
function clamp(value: number, min: number, max: number): number {
  return Math.min(Math.max(value, min), max)
}

/**
 * Hours/minutes duration control backed by a single `minutes` value (spec
 * 0015 stores durations as a plain integer, not a TIME column). Two small
 * number inputs edit the hours (0..24) and minutes (0..59) parts; each edit
 * recomputes and emits the clamped total. `null` represents "never touched"
 * (both parts render blank); once a real value exists, clearing both parts
 * converges to the explicit `0` (not back to `null`) — same distinction a
 * numeric input makes between an empty field and a typed `0`.
 */
export function DurationInput({ value, onChange, labels, disabled }: DurationInputProps) {
  const hours = value === null ? '' : String(Math.floor(value / MINUTES_PER_HOUR))
  const minutes = value === null ? '' : String(value % MINUTES_PER_HOUR)

  const commit = (rawHours: string, rawMinutes: string) => {
    if (rawHours === '' && rawMinutes === '') {
      onChange(null)
      return
    }
    const nextHours = clamp(Number(rawHours) || 0, 0, MAX_HOURS)
    const nextMinutes = clamp(Number(rawMinutes) || 0, 0, MAX_MINUTES)
    onChange(clamp(nextHours * MINUTES_PER_HOUR + nextMinutes, 0, MAX_TOTAL_MINUTES))
  }

  const handleHoursChange = (event: ChangeEvent<HTMLInputElement>) => {
    commit(event.target.value, minutes)
  }

  const handleMinutesChange = (event: ChangeEvent<HTMLInputElement>) => {
    commit(hours, event.target.value)
  }

  return (
    <div className="flex items-center gap-2">
      <Input
        type="number"
        inputMode="numeric"
        min={0}
        max={MAX_HOURS}
        step={1}
        value={hours}
        onChange={handleHoursChange}
        disabled={disabled}
        aria-label={labels.hours}
        className="w-20"
      />
      <span className="text-sm text-muted-foreground" aria-hidden="true">
        :
      </span>
      <Input
        type="number"
        inputMode="numeric"
        min={0}
        max={MAX_MINUTES}
        step={1}
        value={minutes}
        onChange={handleMinutesChange}
        disabled={disabled}
        aria-label={labels.minutes}
        className="w-20"
      />
    </div>
  )
}
