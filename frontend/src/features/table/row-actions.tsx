/* eslint-disable react-refresh/only-export-components -- renderer factory module: returns an AG Grid cell renderer, not a route/page component */
import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { MoreHorizontal } from 'lucide-react'
import type { ICellRendererParams } from 'ag-grid-community'
import { Button } from '@/components/ui/button'
import {
  DropdownMenu,
  DropdownMenuContent,
  DropdownMenuItem,
  DropdownMenuTrigger,
} from '@/components/ui/dropdown-menu'
import {
  Tooltip,
  TooltipContent,
  TooltipProvider,
  TooltipTrigger,
} from '@/components/ui/tooltip'
import {
  resolveActionIcon,
  type ActionIconMap,
} from '@/features/table/action-icon-map'
import type { TableActionDefinition, TableRow } from '@/features/table/types'

/**
 * Up to this many actions render as inline icon buttons; more than this and they
 * all collapse into the overflow (three-dots) menu, keeping the actions column
 * narrow regardless of how many actions a row exposes.
 */
const INLINE_ACTION_LIMIT = 3

/** Fired when the user triggers an action on a row. */
export type RowActionHandler = (
  action: TableActionDefinition,
  row: TableRow,
) => void

/** Optional behaviors injected by the domain that owns the actions column. */
export interface RowActionsOptions {
  /** Returns true while a mutation (e.g. delete) is running for the row. */
  isBusy?: (row: TableRow) => boolean
  /** Adjusts the row before computing actions (e.g. drop self-delete). */
  decorateRow?: (row: TableRow) => TableRow
  /**
   * Domain-supplied icon names → Lucide components, merged over the shared
   * defaults. Lets a domain advertise new icons without touching generic code.
   */
  iconMap?: ActionIconMap
}

interface RowActionsProps extends RowActionsOptions {
  row: TableRow
  /** Full action catalog from the config (how to render each key). */
  catalog: TableActionDefinition[]
  onAction: RowActionHandler
}

/**
 * Renders the actions available for a single row by crossing `row.actions`
 * (the per-row whitelist computed server-side) with the config action catalog
 * (label/icon/type/confirm). Actions not present in `row.actions` are never
 * shown. `confirm` actions ask for confirmation before invoking the handler.
 *
 * Domain-agnostic: the concrete behavior of each action lives in the domain's
 * `onAction` handler; this component only renders the affordance and crosses the
 * whitelist with the catalog.
 */
function RowActions({
  row,
  catalog,
  onAction,
  isBusy,
  decorateRow,
  iconMap,
}: RowActionsProps) {
  const { t } = useTranslation()

  const effectiveRow = decorateRow ? decorateRow(row) : row
  const busy = isBusy?.(effectiveRow) ?? false

  // Preserve catalog order; only keep actions allowed for this row.
  const available = useMemo(() => {
    const allowed = new Set(effectiveRow.actions)
    return catalog.filter((action) => allowed.has(action.key))
  }, [catalog, effectiveRow.actions])

  if (available.length === 0) {
    return null
  }

  const handleSelect = (action: TableActionDefinition) => {
    if (action.confirm && !window.confirm(t('table.confirmAction'))) {
      return
    }
    onAction(action, effectiveRow)
  }

  // Few actions: render them inline as compact icon buttons (label in tooltip).
  if (available.length <= INLINE_ACTION_LIMIT) {
    return (
      <div className="flex h-full items-center justify-end gap-0.5">
        <TooltipProvider>
          {available.map((action) => {
            const Icon = resolveActionIcon(action.icon, iconMap)
            const label = t(action.label)
            return (
              <Tooltip key={action.key}>
                <TooltipTrigger asChild>
                  <Button
                    variant="ghost"
                    size="icon-xs"
                    aria-label={label}
                    disabled={busy}
                    onClick={() => handleSelect(action)}
                    className={
                      action.type === 'danger'
                        ? 'text-destructive hover:text-destructive'
                        : undefined
                    }
                  >
                    <Icon aria-hidden="true" />
                  </Button>
                </TooltipTrigger>
                <TooltipContent>{label}</TooltipContent>
              </Tooltip>
            )
          })}
        </TooltipProvider>
      </div>
    )
  }

  // Many actions: collapse everything into the overflow (three-dots) menu.
  return (
    <div className="flex h-full items-center justify-end">
      <DropdownMenu>
        <DropdownMenuTrigger asChild>
          <Button
            variant="ghost"
            size="icon-xs"
            aria-label={t('table.rowActions')}
            disabled={busy}
          >
            <MoreHorizontal aria-hidden="true" />
          </Button>
        </DropdownMenuTrigger>
        <DropdownMenuContent align="end">
          {available.map((action) => {
            const Icon = resolveActionIcon(action.icon, iconMap)
            return (
              <DropdownMenuItem
                key={action.key}
                variant={action.type === 'danger' ? 'destructive' : 'default'}
                onSelect={() => handleSelect(action)}
              >
                <Icon aria-hidden="true" />
                {t(action.label)}
              </DropdownMenuItem>
            )
          })}
        </DropdownMenuContent>
      </DropdownMenu>
    </div>
  )
}

/**
 * Factory that returns an AG Grid cell renderer for the row-actions column,
 * bound to the action catalog and a single action handler.
 */
export function createRowActionsRenderer(
  catalog: TableActionDefinition[],
  onAction: RowActionHandler,
  options: RowActionsOptions = {},
) {
  return function RowActionsCell(params: ICellRendererParams) {
    const row = params.data as TableRow | undefined
    if (!row) {
      return null
    }
    return (
      <RowActions
        row={row}
        catalog={catalog}
        onAction={onAction}
        isBusy={options.isBusy}
        decorateRow={options.decorateRow}
        iconMap={options.iconMap}
      />
    )
  }
}
