import { useCallback, useEffect, useId, useMemo, useRef, useState } from 'react'
import { Popover as PopoverPrimitive } from 'radix-ui'
import { Check, ChevronsUpDown, Loader2 } from 'lucide-react'
import { Input } from '@/components/ui/input'
import { Skeleton } from '@/components/ui/skeleton'
import { cn } from '@/lib/utils'
import { useDebouncedValue } from '@/hooks/use-debounced-value'

/** A single, selectable option. Domain-agnostic: id + display name only. */
export interface SearchableSelectOption {
  id: number
  name: string
}

export interface SearchableSelectLabels {
  /** Shown in the trigger when nothing is selected. */
  placeholder: string
  /** Placeholder of the in-dropdown search input. */
  searchPlaceholder: string
  /** Shown when there are no options at all. */
  empty: string
  /** Shown when the search term matches no option. */
  noMatch: string
  /** Shown when the options fail to load. */
  error: string
  /** Retry action shown alongside the error. */
  retry: string
}

interface SearchableSelectProps {
  value: number | null
  onChange: (id: number) => void
  options: SearchableSelectOption[]
  labels: SearchableSelectLabels
  disabled?: boolean
  isPending?: boolean
  isError?: boolean
  onRetry?: () => void
  /**
   * When true (default), `options` are filtered client-side by the search term.
   * Set false when the caller narrows the list server-side (via
   * {@link onSearchChange}) and passes already-filtered options.
   */
  filter?: boolean
  /** Debounced search term, for callers that search server-side. */
  onSearchChange?: (term: string) => void
  /** Infinite scroll: whether another page can be loaded. */
  hasNextPage?: boolean
  /** Infinite scroll: whether the next page is currently loading. */
  isFetchingNextPage?: boolean
  /** Infinite scroll: load the next page (fired when the sentinel appears). */
  onLoadMore?: () => void
  className?: string
}

/**
 * Client-side searchable single-select: the non-`for-select` sibling of
 * {@link AsyncPaginatedSelect}. A radix Popover trigger opens a list with a
 * search input on top; by default the full `options` list is filtered
 * client-side as the user types. Callers whose data is capped/paged server-side
 * pass `filter={false}`, read the debounced term through `onSearchChange`, and
 * feed the infinite-scroll props.
 *
 * Loading, error and empty states live INSIDE the popover (never at the field
 * level) so a re-fetch on a new search term does not unmount the open dropdown.
 * Domain-agnostic (id + name only): the geo cascade and any future lookup select
 * share this one component instead of duplicating a searchable dropdown.
 */
export function SearchableSelect({
  value,
  onChange,
  options,
  labels,
  disabled,
  isPending = false,
  isError = false,
  onRetry,
  filter = true,
  onSearchChange,
  hasNextPage = false,
  isFetchingNextPage = false,
  onLoadMore,
  className,
}: SearchableSelectProps) {
  const [open, setOpen] = useState(false)
  const [search, setSearch] = useState('')
  const [portalContainer, setPortalContainer] = useState<HTMLElement | null>(null)
  const debouncedSearch = useDebouncedValue(search.trim())
  const listboxId = useId()
  const scrollRef = useRef<HTMLDivElement>(null)
  const observerRef = useRef<IntersectionObserver | null>(null)

  // Report the debounced term upward for server-side search callers.
  useEffect(() => {
    onSearchChange?.(debouncedSearch)
  }, [debouncedSearch, onSearchChange])

  const selected = useMemo(
    () => options.find((option) => option.id === value) ?? null,
    [options, value],
  )

  const visibleOptions = useMemo(() => {
    if (!filter) {
      return options
    }
    const term = search.trim().toLowerCase()
    if (term === '') {
      return options
    }
    return options.filter((option) => option.name.toLowerCase().includes(term))
  }, [filter, options, search])

  // Reset the search term whenever the popup closes so it reopens clean (and,
  // for server-side callers, clears the narrowing term).
  const handleOpenChange = useCallback((next: boolean) => {
    setOpen(next)
    if (!next) {
      setSearch('')
    }
  }, [])

  // Keep the latest paging state in a ref so the sentinel's callback ref can
  // read fresh values without re-attaching the observer on every render.
  const pagingRef = useRef({ hasNextPage, isFetchingNextPage, onLoadMore })
  pagingRef.current = { hasNextPage, isFetchingNextPage, onLoadMore }

  // Callback ref on the bottom sentinel: attaches an IntersectionObserver
  // exactly when the sentinel mounts (deferred until the popover portal opens)
  // and disconnects when it unmounts.
  const setSentinel = useCallback((node: HTMLDivElement | null) => {
    observerRef.current?.disconnect()
    observerRef.current = null
    if (!node) {
      return
    }
    const observer = new IntersectionObserver(
      (entries) => {
        const { hasNextPage: more, isFetchingNextPage: loading, onLoadMore: load } =
          pagingRef.current
        if (entries[0]?.isIntersecting && more && !loading) {
          load?.()
        }
      },
      { root: scrollRef.current, rootMargin: '0px 0px 80px 0px' },
    )
    observer.observe(node)
    observerRef.current = observer
  }, [])

  useEffect(() => () => observerRef.current?.disconnect(), [])

  // When the control lives inside a modal sheet/dialog, portal the popup back
  // into that content node so wheel/touch scrolling stays inside the modal's
  // allowed scroll tree instead of being blocked as "outside" content.
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

  const select = (id: number) => {
    onChange(id)
    handleOpenChange(false)
  }

  return (
    <PopoverPrimitive.Root open={open} onOpenChange={handleOpenChange}>
      <PopoverPrimitive.Trigger asChild>
        <button
          ref={setTrigger}
          type="button"
          role="combobox"
          disabled={disabled}
          aria-haspopup="listbox"
          aria-expanded={open}
          aria-controls={listboxId}
          className={cn(
            'flex min-h-9 w-full items-center justify-between gap-2 rounded-md border border-input bg-transparent px-3 py-1.5 text-sm shadow-xs transition-[color,box-shadow] outline-none focus-visible:border-ring focus-visible:ring-[3px] focus-visible:ring-ring/50 disabled:cursor-not-allowed disabled:opacity-50',
            className,
          )}
        >
          <span
            className={cn(
              'flex-1 truncate text-left',
              selected === null && 'text-muted-foreground',
            )}
          >
            {selected?.name ?? labels.placeholder}
          </span>
          <ChevronsUpDown
            className="size-4 shrink-0 opacity-50"
            aria-hidden="true"
          />
        </button>
      </PopoverPrimitive.Trigger>

      <PopoverPrimitive.Portal container={portalContainer ?? undefined}>
        <PopoverPrimitive.Content
          align="start"
          sideOffset={4}
          className="z-50 w-(--radix-popover-trigger-width) rounded-md border bg-popover p-1 text-popover-foreground shadow-md outline-none"
          onOpenAutoFocus={(event) => {
            // Keep focus on the search input rather than the first option.
            event.preventDefault()
          }}
        >
          <div className="p-1">
            <Input
              autoFocus
              value={search}
              onChange={(event) => setSearch(event.target.value)}
              placeholder={labels.searchPlaceholder}
              aria-label={labels.searchPlaceholder}
              aria-controls={listboxId}
            />
          </div>

          <div
            ref={scrollRef}
            id={listboxId}
            role="listbox"
            className="max-h-64 overflow-y-auto p-1"
          >
            {isPending ? (
              <OptionsSkeleton />
            ) : isError ? (
              <div className="flex flex-col items-center gap-2 px-2 py-6 text-center">
                <p className="text-sm text-muted-foreground">{labels.error}</p>
                {onRetry ? (
                  <button
                    type="button"
                    onClick={() => onRetry()}
                    className="text-sm font-medium text-primary underline-offset-4 hover:underline"
                  >
                    {labels.retry}
                  </button>
                ) : null}
              </div>
            ) : options.length === 0 ? (
              <p className="px-2 py-6 text-center text-sm text-muted-foreground">
                {labels.empty}
              </p>
            ) : visibleOptions.length === 0 ? (
              <p className="px-2 py-6 text-center text-sm text-muted-foreground">
                {labels.noMatch}
              </p>
            ) : (
              <>
                {visibleOptions.map((option) => {
                  const checked = option.id === value
                  return (
                    <div
                      key={option.id}
                      role="option"
                      aria-selected={checked}
                      tabIndex={0}
                      onClick={() => select(option.id)}
                      onKeyDown={(event) => {
                        if (event.key === 'Enter' || event.key === ' ') {
                          event.preventDefault()
                          select(option.id)
                        }
                      }}
                      className="flex cursor-pointer items-center gap-2 rounded-sm px-2 py-1.5 text-sm outline-none hover:bg-accent focus-visible:bg-accent focus-visible:ring-[2px] focus-visible:ring-ring/50"
                    >
                      <Check
                        className={cn(
                          'size-4 shrink-0',
                          checked ? 'opacity-100' : 'opacity-0',
                        )}
                        aria-hidden="true"
                      />
                      <span className="truncate">{option.name}</span>
                    </div>
                  )
                })}
                {isFetchingNextPage ? (
                  <div className="flex items-center justify-center py-2">
                    <Loader2
                      className="size-4 animate-spin text-muted-foreground"
                      aria-hidden="true"
                    />
                  </div>
                ) : null}
                {hasNextPage ? (
                  <div ref={setSentinel} aria-hidden="true" />
                ) : null}
              </>
            )}
          </div>
        </PopoverPrimitive.Content>
      </PopoverPrimitive.Portal>
    </PopoverPrimitive.Root>
  )
}

/** Skeleton shaped like a short list of options. */
function OptionsSkeleton() {
  return (
    <div className="space-y-1 p-1" data-testid="searchable-select-skeleton">
      {Array.from({ length: 5 }).map((_, index) => (
        <div key={index} className="flex items-center gap-2 px-2 py-1.5">
          <Skeleton className="size-4 shrink-0 rounded-sm" />
          <Skeleton className="h-3.5 w-[60%]" />
        </div>
      ))}
    </div>
  )
}
