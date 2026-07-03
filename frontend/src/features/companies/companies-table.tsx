import { useCallback, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import axios from 'axios'
import { Plus } from 'lucide-react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { PageHeader } from '@/components/page-header'
import {
  Sheet,
  SheetContent,
  SheetDescription,
  SheetHeader,
  SheetTitle,
} from '@/components/ui/sheet'
import { Can } from '@/features/auth/can'
import { TableView, type TableViewHandle } from '@/features/table/table-view'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { companyColumnRenderers } from '@/features/companies/column-renderers'
import { deleteCompany, fetchCompany } from '@/features/companies/api'
import { CompanyForm } from '@/features/companies/company-form'
import { CompanyDetailView } from '@/features/companies/company-detail'

/** Domain key used to mount the generic table for companies. */
const COMPANIES_DOMAIN = 'companies'

/** Which sheet (if any) is currently open and for which row. */
type SheetState =
  | { kind: 'none' }
  | { kind: 'create' }
  | { kind: 'view'; row: TableRow }
  | { kind: 'edit'; row: TableRow }

/**
 * Thin Companies adapter over the generic table. It mounts `<TableView>` with
 * the `companies` domain, its custom cell renderers and a row-action handler,
 * and owns the CRUD flows: opening a Sheet for view/edit/create, confirming +
 * running the delete mutation, and refreshing the SSRM grid after every
 * mutation via the table's imperative handle. No table logic lives here —
 * only companies CRUD wiring. Permission gating is an affordance only; the
 * backend re-authorizes each call.
 */
export function CompaniesTable() {
  const { t } = useTranslation()

  const tableRef = useRef<TableViewHandle>(null)
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])

  const [sheet, setSheet] = useState<SheetState>({ kind: 'none' })
  const [deletingId, setDeletingId] = useState<number | null>(null)

  const closeSheet = useCallback(() => setSheet({ kind: 'none' }), [])

  const runDelete = useCallback(
    async (row: TableRow) => {
      setDeletingId(row.id)
      try {
        await deleteCompany(row.id)
        toast.success(t('companies.form.deleted'))
        refreshGrid()
      } catch (error) {
        const status = axios.isAxiosError(error) ? error.response?.status : undefined
        toast.error(
          status === 403
            ? t('companies.form.deleteForbidden')
            : t('companies.form.deleteError'),
        )
      } finally {
        setDeletingId(null)
      }
    },
    [refreshGrid, t],
  )

  const handleAction: RowActionHandler = useCallback(
    (action: TableActionDefinition, row: TableRow) => {
      switch (action.key) {
        case 'view':
          setSheet({ kind: 'view', row })
          break
        case 'edit':
          setSheet({ kind: 'edit', row })
          break
        case 'delete':
          void runDelete(row)
          break
        default:
          break
      }
    },
    [runDelete],
  )

  const isBusy = useCallback(
    (row: TableRow) => row.id === deletingId,
    [deletingId],
  )

  const onSheetOpenChange = useCallback(
    (open: boolean) => {
      if (!open) {
        closeSheet()
      }
    },
    [closeSheet],
  )

  const onMutationSuccess = useCallback(() => {
    closeSheet()
    refreshGrid()
  }, [closeSheet, refreshGrid])

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader
        actions={
          <Can permission="companies.create">
            <Button onClick={() => setSheet({ kind: 'create' })}>
              <Plus aria-hidden="true" />
              {t('companies.form.newCompany')}
            </Button>
          </Can>
        }
      />

      <TableView
        ref={tableRef}
        domain={COMPANIES_DOMAIN}
        renderers={companyColumnRenderers}
        onAction={handleAction}
        isBusy={isBusy}
      />

      <Sheet open={sheet.kind !== 'none'} onOpenChange={onSheetOpenChange}>
        <SheetContent className="gap-0 sm:max-w-2xl">
          {sheet.kind === 'view' && (
            <>
              <SheetHeader>
                <SheetTitle>{t('companies.detail.title')}</SheetTitle>
                <SheetDescription>{t('companies.detail.subtitle')}</SheetDescription>
              </SheetHeader>
              <CompanyDetailView companyId={sheet.row.id} />
            </>
          )}

          {sheet.kind === 'create' && (
            <>
              <SheetHeader>
                <SheetTitle>{t('companies.form.createTitle')}</SheetTitle>
                <SheetDescription>
                  {t('companies.form.createSubtitle')}
                </SheetDescription>
              </SheetHeader>
              <CompanyForm
                mode={{ type: 'create' }}
                onSuccess={onMutationSuccess}
                onCancel={closeSheet}
              />
            </>
          )}

          {sheet.kind === 'edit' && (
            <>
              <SheetHeader>
                <SheetTitle>{t('companies.form.editTitle')}</SheetTitle>
                <SheetDescription>
                  {t('companies.form.editSubtitle')}
                </SheetDescription>
              </SheetHeader>
              <EditCompanyLoader
                companyId={sheet.row.id}
                onSuccess={onMutationSuccess}
                onCancel={closeSheet}
              />
            </>
          )}
        </SheetContent>
      </Sheet>
    </div>
  )
}

interface EditCompanyLoaderProps {
  companyId: number
  onSuccess: () => void
  onCancel: () => void
}

/**
 * Fetches the fresh, re-authorized company detail before mounting the edit
 * form, so the partial PATCH starts from authoritative values rather than the
 * grid row snapshot.
 */
function EditCompanyLoader({ companyId, onSuccess, onCancel }: EditCompanyLoaderProps) {
  const { t } = useTranslation()
  const {
    data: company,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(['companies', 'detail', companyId], () => fetchCompany(companyId))

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive">{t('companies.detail.loadError')}</p>
        <Button variant="outline" size="sm" onClick={() => refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (isLoading || !company) {
    return (
      <div className="flex flex-col gap-4 p-4">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  return (
    <CompanyForm
      mode={{ type: 'edit', company }}
      onSuccess={onSuccess}
      onCancel={onCancel}
    />
  )
}
