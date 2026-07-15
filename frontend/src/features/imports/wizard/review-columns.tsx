/* eslint-disable react-refresh/only-export-components -- cell-renderer module: mixes AG Grid render components with the pure colDef builder, not a route/page component */
import type {
  ColDef,
  ICellRendererParams,
  ValueGetterParams,
  ValueSetterParams,
} from 'ag-grid-community'
import type { TFunction } from 'i18next'
import { AlertTriangle } from 'lucide-react'
import i18n from '@/i18n'
import { Badge } from '@/components/ui/badge'
import { EXTRA_TARGET, IGNORE_TARGET } from '@/features/imports/wizard/types'
import type { ImportRowStatus, ImportRunDetail, ImportRunRowItem } from '@/features/imports/wizard/types'

/**
 * `colId` prefixes distinguishing the two id spaces a review row value can
 * live in: a mapped field id (`fields[].id`, backend catalog) vs. an extra
 * column's raw file header (`leads.extra_fields` key). They never collide
 * even when a file header happens to match a field id string.
 */
const FIELD_COLUMN_PREFIX = 'field:'
const EXTRA_COLUMN_PREFIX = 'extra:'

/** Badge variant per row status (`App\Enums\ImportRowStatus` mirror). */
const STATUS_BADGE_VARIANT: Record<ImportRowStatus, 'default' | 'secondary' | 'destructive' | 'outline'> = {
  valid: 'secondary',
  warning: 'outline',
  error: 'destructive',
  duplicate: 'outline',
  skipped: 'outline',
}

/** Extra tone (beyond the shadcn variant) for statuses that share the `outline` variant. */
const STATUS_TONE_CLASS: Partial<Record<ImportRowStatus, string>> = {
  warning: 'border-amber-500 text-amber-700 dark:text-amber-400',
  duplicate: 'border-sky-500 text-sky-700 dark:text-sky-400',
  skipped: 'text-muted-foreground',
}

function statusLabel(status: ImportRowStatus): string {
  return i18n.t(`review.status.${status}`, { ns: 'importWizard', defaultValue: status })
}

/**
 * Read-only `status` column cell: a colored badge, plus a compact "edited"
 * marker once the row has been touched inline (AC-023 `is_edited`).
 */
export function ReviewStatusCell({ data }: ICellRendererParams<ImportRunRowItem, ImportRowStatus>) {
  if (!data) return null
  return (
    <div className="flex h-full items-center gap-1.5">
      <Badge variant={STATUS_BADGE_VARIANT[data.status]} className={STATUS_TONE_CLASS[data.status]}>
        {statusLabel(data.status)}
      </Badge>
      {data.is_edited ? (
        <span
          className="size-1.5 shrink-0 rounded-full bg-primary"
          title={i18n.t('review.editedTitle', { ns: 'importWizard' })}
        />
      ) : null}
    </div>
  )
}

/** Read-only `messages` column cell: the row's error/warning/interpretation notices. */
export function ReviewMessagesCell({ data }: ICellRendererParams<ImportRunRowItem, unknown>) {
  const messages = data?.messages ?? []
  if (messages.length === 0) {
    return <span className="text-muted-foreground">—</span>
  }
  return (
    <div className="flex h-full items-center gap-1 overflow-hidden" title={messages.join('\n')}>
      <AlertTriangle className="size-3.5 shrink-0 text-amber-600 dark:text-amber-400" aria-hidden="true" />
      <span className="truncate text-xs">{messages.join(' · ')}</span>
    </div>
  )
}

/** Strips the `colId` prefix back to the `values` key it edits, or `null` for a non-value column. */
export function reviewValueKeyOf(colId: string): string | null {
  if (colId.startsWith(FIELD_COLUMN_PREFIX)) return colId.slice(FIELD_COLUMN_PREFIX.length)
  if (colId.startsWith(EXTRA_COLUMN_PREFIX)) return colId.slice(EXTRA_COLUMN_PREFIX.length)
  return null
}

/**
 * An editable value column, backed by a `valueGetter`/`valueSetter` pair
 * rather than a flat `field` path: `ImportRunRowItem.values` is a single
 * `Record<string, string>` keyed by field id (mapped) or original column
 * name (extra), not a per-column top-level row property.
 */
function buildValueColumn(colId: string, valueKey: string, headerName: string, sortable: boolean): ColDef<ImportRunRowItem> {
  return {
    colId,
    headerName,
    editable: true,
    sortable,
    filter: false,
    minWidth: 160,
    cellEditor: 'agTextCellEditor',
    valueGetter: (params: ValueGetterParams<ImportRunRowItem>) => params.data?.values[valueKey] ?? '',
    valueSetter: (params: ValueSetterParams<ImportRunRowItem>) => {
      if (!params.data || params.newValue === params.oldValue) return false
      params.data.values = { ...params.data.values, [valueKey]: String(params.newValue ?? '') }
      return true
    },
  }
}

/**
 * Builds the review grid's column definitions from the run's frozen mapping
 * (AC-023): `row_number`/`status` are read-only service columns, one
 * editable column per mapped field and per `__extra__` column, and a
 * trailing read-only `messages` column. Sorting is only wired on the columns
 * the backend allow-lists (`row_number`, `status`, mapped field ids) —
 * extra columns and `messages` stay unsortable, matching the SSRM contract.
 */
export function buildReviewColumnDefs(run: ImportRunDetail, t: TFunction): ColDef<ImportRunRowItem>[] {
  const mapping = run.column_mapping ?? {}
  const mappedFieldIds = new Set(
    Object.values(mapping).filter((target) => target !== IGNORE_TARGET && target !== EXTRA_TARGET),
  )
  const extraColumnNames = Object.entries(mapping)
    .filter(([, target]) => target === EXTRA_TARGET)
    .map(([columnName]) => columnName)
  const mappedFields = run.fields.filter((field) => mappedFieldIds.has(field.id))

  return [
    {
      colId: 'row_number',
      field: 'row_number',
      headerName: t('review.columns.rowNumber'),
      editable: false,
      sortable: true,
      filter: false,
      width: 90,
      minWidth: 90,
      flex: 0,
    },
    {
      colId: 'status',
      headerName: t('review.columns.status'),
      editable: false,
      sortable: true,
      filter: false,
      width: 160,
      minWidth: 160,
      flex: 0,
      cellRenderer: ReviewStatusCell,
    },
    // `field.label` is a backend default-namespace i18n key
    // (`imports.leads.fields.*`); resolve it via the default namespace, not the
    // `importWizard`-scoped `t` used for the grid's own chrome.
    ...mappedFields.map((field) =>
      buildValueColumn(FIELD_COLUMN_PREFIX + field.id, field.id, i18n.t(field.label), true),
    ),
    ...extraColumnNames.map((columnName) =>
      buildValueColumn(
        EXTRA_COLUMN_PREFIX + columnName,
        columnName,
        `${columnName} (${t('review.columns.extraSuffix')})`,
        false,
      ),
    ),
    {
      colId: 'messages',
      headerName: t('review.columns.messages'),
      editable: false,
      sortable: false,
      filter: false,
      minWidth: 220,
      flex: 1,
      cellRenderer: ReviewMessagesCell,
    },
  ]
}
