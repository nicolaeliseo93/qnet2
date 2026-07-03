import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Bookmark, BookmarkPlus, Check, Lock, Trash2, Users } from 'lucide-react'
import axios from 'axios'
import { toast } from 'sonner'
import { cn } from '@/lib/utils'
import { Button } from '@/components/ui/button'
import { useConfirm } from '@/components/confirm-dialog-context'
import { Input } from '@/components/ui/input'
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
import { useCreateFilterView, useDeleteFilterView, useFilterViews } from '@/features/table/use-filter-views'
import type { FilterViewVisibility, TableFilterView } from '@/features/table/types'

/** Server-side max length for a saved filter view's name (spec 0007). */
const VIEW_NAME_MAX_LENGTH = 80

interface FilterViewsControlProps {
  /** Domain key selecting the server-side table definition (e.g. "users"). */
  domain: string
  /** Current AG Grid filterModel, saved verbatim as a new view's filters. */
  currentFilters: Record<string, unknown>
  /** Applies a saved view's filters to the grid (the caller wires `setFilterModel`). */
  onApply: (filters: Record<string, unknown>) => void
}

/**
 * Order-independent equality of two filter models: same keys, same value per
 * key. Lets the panel flag which saved view is the one currently applied.
 */
function sameFilters(a: Record<string, unknown>, b: Record<string, unknown>): boolean {
  const keys = Object.keys(a)
  if (keys.length !== Object.keys(b).length) {
    return false
  }
  return keys.every((key) => key in b && JSON.stringify(a[key]) === JSON.stringify(b[key]))
}

interface FilterViewRowProps {
  view: TableFilterView
  active: boolean
  onApply: (filters: Record<string, unknown>) => void
  onDelete: (view: TableFilterView) => void
}

/**
 * One saved view row: a leading lock/people glyph telegraphs private vs shared
 * at a glance, the name applies the view on click, an owner-only trash button
 * (revealed on hover/focus) deletes it. Apply and delete are two sibling
 * `DropdownMenuItem`s so each stays independently keyboard/AT operable.
 */
function FilterViewRow({ view, active, onApply, onDelete }: FilterViewRowProps) {
  const { t } = useTranslation()
  const VisibilityIcon = view.visibility === 'shared' ? Users : Lock

  return (
    <div className="group/row flex items-center gap-1">
      <DropdownMenuItem
        className={cn('min-w-0 flex-1 gap-2', active && 'bg-accent/60')}
        title={t('table.applyView')}
        onSelect={() => onApply(view.filters)}
      >
        <VisibilityIcon aria-hidden="true" className="text-muted-foreground" />
        <span className="truncate">{view.name}</span>
        <span className="ml-auto flex shrink-0 items-center gap-1.5">
          {active ? (
            <Check aria-label={t('table.viewActive')} className="size-3.5 text-primary" />
          ) : null}
          {!view.owned && view.owner_name ? (
            <span className="truncate text-xs text-muted-foreground">
              {t('table.sharedBy', { name: view.owner_name })}
            </span>
          ) : null}
        </span>
      </DropdownMenuItem>
      {view.owned ? (
        <DropdownMenuItem
          variant="destructive"
          className="shrink-0 px-2 opacity-0 transition-opacity group-hover/row:opacity-100 focus:opacity-100"
          aria-label={t('table.deleteView')}
          onSelect={() => onDelete(view)}
        >
          <Trash2 aria-hidden="true" />
        </DropdownMenuItem>
      ) : null}
    </div>
  )
}

interface VisibilityOptionProps {
  value: FilterViewVisibility
  active: boolean
  icon: typeof Lock
  label: string
  onSelect: (value: FilterViewVisibility) => void
}

/** One segment of the private/shared segmented control. */
function VisibilityOption({ value, active, icon: Icon, label, onSelect }: VisibilityOptionProps) {
  return (
    <button
      type="button"
      aria-pressed={active}
      onClick={() => onSelect(value)}
      className={cn(
        'flex items-center justify-center gap-1.5 rounded-[5px] px-2 py-1 text-xs font-medium transition-colors',
        active
          ? 'bg-background text-foreground shadow-sm'
          : 'text-muted-foreground hover:text-foreground',
      )}
    >
      <Icon aria-hidden="true" className="size-3.5" />
      {label}
    </button>
  )
}

/**
 * Toolbar control that both LISTS the actor's saved filter views (own + others'
 * shared, grouped) and SAVES the current filter set — all inline in one panel,
 * no modal. Applying/deleting are pure wiring (the grid mutation lives in the
 * caller via `onApply`); saving posts the caller-supplied `currentFilters`.
 */
export function FilterViewsControl({ domain, currentFilters, onApply }: FilterViewsControlProps) {
  const { t } = useTranslation()
  const confirm = useConfirm()
  const { data: views } = useFilterViews(domain)
  const createView = useCreateFilterView(domain)
  const deleteView = useDeleteFilterView(domain)

  const [open, setOpen] = useState(false)
  const [name, setName] = useState('')
  const [visibility, setVisibility] = useState<FilterViewVisibility>('private')

  const ownedViews = (views ?? []).filter((view) => view.owned)
  const sharedViews = (views ?? []).filter((view) => !view.owned)
  const hasViews = ownedViews.length > 0 || sharedViews.length > 0
  const count = ownedViews.length + sharedViews.length

  const hasFilters = Object.keys(currentFilters).length > 0
  const canSave = name.trim().length > 0 && hasFilters && !createView.isPending

  const resetForm = () => {
    setName('')
    setVisibility('private')
  }

  const handleOpenChange = (next: boolean) => {
    setOpen(next)
    if (!next) {
      resetForm()
    }
  }

  const handleDelete = async (view: TableFilterView) => {
    const confirmed = await confirm({
      tone: 'destructive',
      title: t('table.deleteView'),
      description: t('table.confirmAction'),
    })
    if (!confirmed) {
      return
    }
    try {
      await deleteView.mutateAsync(view.id)
      toast.success(t('table.viewDeleted'))
    } catch {
      toast.error(t('table.viewDeleteError'))
    }
  }

  const handleSave = async () => {
    if (!canSave) {
      return
    }
    try {
      await createView.mutateAsync({ name: name.trim(), filters: currentFilters, visibility })
      toast.success(t('table.viewSaved'))
      resetForm()
      setOpen(false)
    } catch (error) {
      const isDuplicateName =
        axios.isAxiosError(error) &&
        error.response?.status === 422 &&
        Boolean(error.response.data?.errors?.name)
      toast.error(t(isDuplicateName ? 'table.duplicateViewName' : 'table.viewSaveError'))
    }
  }

  return (
    <DropdownMenu open={open} onOpenChange={handleOpenChange}>
      <Tooltip>
        <TooltipTrigger asChild>
          <DropdownMenuTrigger asChild>
            <Button
              variant="ghost"
              size="icon-sm"
              className="relative text-muted-foreground hover:text-foreground"
              aria-label={t('table.savedFilters')}
            >
              <Bookmark aria-hidden="true" />
              {count > 0 ? (
                <span className="absolute -right-1 -top-1 inline-flex h-4 min-w-4 items-center justify-center rounded-full bg-primary px-1 text-[10px] font-semibold text-primary-foreground">
                  {count}
                </span>
              ) : null}
            </Button>
          </DropdownMenuTrigger>
        </TooltipTrigger>
        <TooltipContent>{t('table.savedFilters')}</TooltipContent>
      </Tooltip>

      <DropdownMenuContent align="start" className="w-80 p-0">
        <div className="flex items-center gap-2 border-b px-3 py-2.5">
          <div className="flex size-7 shrink-0 items-center justify-center rounded-md bg-primary/10 text-primary">
            <Bookmark aria-hidden="true" className="size-4" />
          </div>
          <div className="min-w-0">
            <p className="text-sm font-semibold leading-tight">{t('table.savedFilters')}</p>
            <p className="truncate text-xs text-muted-foreground">
              {t('table.savedFiltersSubtitle')}
            </p>
          </div>
        </div>

        <div className="max-h-64 overflow-y-auto p-1">
          {hasViews ? null : (
            <div className="flex flex-col items-center gap-1 px-3 py-6 text-center">
              <Bookmark aria-hidden="true" className="size-5 text-muted-foreground/60" />
              <p className="text-xs text-muted-foreground">{t('table.noSavedViews')}</p>
            </div>
          )}

          {ownedViews.length > 0 ? (
            <>
              <DropdownMenuLabel className="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                {t('table.myViews')}
              </DropdownMenuLabel>
              {ownedViews.map((view) => (
                <FilterViewRow
                  key={view.id}
                  view={view}
                  active={sameFilters(view.filters, currentFilters)}
                  onApply={onApply}
                  onDelete={(target) => void handleDelete(target)}
                />
              ))}
            </>
          ) : null}

          {sharedViews.length > 0 ? (
            <>
              {ownedViews.length > 0 ? <DropdownMenuSeparator /> : null}
              <DropdownMenuLabel className="text-[11px] font-medium uppercase tracking-wide text-muted-foreground">
                {t('table.sharedViews')}
              </DropdownMenuLabel>
              {sharedViews.map((view) => (
                <FilterViewRow
                  key={view.id}
                  view={view}
                  active={sameFilters(view.filters, currentFilters)}
                  onApply={onApply}
                  onDelete={(target) => void handleDelete(target)}
                />
              ))}
            </>
          ) : null}
        </div>

        {/* Inline save panel. Radix Menu grabs keystrokes for typeahead and
            closes on Tab; stopping propagation (except Escape) lets the form
            be typed and tabbed through while the panel stays open. */}
        <div
          className="border-t bg-muted/40 px-3 py-3"
          onKeyDown={(event) => {
            if (event.key !== 'Escape') {
              event.stopPropagation()
            }
          }}
        >
          <div className="mb-2 flex items-center gap-1.5">
            <BookmarkPlus aria-hidden="true" className="size-3.5 text-muted-foreground" />
            <span className="text-[11px] font-semibold uppercase tracking-wide text-muted-foreground">
              {t('table.saveViewHeading')}
            </span>
          </div>

          {hasFilters ? (
            <div className="flex flex-col gap-2">
              <Input
                value={name}
                onChange={(event) => setName(event.target.value)}
                onKeyDown={(event) => {
                  if (event.key === 'Enter') {
                    event.preventDefault()
                    void handleSave()
                  }
                }}
                placeholder={t('table.viewNamePlaceholder')}
                aria-label={t('table.viewNamePlaceholder')}
                autoComplete="off"
                maxLength={VIEW_NAME_MAX_LENGTH}
                className="h-8"
              />

              <div
                role="group"
                aria-label={t('table.visibility')}
                className="grid grid-cols-2 gap-1 rounded-md bg-muted p-1"
              >
                <VisibilityOption
                  value="private"
                  active={visibility === 'private'}
                  icon={Lock}
                  label={t('table.visibilityPrivate')}
                  onSelect={setVisibility}
                />
                <VisibilityOption
                  value="shared"
                  active={visibility === 'shared'}
                  icon={Users}
                  label={t('table.visibilityShared')}
                  onSelect={setVisibility}
                />
              </div>

              <Button size="sm" className="w-full" disabled={!canSave} onClick={() => void handleSave()}>
                <BookmarkPlus aria-hidden="true" />
                {t('table.saveView')}
              </Button>
            </div>
          ) : (
            <p className="text-xs text-muted-foreground">{t('table.applyFilterToSaveHint')}</p>
          )}
        </div>
      </DropdownMenuContent>
    </DropdownMenu>
  )
}
