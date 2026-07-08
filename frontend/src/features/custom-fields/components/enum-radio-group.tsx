import { useId } from 'react'
import { cn } from '@/lib/utils'
import type { CustomFieldOption } from '@/features/custom-fields/types'

interface EnumRadioGroupProps {
  id: string
  describedBy: string
  invalid: boolean
  label: string
  options: CustomFieldOption[]
  value: string | null
  onChange: (value: string) => void
  disabled: boolean
}

/**
 * `enum` field, `config.display: 'radio'`. There is no `radio-group.tsx` in
 * `components/ui/` (design-system ownership) to reuse, so this renders plain
 * native `<input type="radio">` elements — accessible by default, no new
 * dependency, no new shared UI primitive introduced from a feature slice.
 */
export function EnumRadioGroup({
  id,
  describedBy,
  invalid,
  label,
  options,
  value,
  onChange,
  disabled,
}: EnumRadioGroupProps) {
  const groupName = useId()

  return (
    <div
      id={id}
      role="radiogroup"
      aria-label={label}
      aria-describedby={describedBy}
      aria-invalid={invalid}
      className="flex flex-col gap-1.5"
    >
      {options.map((option) => (
        <label
          key={option.value}
          className={cn(
            'flex items-center gap-2 text-sm',
            disabled ? 'cursor-not-allowed opacity-50' : 'cursor-pointer',
          )}
        >
          <input
            type="radio"
            name={groupName}
            value={option.value}
            checked={value === option.value}
            disabled={disabled}
            onChange={() => onChange(option.value)}
            className="size-3.5"
          />
          {option.color ? (
            <span
              aria-hidden="true"
              className="size-2.5 shrink-0 rounded-full"
              style={{ backgroundColor: option.color }}
            />
          ) : null}
          {option.label}
        </label>
      ))}
    </div>
  )
}
