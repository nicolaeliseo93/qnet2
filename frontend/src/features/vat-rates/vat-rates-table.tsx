import { useCallback, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
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
import { ResourceActivityDialog } from '@/features/activity-log/resource-activity-dialog'
import { TableView, type TableViewHandle } from '@/features/table/table-view'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { vatRateColumnRenderers } from '@/features/vat-rates/column-renderers'
import { deleteVatRate, fetchVatRate } from '@/features/vat-rates/api'
import { VatRateForm } from '@/features/vat-rates/vat-rate-form'
import { VatRateDetailView } from '@/features/vat-rates/vat-rate-detail'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import type { VatRateDetail } from '@/features/vat-rates/types'

/** Domain key used to mount the generic table for VAT rates. */
const VAT_RATES_DOMAIN = 'vat-rates'

/** Which sheet (if any) is currently open and for which row. */
type SheetState =
  | { kind: 'none' }
  | { kind: 'create' }
  | { kind: 'view'; row: TableRow }
  | { kind: 'edit'; row: TableRow }

/** Query key for a single VAT rate's detail (fresh-on-open pattern). */
function detailQueryKey(id: number) {
  return ['vat-rates', 'detail', id] as const
}

/**
 * Thin VAT rates adapter over the generic table. It mounts `<TableView>` with
 * the `vat-rates` domain, its custom cell renderers and a row-action handler,
 * and owns the CRUD flows: opening a Sheet for view/edit/create, confirming +
 * running the delete mutation, and refreshing the SSRM grid after every
 * mutation via the table's imperative handle. No table logic lives here —
 * only VAT rates CRUD wiring. Permission gating is an affordance only; the
 * backend re-authorizes each call.
 */
export function VatRatesTable() {
  const { t } = useTranslation()
  const queryClient = useQueryClient()

  const tableRef = useRef<TableViewHandle>(null)
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])

  const [sheet, setSheet] = useState<SheetState>({ kind: 'none' })
  const [deletingId, setDeletingId] = useState<number | null>(null)
  const [activityRow, setActivityRow] = useState<TableRow | null>(null)

  const closeSheet = useCallback(() => setSheet({ kind: 'none' }), [])

  const runDelete = useCallback(
    async (row: TableRow) => {
      setDeletingId(row.id)
      try {
        await deleteVatRate(row.id)
        toast.success(t('vatRates.form.deleted'))
        refreshGrid()
      } catch (error) {
        const status = axios.isAxiosError(error) ? error.response?.status : undefined
        toast.error(
          status === 403 ? t('vatRates.form.deleteForbidden') : t('vatRates.form.deleteError'),
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
        case 'activity':
          setActivityRow(row)
          break
        default:
          break
      }
    },
    [runDelete],
  )

  const isBusy = useCallback((row: TableRow) => row.id === deletingId, [deletingId])

  const onSheetOpenChange = useCallback(
    (open: boolean) => {
      if (!open) {
        closeSheet()
      }
    },
    [closeSheet],
  )

  const onMutationSuccess = useCallback(
    (vatRate: VatRateDetail) => {
      closeSheet()
      refreshGrid()
      queryClient.invalidateQueries({ queryKey: detailQueryKey(vatRate.id) })
    },
    [closeSheet, refreshGrid, queryClient],
  )

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader
        actions={
          <Can permission="vat-rates.create">
            <Button onClick={() => setSheet({ kind: 'create' })}>
              <Plus aria-hidden="true" />
              {t('vatRates.form.newVatRate')}
            </Button>
          </Can>
        }
      />

      <TableView
        ref={tableRef}
        domain={VAT_RATES_DOMAIN}
        renderers={vatRateColumnRenderers}
        onAction={handleAction}
        isBusy={isBusy}
      />

      <Sheet open={sheet.kind !== 'none'} onOpenChange={onSheetOpenChange}>
        <SheetContent className="gap-0" storageKey={`sheet-width:${VAT_RATES_DOMAIN}`}>
          {sheet.kind === 'view' && (
            <>
              <SheetHeader className="sr-only">
                <SheetTitle>{t('vatRates.detail.title')}</SheetTitle>
                <SheetDescription>{t('vatRates.detail.subtitle')}</SheetDescription>
              </SheetHeader>
              <ViewVatRateLoader vatRateId={sheet.row.id} />
            </>
          )}

          {sheet.kind === 'create' && (
            <>
              <SheetHeader>
                <SheetTitle>{t('vatRates.form.createTitle')}</SheetTitle>
                <SheetDescription>{t('vatRates.form.createSubtitle')}</SheetDescription>
              </SheetHeader>
              <VatRateForm
                mode={{ type: 'create' }}
                onSuccess={onMutationSuccess}
                onCancel={closeSheet}
              />
            </>
          )}

          {sheet.kind === 'edit' && (
            <>
              <SheetHeader>
                <SheetTitle>{t('vatRates.form.editTitle')}</SheetTitle>
                <SheetDescription>{t('vatRates.form.editSubtitle')}</SheetDescription>
              </SheetHeader>
              <EditVatRateLoader
                vatRateId={sheet.row.id}
                onSuccess={onMutationSuccess}
                onCancel={closeSheet}
              />
            </>
          )}
        </SheetContent>
      </Sheet>

      <ResourceActivityDialog
        resource={VAT_RATES_DOMAIN}
        row={activityRow}
        onOpenChange={(open) => {
          if (!open) {
            setActivityRow(null)
          }
        }}
      />
    </div>
  )
}

interface ViewVatRateLoaderProps {
  vatRateId: number
}

/**
 * Fetches the fresh VAT rate detail and hands it down to the (presentational)
 * `VatRateDetailView`, which owns no data-fetching state of its own.
 */
function ViewVatRateLoader({ vatRateId }: ViewVatRateLoaderProps) {
  const { t } = useTranslation()
  const {
    data: vatRate,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(vatRateId), () => fetchVatRate(vatRateId))

  if (isError) {
    return (
      <DetailError
        message={t('vatRates.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !vatRate) {
    return <DetailLoading />
  }

  return <VatRateDetailView vatRate={vatRate} />
}

interface EditVatRateLoaderProps {
  vatRateId: number
  onSuccess: (vatRate: VatRateDetail) => void
  onCancel: () => void
}

/**
 * Fetches the fresh, re-authorized VAT rate detail before mounting the edit
 * form, so the partial PATCH starts from authoritative values rather than
 * the grid row snapshot.
 */
function EditVatRateLoader({ vatRateId, onSuccess, onCancel }: EditVatRateLoaderProps) {
  const { t } = useTranslation()
  const {
    data: vatRate,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(vatRateId), () => fetchVatRate(vatRateId))

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive">{t('vatRates.detail.loadError')}</p>
        <Button variant="outline" size="sm" onClick={() => refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (isLoading || !vatRate) {
    return (
      <div className="flex flex-col gap-4 p-4">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  return (
    <VatRateForm mode={{ type: 'edit', vatRate }} onSuccess={onSuccess} onCancel={onCancel} />
  )
}
