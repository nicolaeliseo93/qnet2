import { Badge } from '@/components/ui/badge'
import type { CustomFieldOption } from '@/features/custom-fields/types'

interface EnumBadgePickerProps {
  id: string
  describedBy: string
  invalid: boolean
  label: string
  options: CustomFieldOption[]
  value: string | null
  onChange: (value: string) => void
  disabled: boolean
}

/** `enum` field, `config.display: 'badge'`: a single-select picker of clickable `Badge`s. */
export function EnumBadgePicker({
  id,
  describedBy,
  invalid,
  label,
  options,
  value,
  onChange,
  disabled,
}: EnumBadgePickerProps) {
  return (
    <div
      id={id}
      role="radiogroup"
      aria-label={label}
      aria-describedby={describedBy}
      aria-invalid={invalid}
      className="flex flex-wrap gap-1.5"
    >
      {options.map((option) => {
        const selected = option.value === value
        return (
          <Badge key={option.value} variant={selected ? 'default' : 'outline'} asChild>
            <button
              type="button"
              role="radio"
              aria-checked={selected}
              disabled={disabled}
              onClick={() => onChange(option.value)}
            >
              {option.label}
            </button>
          </Badge>
        )
      })}
    </div>
  )
}
