import { useCallback, useMemo, useState } from 'react'
import { Popover as PopoverPrimitive } from 'radix-ui'
import { ChevronsUpDown, X } from 'lucide-react'
import { Input } from '@/components/ui/input'
import { DynamicIcon } from '@/features/custom-fields/dynamic-icon'
import { ICON_NAMES } from '@/features/custom-fields/icon-catalog'
import { cn } from '@/lib/utils'

export interface IconPickerLabels {
  /** Trigger text when no icon is selected. */
  placeholder: string
  /** Placeholder of the in-popover search box. */
  searchPlaceholder: string
  /** Shown when the search term matches no icon. */
  empty: string
  /** Accessible label of the inline "clear selection" button. */
  clearLabel: string
}

interface IconPickerProps {
  /** Canonical kebab-case lucide name, or empty string when unset. */
  value: string
  onChange: (name: string) => void
  labels: IconPickerLabels
  disabled?: boolean
  readOnly?: boolean
  id?: string
  describedBy?: string
  invalid?: boolean
  className?: string
}

/**
 * Searchable lucide icon picker: a Popover trigger showing the current glyph +
 * name opens a grid of the curated {@link ICON_NAMES}, filtered live by a search
 * box. Replaces the free-text `icon` input on the custom-field definition form
 * (and each enum option) so admins pick a real glyph instead of guessing a
 * lucide name. Mirrors `SearchableSelect`'s conventions: portals back into the
 * hosting sheet/dialog so wheel/touch scrolling stays inside the modal.
 */
export function IconPicker({
  value,
  onChange,
  labels,
  disabled,
  readOnly,
  id,
  describedBy,
  invalid,
  className,
}: IconPickerProps) {
  const [open, setOpen] = useState(false)
  const [search, setSearch] = useState('')
  const [portalContainer, setPortalContainer] = useState<HTMLElement | null>(null)

  const visibleNames = useMemo(() => {
    const term = search.trim().toLowerCase()
    if (term === '') {
      return ICON_NAMES
    }
    return ICON_NAMES.filter((name) => name.includes(term))
  }, [search])

  const handleOpenChange = useCallback((next: boolean) => {
    setOpen(next)
    if (!next) {
      setSearch('')
    }
  }, [])

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

  const select = (name: string) => {
    onChange(name)
    handleOpenChange(false)
  }

  const locked = disabled || readOnly

  return (
    <div className={cn('flex items-center gap-2', className)}>
      <PopoverPrimitive.Root open={open} onOpenChange={handleOpenChange}>
        <PopoverPrimitive.Trigger asChild>
          <button
            ref={setTrigger}
            type="button"
            id={id}
            disabled={locked}
            aria-haspopup="dialog"
            aria-expanded={open}
            aria-describedby={describedBy}
            aria-invalid={invalid}
            className={cn(
              'flex min-h-9 flex-1 items-center justify-between gap-2 rounded-md border border-input bg-transparent px-3 py-1.5 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50 aria-invalid:border-destructive aria-invalid:ring-destructive/20',
            )}
          >
            <span
              className={cn(
                'flex flex-1 items-center gap-2 truncate text-left',
                value === '' && 'text-muted-foreground',
              )}
            >
              {value ? (
                <DynamicIcon name={value} className="size-4 shrink-0" />
              ) : null}
              <span className="truncate">{value || labels.placeholder}</span>
            </span>
            <ChevronsUpDown className="size-4 shrink-0 opacity-50" aria-hidden="true" />
          </button>
        </PopoverPrimitive.Trigger>

        <PopoverPrimitive.Portal container={portalContainer ?? undefined}>
          <PopoverPrimitive.Content
            align="start"
            sideOffset={4}
            className="z-50 w-(--radix-popover-trigger-width) min-w-64 rounded-md border bg-popover p-1 text-popover-foreground shadow-md outline-none"
            onOpenAutoFocus={(event) => event.preventDefault()}
          >
            <div className="p-1">
              <Input
                autoFocus
                value={search}
                onChange={(event) => setSearch(event.target.value)}
                placeholder={labels.searchPlaceholder}
                aria-label={labels.searchPlaceholder}
              />
            </div>

            {visibleNames.length === 0 ? (
              <p className="px-2 py-6 text-center text-sm text-muted-foreground">
                {labels.empty}
              </p>
            ) : (
              <div
                role="listbox"
                className="grid max-h-64 grid-cols-6 gap-1 overflow-y-auto p-1"
              >
                {visibleNames.map((name) => {
                  const selected = name === value
                  return (
                    <button
                      key={name}
                      type="button"
                      role="option"
                      aria-selected={selected}
                      aria-label={name}
                      title={name}
                      onClick={() => select(name)}
                      className={cn(
                        'flex aspect-square items-center justify-center rounded-sm text-muted-foreground outline-none hover:bg-accent hover:text-foreground focus-visible:bg-accent focus-visible:ring-[2px] focus-visible:ring-ring/50',
                        selected && 'bg-primary/10 text-primary ring-1 ring-primary/40',
                      )}
                    >
                      <DynamicIcon name={name} className="size-4" />
                    </button>
                  )
                })}
              </div>
            )}
          </PopoverPrimitive.Content>
        </PopoverPrimitive.Portal>
      </PopoverPrimitive.Root>

      {value && !locked ? (
        <button
          type="button"
          onClick={() => onChange('')}
          aria-label={labels.clearLabel}
          className="flex size-8 shrink-0 items-center justify-center rounded-md text-muted-foreground outline-none hover:bg-accent hover:text-foreground focus-visible:ring-[2px] focus-visible:ring-ring/50"
        >
          <X className="size-4" aria-hidden="true" />
        </button>
      ) : null}
    </div>
  )
}
