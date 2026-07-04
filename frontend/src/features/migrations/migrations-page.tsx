import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { ChevronLeft, ChevronRight, Eye } from 'lucide-react'
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
import { MigrationTemplatePanel } from '@/features/migrations/migration-template-panel'
import type { MigrationColumn, MigrationPreviewRow } from '@/features/migrations/types'
import {
  useMigrationColumns,
  useMigrationPreview,
  useMigrationSources,
} from '@/features/migrations/use-migrations'

const FIRST_PAGE = 1
const EMPTY_COLUMNS: MigrationColumn[] = []
const EMPTY_ROWS: MigrationPreviewRow[] = []

/**
 * Migrations page (spec 0013, super-admin only, template-first redesign):
 * qnet is the contract owner, so selecting a source's primary view is the
 * EXPECTED field schema (`MigrationTemplatePanel`, no external call) rather
 * than a live external fetch. The read-only external preview and the import
 * flow are both opt-in actions from there: the preview only fires once
 * explicitly requested, and import opens the existing `ImportDialog`. No
 * edit/delete/create control ever appears -- everything here is read-only.
 */
export default function MigrationsPage() {
  const { t } = useTranslation('migrations')
  const [selectedSource, setSelectedSource] = useState<string | null>(null)
  const [page, setPage] = useState(FIRST_PAGE)
  const [previewRequested, setPreviewRequested] = useState(false)
  const [importOpen, setImportOpen] = useState(false)

  const sourcesQuery = useMigrationSources()
  const columnsQuery = useMigrationColumns(selectedSource)
  const previewQuery = useMigrationPreview(selectedSource, page, undefined, previewRequested)

  const handleSourceChange = (next: string) => {
    setSelectedSource(next)
    setPage(FIRST_PAGE)
    setPreviewRequested(false)
  }

  const sources = sourcesQuery.data ?? []
  const selectedSourceLabel =
    sources.find((source) => source.key === selectedSource)?.label ?? selectedSource ?? ''

  const pagination = previewQuery.data?.pagination
  const isPreviewLoading = columnsQuery.isLoading || previewQuery.isLoading
  const isPreviewError = columnsQuery.isError || previewQuery.isError

  return (
    <div className="flex flex-1 flex-col gap-6">
      <PageHeader />

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
        </CardContent>
      </Card>

      {selectedSource ? (
        <MigrationTemplatePanel
          sourceLabel={selectedSourceLabel}
          columns={columnsQuery.data?.columns ?? EMPTY_COLUMNS}
          request={columnsQuery.data?.request}
          sample={columnsQuery.data?.sample}
          isLoading={columnsQuery.isLoading}
          isError={columnsQuery.isError}
          onImportClick={() => setImportOpen(true)}
        />
      ) : null}

      {selectedSource && !previewRequested ? (
        <div>
          <Button type="button" variant="outline" onClick={() => setPreviewRequested(true)}>
            <Eye aria-hidden="true" />
            {t('preview.showButton')}
          </Button>
        </div>
      ) : null}

      {selectedSource && previewRequested ? (
        <Card>
          <CardContent className="flex flex-col gap-4">
            <MigrationPreviewTable
              columns={columnsQuery.data?.columns ?? EMPTY_COLUMNS}
              rows={previewQuery.data?.rows ?? EMPTY_ROWS}
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
          </CardContent>
        </Card>
      ) : null}

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
