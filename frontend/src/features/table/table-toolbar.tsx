import { type ReactNode, type RefObject } from 'react'
import { useTranslation } from 'react-i18next'
import {
  FilterX,
  Maximize2,
  Minimize2,
  MoreHorizontal,
  RotateCcw,
  Search,
  X,
} from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Separator } from '@/components/ui/separator'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuLabel,
  DropdownMenuSeparator,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {
  Tooltip,
  TooltipContent,
  TooltipTrigger,
} from '@/components/ui/tooltip'

interface TableToolbarProps {
  /** Whether the domain exposes a global quick-search (config `searchable`). */
  searchEnabled: boolean
  /** Placeholder built from the searchable columns' labels. */
  searchPlaceholder: string
  /** Ref to the search input so the ⌘K shortcut can focus it. */
  searchInputRef: RefObject<HTMLInputElement | null>
  searchValue: string
  onSearchChange: (value: string) => void
  /** Localized keyboard-shortcut hint shown in the search field (⌘K / Ctrl K). */
  searchShortcut: string
  /** Total known row count, or null before the first load. */
  rowCount: number | null
  /** Whether any column filter is active (drives the reset-filters affordance). */
  filtersActive: boolean
  onResetFilters: () => void
  resettingFilters: boolean
  /** Whether the column layout is customized (drives the reset-layout option). */
  layoutCustomized: boolean
  onResetLayout: () => void
  resettingLayout: boolean
  /** Fullscreen state + its toggle. */
  fullscreen: boolean
  onToggleFullscreen: () => void
  /** Saved-views control (rendered by the caller, which owns the grid API). */
  savedViewsSlot?: ReactNode
}

interface IconButtonProps {
  icon: ReactNode
  label: string
  onClick: () => void
  disabled?: boolean
}

/** A ghost, icon-only toolbar button with a tooltip (the toolbar's base unit). */
function IconButton({ icon, label, onClick, disabled }: IconButtonProps) {
  return (
    <Tooltip>
      <TooltipTrigger asChild>
        <Button
          type="button"
          variant="ghost"
          size="icon-sm"
          aria-label={label}
          disabled={disabled}
          onClick={onClick}
          className="text-muted-foreground hover:text-foreground"
        >
          {icon}
        </Button>
      </TooltipTrigger>
      <TooltipContent>{label}</TooltipContent>
    </Tooltip>
  )
}

/**
 * The unified table header (spec 0009): a single bar fused to the top of the
 * grid. Left holds the global quick-search + a live row counter; right holds the
 * icon controls — floating-filter toggle, reset-filters, saved views, an options
 * menu (export, reset layout) and fullscreen. Purely presentational: every piece
 * of state and every handler is owned by the caller (TableView).
 */
export function TableToolbar({
  searchEnabled,
  searchPlaceholder,
  searchInputRef,
  searchValue,
  onSearchChange,
  searchShortcut,
  rowCount,
  filtersActive,
  onResetFilters,
  resettingFilters,
  layoutCustomized,
  onResetLayout,
  resettingLayout,
  fullscreen,
  onToggleFullscreen,
  savedViewsSlot,
}: TableToolbarProps) {
  const { t } = useTranslation()

  return (
    <div className="flex items-center gap-2 border-b border-border bg-card px-2.5 py-2">
      {/* Left: search + live row count */}
      <div className="flex min-w-0 flex-1 items-center gap-3">
        {searchEnabled ? (
          <div className="relative w-full max-w-xs">
            <Search
              aria-hidden="true"
              className="pointer-events-none absolute left-2.5 top-1/2 size-4 -translate-y-1/2 text-muted-foreground"
            />
            <Input
              ref={searchInputRef}
              type="search"
              value={searchValue}
              onChange={(event) => onSearchChange(event.target.value)}
              placeholder={searchPlaceholder}
              aria-label={searchPlaceholder}
              autoComplete="off"
              className="h-9 border-transparent bg-muted/60 pl-8 pr-14 shadow-none focus-visible:border-ring focus-visible:bg-card [&::-webkit-search-cancel-button]:hidden"
            />
            {searchValue ? (
              <button
                type="button"
                onClick={() => onSearchChange('')}
                aria-label={t('common.clear')}
                className="absolute right-2 top-1/2 flex size-5 -translate-y-1/2 items-center justify-center rounded-sm text-muted-foreground transition-colors hover:bg-accent hover:text-foreground"
              >
                <X aria-hidden="true" className="size-3.5" />
              </button>
            ) : (
              <kbd className="pointer-events-none absolute right-2 top-1/2 hidden -translate-y-1/2 select-none items-center gap-0.5 rounded border bg-background px-1.5 font-mono text-[10px] font-medium text-muted-foreground sm:inline-flex">
                {searchShortcut}
              </kbd>
            )}
          </div>
        ) : null}

        {rowCount != null ? (
          <span className="hidden shrink-0 whitespace-nowrap text-xs font-medium text-muted-foreground sm:inline">
            {t('table.rowCount', { count: rowCount })}
          </span>
        ) : null}
      </div>

      {/* Right: icon controls */}
      <div className="flex shrink-0 items-center gap-0.5">
        {filtersActive ? (
          <IconButton
            icon={<FilterX aria-hidden="true" />}
            label={t('table.resetFilters')}
            onClick={onResetFilters}
            disabled={resettingFilters}
          />
        ) : null}

        {savedViewsSlot}

        <DropdownMenu>
          <Tooltip>
            <TooltipTrigger asChild>
              <DropdownMenuTrigger asChild>
                <Button
                  type="button"
                  variant="ghost"
                  size="icon-sm"
                  aria-label={t('table.options')}
                  className="text-muted-foreground hover:text-foreground"
                >
                  <MoreHorizontal aria-hidden="true" />
                </Button>
              </DropdownMenuTrigger>
            </TooltipTrigger>
            <TooltipContent>{t('table.options')}</TooltipContent>
          </Tooltip>

          <DropdownMenuContent align="end" className="w-56">
            <DropdownMenuLabel className="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
              {t('table.options')}
            </DropdownMenuLabel>

            <DropdownMenuItem disabled className="justify-between gap-2">
              {t('table.export')}
              <span className="rounded-full bg-muted px-1.5 py-0.5 text-[10px] font-medium text-muted-foreground">
                {t('common.soon')}
              </span>
            </DropdownMenuItem>

            {layoutCustomized ? (
              <>
                <DropdownMenuSeparator />
                <DropdownMenuItem
                  disabled={resettingLayout}
                  onSelect={(event) => {
                    event.preventDefault()
                    onResetLayout()
                  }}
                >
                  <RotateCcw aria-hidden="true" />
                  {t('table.resetLayout')}
                </DropdownMenuItem>
              </>
            ) : null}
          </DropdownMenuContent>
        </DropdownMenu>

        <Separator orientation="vertical" className="mx-0.5 h-5" />

        <IconButton
          icon={
            fullscreen ? (
              <Minimize2 aria-hidden="true" />
            ) : (
              <Maximize2 aria-hidden="true" />
            )
          }
          label={t(fullscreen ? 'table.exitFullscreen' : 'table.fullscreen')}
          onClick={onToggleFullscreen}
        />
      </div>
    </div>
  )
}
