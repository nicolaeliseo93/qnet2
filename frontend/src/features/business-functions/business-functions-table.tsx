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
import { ModuleStatsPanel } from '@/features/stats/module-stats-panel'
import { StatsToggleButton } from '@/features/stats/stats-toggle-button'
import { useStatsPanel } from '@/features/stats/use-stats-panel'
import { useInvalidateModuleStats } from '@/features/stats/use-invalidate-module-stats'
import { TableView, type TableViewHandle } from '@/features/table/table-view'
import type { RowActionHandler } from '@/features/table/row-actions'
import type {
  TableActionDefinition,
  TableRow,
} from '@/features/table/types'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { businessFunctionColumnRenderers } from '@/features/business-functions/column-renderers'
import { deleteBusinessFunction, fetchBusinessFunction } from '@/features/business-functions/api'
import { BusinessFunctionForm } from '@/features/business-functions/business-function-form'
import { BusinessFunctionDetailView } from '@/features/business-functions/business-function-detail'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import type { BusinessFunctionDetail } from '@/features/business-functions/types'

/** Domain key used to mount the generic table for business functions. */
const BUSINESS_FUNCTIONS_DOMAIN = 'business-functions'

/** Which sheet (if any) is currently open and for which row. */
type SheetState =
  | { kind: 'none' }
  | { kind: 'create' }
  | { kind: 'view'; row: TableRow }
  | { kind: 'edit'; row: TableRow }

/** Query key for a single business function's detail (fresh-on-open pattern). */
function detailQueryKey(id: number) {
  return ['business-functions', 'detail', id] as const
}

/**
 * Thin Business Functions adapter over the generic table. It mounts
 * `<TableView>` with the `business-functions` domain, its custom cell
 * renderers and a row-action handler, and owns the CRUD flows: opening a
 * Sheet for view/edit/create, confirming + running the delete mutation, and
 * refreshing the SSRM grid after every mutation via the table's imperative
 * handle. No table logic lives here — only business-functions CRUD wiring.
 * Permission gating is an affordance only; the backend re-authorizes each call.
 */
export function BusinessFunctionsTable() {
  const { t } = useTranslation()
  const stats = useStatsPanel(BUSINESS_FUNCTIONS_DOMAIN)
  const invalidateStats = useInvalidateModuleStats(BUSINESS_FUNCTIONS_DOMAIN)
  const queryClient = useQueryClient()

  const tableRef = useRef<TableViewHandle>(null)
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])

  const [sheet, setSheet] = useState<SheetState>({ kind: 'none' })
  const [deletingId, setDeletingId] = useState<number | null>(null)

  const closeSheet = useCallback(() => setSheet({ kind: 'none' }), [])

  const runDelete = useCallback(
    async (row: TableRow) => {
      setDeletingId(row.id)
      try {
        await deleteBusinessFunction(row.id)
        toast.success(t('businessFunctions.form.deleted'))
        refreshGrid()
        invalidateStats()
      } catch (error) {
        const status = axios.isAxiosError(error) ? error.response?.status : undefined
        toast.error(
          status === 403
            ? t('businessFunctions.form.deleteForbidden')
            : t('businessFunctions.form.deleteError'),
        )
      } finally {
        setDeletingId(null)
      }
    },
    [refreshGrid, t, invalidateStats],
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

  const onMutationSuccess = useCallback(
    (businessFunction: BusinessFunctionDetail) => {
      closeSheet()
      refreshGrid()
      invalidateStats()
      queryClient.invalidateQueries({ queryKey: detailQueryKey(businessFunction.id) })
    },
    [closeSheet, refreshGrid, queryClient, invalidateStats],
  )

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader
        actions={
          <>
            <StatsToggleButton
              domain={BUSINESS_FUNCTIONS_DOMAIN}
              isOpen={stats.isOpen}
              onToggle={stats.toggle}
            />
            <Can permission="business-functions.create">
              <Button onClick={() => setSheet({ kind: 'create' })}>
                <Plus aria-hidden="true" />
                {t('businessFunctions.form.newBusinessFunction')}
              </Button>
            </Can>
          </>
        }
      />

      <ModuleStatsPanel domain={BUSINESS_FUNCTIONS_DOMAIN} isOpen={stats.isOpen} />

      <TableView
        ref={tableRef}
        domain={BUSINESS_FUNCTIONS_DOMAIN}
        renderers={businessFunctionColumnRenderers}
        onAction={handleAction}
        isBusy={isBusy}
      />

      <Sheet open={sheet.kind !== 'none'} onOpenChange={onSheetOpenChange}>
        <SheetContent className="gap-0" storageKey={`sheet-width:${BUSINESS_FUNCTIONS_DOMAIN}`}>
          {sheet.kind === 'view' && (
            <>
              <SheetHeader className="sr-only">
                <SheetTitle>{t('businessFunctions.detail.title')}</SheetTitle>
                <SheetDescription>
                  {t('businessFunctions.detail.subtitle')}
                </SheetDescription>
              </SheetHeader>
              <ViewBusinessFunctionLoader businessFunctionId={sheet.row.id} />
            </>
          )}

          {sheet.kind === 'create' && (
            <>
              <SheetHeader>
                <SheetTitle>{t('businessFunctions.form.createTitle')}</SheetTitle>
                <SheetDescription>
                  {t('businessFunctions.form.createSubtitle')}
                </SheetDescription>
              </SheetHeader>
              <BusinessFunctionForm
                mode={{ type: 'create' }}
                onSuccess={onMutationSuccess}
                onCancel={closeSheet}
              />
            </>
          )}

          {sheet.kind === 'edit' && (
            <>
              <SheetHeader>
                <SheetTitle>{t('businessFunctions.form.editTitle')}</SheetTitle>
                <SheetDescription>
                  {t('businessFunctions.form.editSubtitle')}
                </SheetDescription>
              </SheetHeader>
              <EditBusinessFunctionLoader
                businessFunctionId={sheet.row.id}
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

interface ViewBusinessFunctionLoaderProps {
  businessFunctionId: number
}

/**
 * Fetches the fresh business function detail and hands it down to the
 * (presentational) `BusinessFunctionDetailView`, which owns no data-fetching
 * state of its own.
 */
function ViewBusinessFunctionLoader({
  businessFunctionId,
}: ViewBusinessFunctionLoaderProps) {
  const { t } = useTranslation()
  const {
    data: businessFunction,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(businessFunctionId), () =>
    fetchBusinessFunction(businessFunctionId),
  )

  if (isError) {
    return (
      <DetailError
        message={t('businessFunctions.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !businessFunction) {
    return <DetailLoading />
  }

  return <BusinessFunctionDetailView businessFunction={businessFunction} />
}

interface EditBusinessFunctionLoaderProps {
  businessFunctionId: number
  onSuccess: (businessFunction: BusinessFunctionDetail) => void
  onCancel: () => void
}

/**
 * Fetches the fresh, re-authorized business function detail before mounting
 * the edit form, so the partial PATCH starts from authoritative values rather
 * than the grid row snapshot.
 */
function EditBusinessFunctionLoader({
  businessFunctionId,
  onSuccess,
  onCancel,
}: EditBusinessFunctionLoaderProps) {
  const { t } = useTranslation()
  const {
    data: businessFunction,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(businessFunctionId), () =>
    fetchBusinessFunction(businessFunctionId),
  )

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive">
          {t('businessFunctions.detail.loadError')}
        </p>
        <Button variant="outline" size="sm" onClick={() => refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (isLoading || !businessFunction) {
    return (
      <div className="flex flex-col gap-4 p-4">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  return (
    <BusinessFunctionForm
      mode={{ type: 'edit', businessFunction }}
      onSuccess={onSuccess}
      onCancel={onCancel}
    />
  )
}
