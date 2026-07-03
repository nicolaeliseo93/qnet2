import { useTranslation } from 'react-i18next'
import { Skeleton } from '@/components/ui/skeleton'
import type { MigrationColumn, MigrationPreviewRow } from '@/features/migrations/types'

/** Renders a single cell value, keeping booleans/nulls readable as text. */
function formatCellValue(value: MigrationPreviewRow[string]): string {
  if (value === null) return ''
  if (typeof value === 'boolean') return value ? 'true' : 'false'
  return String(value)
}

interface MigrationPreviewTableProps {
  columns: MigrationColumn[]
  rows: MigrationPreviewRow[]
  isLoading: boolean
  isError: boolean
}

/**
 * Read-only rendering of a source's external preview (fase 1): no edit,
 * delete or create control ever appears here (spec 0013 scope). Plain
 * semantic table on purpose -- the generic AG Grid framework is explicitly
 * out of scope for this accoppiato-al-DB-locale preview.
 */
export function MigrationPreviewTable({
  columns,
  rows,
  isLoading,
  isError,
}: MigrationPreviewTableProps) {
  const { t } = useTranslation('migrations')

  if (isError) {
    return (
      <p className="text-sm text-destructive" role="alert">
        {t('preview.loadError')}
      </p>
    )
  }

  if (isLoading) {
    return (
      <div className="flex flex-col gap-2">
        <Skeleton className="h-8 w-full" />
        <Skeleton className="h-8 w-full" />
        <Skeleton className="h-8 w-full" />
      </div>
    )
  }

  if (columns.length === 0 || rows.length === 0) {
    return <p className="text-sm text-muted-foreground">{t('preview.empty')}</p>
  }

  return (
    <div className="max-h-96 overflow-auto rounded-md border">
      <table className="w-full text-left text-sm">
        <thead className="bg-muted/60">
          <tr>
            {columns.map((column) => (
              <th key={column.id} scope="col" className="whitespace-nowrap px-3 py-2 font-medium">
                {column.label}
              </th>
            ))}
          </tr>
        </thead>
        <tbody>
          {rows.map((row, index) => (
            // The external preview carries no stable row id of its own; index is
            // safe here (static list per page, no client-side reordering).
            <tr key={index} className="border-t">
              {columns.map((column) => (
                <td key={column.id} className="truncate px-3 py-2">
                  {formatCellValue(row[column.id])}
                </td>
              ))}
            </tr>
          ))}
        </tbody>
      </table>
    </div>
  )
}
