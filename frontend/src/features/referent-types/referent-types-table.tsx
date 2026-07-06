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
import { TableView, type TableViewHandle } from '@/features/table/table-view'
import type { RowActionHandler } from '@/features/table/row-actions'
import type { TableActionDefinition, TableRow } from '@/features/table/types'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { referentTypeColumnRenderers } from '@/features/referent-types/column-renderers'
import { deleteReferentType, fetchReferentType } from '@/features/referent-types/api'
import { ReferentTypeForm } from '@/features/referent-types/referent-type-form'
import { ReferentTypeDetailView } from '@/features/referent-types/referent-type-detail'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import type { ReferentTypeDetail } from '@/features/referent-types/types'

/** Domain key used to mount the generic table for referent types. */
const REFERENT_TYPES_DOMAIN = 'referent-types'

/** Which sheet (if any) is currently open and for which row. */
type SheetState =
  | { kind: 'none' }
  | { kind: 'create' }
  | { kind: 'view'; row: TableRow }
  | { kind: 'edit'; row: TableRow }

/** Query key for a single referent type's detail (fresh-on-open pattern). */
function detailQueryKey(id: number) {
  return ['referent-types', 'detail', id] as const
}

/**
 * Thin Referent Types adapter over the generic table. It mounts `<TableView>`
 * with the `referent-types` domain, its custom cell renderers and a
 * row-action handler, and owns the CRUD flows: opening a Sheet for
 * view/edit/create, confirming + running the delete mutation, and refreshing
 * the SSRM grid after every mutation via the table's imperative handle. No
 * table logic lives here — only referent-types CRUD wiring. Permission gating
 * is an affordance only; the backend re-authorizes each call.
 */
export function ReferentTypesTable() {
  const { t } = useTranslation()
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
        await deleteReferentType(row.id)
        toast.success(t('referentTypes.form.deleted'))
        refreshGrid()
      } catch (error) {
        const status = axios.isAxiosError(error) ? error.response?.status : undefined
        toast.error(
          status === 403
            ? t('referentTypes.form.deleteForbidden')
            : t('referentTypes.form.deleteError'),
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
    (referentType: ReferentTypeDetail) => {
      closeSheet()
      refreshGrid()
      queryClient.invalidateQueries({ queryKey: detailQueryKey(referentType.id) })
    },
    [closeSheet, refreshGrid, queryClient],
  )

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader
        actions={
          <Can permission="referent-types.create">
            <Button onClick={() => setSheet({ kind: 'create' })}>
              <Plus aria-hidden="true" />
              {t('referentTypes.form.newReferentType')}
            </Button>
          </Can>
        }
      />

      <TableView
        ref={tableRef}
        domain={REFERENT_TYPES_DOMAIN}
        renderers={referentTypeColumnRenderers}
        onAction={handleAction}
        isBusy={isBusy}
      />

      <Sheet open={sheet.kind !== 'none'} onOpenChange={onSheetOpenChange}>
        <SheetContent className="gap-0 sm:max-w-2xl">
          {sheet.kind === 'view' && (
            <>
              <SheetHeader className="sr-only">
                <SheetTitle>{t('referentTypes.detail.title')}</SheetTitle>
                <SheetDescription>{t('referentTypes.detail.subtitle')}</SheetDescription>
              </SheetHeader>
              <ViewReferentTypeLoader referentTypeId={sheet.row.id} />
            </>
          )}

          {sheet.kind === 'create' && (
            <>
              <SheetHeader>
                <SheetTitle>{t('referentTypes.form.createTitle')}</SheetTitle>
                <SheetDescription>{t('referentTypes.form.createSubtitle')}</SheetDescription>
              </SheetHeader>
              <ReferentTypeForm
                mode={{ type: 'create' }}
                onSuccess={onMutationSuccess}
                onCancel={closeSheet}
              />
            </>
          )}

          {sheet.kind === 'edit' && (
            <>
              <SheetHeader>
                <SheetTitle>{t('referentTypes.form.editTitle')}</SheetTitle>
                <SheetDescription>{t('referentTypes.form.editSubtitle')}</SheetDescription>
              </SheetHeader>
              <EditReferentTypeLoader
                referentTypeId={sheet.row.id}
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

interface ViewReferentTypeLoaderProps {
  referentTypeId: number
}

/**
 * Fetches the fresh referent-type detail and hands it down to the
 * (presentational) `ReferentTypeDetailView`, which owns no data-fetching
 * state of its own.
 */
function ViewReferentTypeLoader({ referentTypeId }: ViewReferentTypeLoaderProps) {
  const { t } = useTranslation()
  const {
    data: referentType,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(referentTypeId), () => fetchReferentType(referentTypeId))

  if (isError) {
    return (
      <DetailError
        message={t('referentTypes.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !referentType) {
    return <DetailLoading />
  }

  return <ReferentTypeDetailView referentType={referentType} />
}

interface EditReferentTypeLoaderProps {
  referentTypeId: number
  onSuccess: (referentType: ReferentTypeDetail) => void
  onCancel: () => void
}

/**
 * Fetches the fresh, re-authorized referent-type detail before mounting the
 * edit form, so the partial PATCH starts from authoritative values rather
 * than the grid row snapshot.
 */
function EditReferentTypeLoader({
  referentTypeId,
  onSuccess,
  onCancel,
}: EditReferentTypeLoaderProps) {
  const { t } = useTranslation()
  const {
    data: referentType,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(referentTypeId), () => fetchReferentType(referentTypeId))

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive">{t('referentTypes.detail.loadError')}</p>
        <Button variant="outline" size="sm" onClick={() => refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (isLoading || !referentType) {
    return (
      <div className="flex flex-col gap-4 p-4">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  return (
    <ReferentTypeForm
      mode={{ type: 'edit', referentType }}
      onSuccess={onSuccess}
      onCancel={onCancel}
    />
  )
}
