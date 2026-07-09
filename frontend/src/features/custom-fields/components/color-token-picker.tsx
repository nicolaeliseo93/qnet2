import { useCallback, useState } from 'react'
import { Popover as PopoverPrimitive } from 'radix-ui'
import { ChevronsUpDown, X } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { BADGE_COLOR_TOKENS, swatchClassFor } from '@/features/custom-fields/badge-color-tokens'
import { cn } from '@/lib/utils'

interface ColorTokenPickerProps {
  /** Stored palette token (e.g. `blue`), or empty string when unset. */
  value: string
  onChange: (token: string) => void
  disabled?: boolean
  className?: string
}

/**
 * Clickable swatch picker for an enum option's `color`. The value is a palette
 * TOKEN (not a hex): the grid badge maps it by name via `BADGE_COLOR_CLASSES`
 * (see {@link BADGE_COLOR_TOKENS}), so only these tokens are offered. A Popover
 * trigger shows the current swatch + localized name; the panel is a grid of the
 * palette swatches plus a clear action. Mirrors `IconPicker`: portals back into
 * the hosting sheet so scrolling stays inside the modal.
 */
export function ColorTokenPicker({ value, onChange, disabled, className }: ColorTokenPickerProps) {
  const { t } = useTranslation()
  const [open, setOpen] = useState(false)
  const [portalContainer, setPortalContainer] = useState<HTMLElement | null>(null)

  const setTrigger = useCallback((node: HTMLButtonElement | null) => {
    if (!node) {
      setPortalContainer(null)
      return
    }
    const modalContent = node.closest(
      '[data-slot="sheet-content"], [data-slot="dialog-content"]',
    )
    setPortalContainer(modalContent instanceof HTMLElement ? modalContent : null)
  }, [])

  const select = (token: string) => {
    onChange(token)
    setOpen(false)
  }

  const swatch = swatchClassFor(value)

  return (
    <div className={cn('flex items-center gap-2', className)}>
      <PopoverPrimitive.Root open={open} onOpenChange={setOpen}>
        <PopoverPrimitive.Trigger asChild>
          <button
            ref={setTrigger}
            type="button"
            disabled={disabled}
            aria-haspopup="dialog"
            aria-expanded={open}
            className="flex min-h-9 flex-1 items-center justify-between gap-2 rounded-md border border-input bg-transparent px-3 py-1.5 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50"
          >
            <span
              className={cn(
                'flex flex-1 items-center gap-2 truncate text-left',
                value === '' && 'text-muted-foreground',
              )}
            >
              <span
                className={cn(
                  'size-4 shrink-0 rounded-full border',
                  swatch ?? 'bg-transparent',
                )}
                aria-hidden="true"
              />
              <span className="truncate">
                {value ? t(`customFields.colors.${value}`) : t('customFields.form.colorPickerPlaceholder')}
              </span>
            </span>
            <ChevronsUpDown className="size-4 shrink-0 opacity-50" aria-hidden="true" />
          </button>
        </PopoverPrimitive.Trigger>

        <PopoverPrimitive.Portal container={portalContainer ?? undefined}>
          <PopoverPrimitive.Content
            align="start"
            sideOffset={4}
            className="z-50 w-(--radix-popover-trigger-width) min-w-56 rounded-md border bg-popover p-2 text-popover-foreground shadow-md outline-none"
          >
            <div role="listbox" className="grid grid-cols-7 gap-1.5">
              {BADGE_COLOR_TOKENS.map(({ token, swatch: dot }) => {
                const selected = token === value
                return (
                  <button
                    key={token}
                    type="button"
                    role="option"
                    aria-selected={selected}
                    aria-label={t(`customFields.colors.${token}`)}
                    title={t(`customFields.colors.${token}`)}
                    onClick={() => select(token)}
                    className={cn(
                      'flex aspect-square items-center justify-center rounded-md outline-none focus-visible:ring-[2px] focus-visible:ring-ring/50',
                      selected && 'ring-2 ring-ring ring-offset-1 ring-offset-popover',
                    )}
                  >
                    <span className={cn('size-5 rounded-full border', dot)} aria-hidden="true" />
                  </button>
                )
              })}
            </div>
          </PopoverPrimitive.Content>
        </PopoverPrimitive.Portal>
      </PopoverPrimitive.Root>

      {value && !disabled ? (
        <button
          type="button"
          onClick={() => onChange('')}
          aria-label={t('customFields.form.colorClear')}
          className="flex size-8 shrink-0 items-center justify-center rounded-md text-muted-foreground outline-none hover:bg-accent hover:text-foreground focus-visible:ring-[2px] focus-visible:ring-ring/50"
        >
          <X className="size-4" aria-hidden="true" />
        </button>
      ) : null}
    </div>
  )
}
