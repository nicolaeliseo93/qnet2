import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ChevronLeft, ChevronRight, Upload } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import { PageHeader } from '@/components/page-header'
// Side effect: registers the `migrations` i18next namespace (see the module
// doc comment) before this page's own `t()` calls run.
import '@/features/migrations/i18n'
import { ImportDialog } from '@/features/migrations/import-dialog'
import { MigrationPreviewTable } from '@/features/migrations/migration-preview-table'
import {
  useMigrationColumns,
  useMigrationPreview,
  useMigrationSources,
} from '@/features/migrations/use-migrations'

const FIRST_PAGE = 1

/**
 * Migrations page (spec 0013, super-admin only): selects an external source
 * and renders its read-only, paginated preview (fase 1). Importing (fase 2)
 * is a separate confirm+poll flow opened from here via `ImportDialog`. No
 * edit/delete/create control ever appears -- the preview is strictly
 * read-only.
 */
export default function MigrationsPage() {
  const { t } = useTranslation('migrations')
  const [selectedSource, setSelectedSource] = useState<string | null>(null)
  const [page, setPage] = useState(FIRST_PAGE)
  const [importOpen, setImportOpen] = useState(false)

  const sourcesQuery = useMigrationSources()
  const columnsQuery = useMigrationColumns(selectedSource)
  const previewQuery = useMigrationPreview(selectedSource, page)

  const handleSourceChange = (next: string) => {
    setSelectedSource(next)
    setPage(FIRST_PAGE)
  }

  const sources = sourcesQuery.data ?? []
  const selectedSourceLabel =
    sources.find((source) => source.key === selectedSource)?.label ?? selectedSource ?? ''

  const pagination = previewQuery.data?.pagination
  const isPreviewLoading = columnsQuery.isLoading || previewQuery.isLoading
  const isPreviewError = columnsQuery.isError || previewQuery.isError

  return (
    <div className="flex flex-1 flex-col gap-6">
      <PageHeader
        actions={
          <Button
            type="button"
            onClick={() => setImportOpen(true)}
            disabled={selectedSource == null}
          >
            <Upload aria-hidden="true" />
            {t('page.import')}
          </Button>
        }
      />

      <Card>
        <CardContent className="flex flex-col gap-4">
          <div className="flex flex-col gap-1.5 sm:w-64">
            <Label htmlFor="migration-source">{t('page.sourceLabel')}</Label>
            {sourcesQuery.isLoading ? (
              <Skeleton className="h-9 w-full" />
            ) : sourcesQuery.isError ? (
              <p className="text-sm text-destructive" role="alert">
                {t('page.sourcesLoadError')}
              </p>
            ) : sources.length === 0 ? (
              <p className="text-sm text-muted-foreground">{t('page.sourcesEmpty')}</p>
            ) : (
              <Select value={selectedSource ?? undefined} onValueChange={handleSourceChange}>
                <SelectTrigger id="migration-source" className="w-full">
                  <SelectValue placeholder={t('page.sourcePlaceholder')} />
                </SelectTrigger>
                <SelectContent>
                  {sources.map((source) => (
                    <SelectItem key={source.key} value={source.key}>
                      {source.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
            )}
          </div>

          {selectedSource ? (
            <>
              <MigrationPreviewTable
                columns={columnsQuery.data ?? []}
                rows={previewQuery.data?.rows ?? []}
                isLoading={isPreviewLoading}
                isError={isPreviewError}
              />

              {pagination ? (
                <div className="flex items-center justify-between gap-2">
                  <span className="text-sm text-muted-foreground">
                    {t('page.pageIndicator', { page: pagination.page })}
                  </span>
                  <div className="flex gap-2">
                    <Button
                      type="button"
                      variant="outline"
                      size="sm"
                      onClick={() => setPage((current) => Math.max(FIRST_PAGE, current - 1))}
                      disabled={pagination.page <= FIRST_PAGE}
                    >
                      <ChevronLeft aria-hidden="true" />
                      {t('page.previous')}
                    </Button>
                    <Button
                      type="button"
                      variant="outline"
                      size="sm"
                      onClick={() => setPage((current) => current + 1)}
                      disabled={!pagination.has_more}
                    >
                      {t('page.next')}
                      <ChevronRight aria-hidden="true" />
                    </Button>
                  </div>
                </div>
              ) : null}
            </>
          ) : null}
        </CardContent>
      </Card>

      {selectedSource ? (
        <ImportDialog
          source={selectedSource}
          sourceLabel={selectedSourceLabel}
          open={importOpen}
          onOpenChange={setImportOpen}
        />
      ) : null}
    </div>
  )
}
