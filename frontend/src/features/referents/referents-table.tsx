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
import { referentColumnRenderers } from '@/features/referents/column-renderers'
import { deleteReferent, fetchReferent } from '@/features/referents/api'
import { ReferentForm } from '@/features/referents/referent-form'
import { ReferentDetailView } from '@/features/referents/referent-detail'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import type { ReferentDetail } from '@/features/referents/types'

/** Domain key used to mount the generic table for referents. */
const REFERENTS_DOMAIN = 'referents'

/** Which sheet (if any) is currently open and for which row. */
type SheetState =
  | { kind: 'none' }
  | { kind: 'create' }
  | { kind: 'view'; row: TableRow }
  | { kind: 'edit'; row: TableRow }

/** Query key for a single referent's detail (fresh-on-open pattern). */
function detailQueryKey(id: number) {
  return ['referents', 'detail', id] as const
}

/**
 * Thin Referents adapter over the generic table. It mounts `<TableView>` with
 * the `referents` domain, its custom cell renderers and a row-action handler,
 * and owns the CRUD flows: opening a Sheet for view/edit/create, confirming +
 * running the delete mutation, and refreshing the SSRM grid after every
 * mutation via the table's imperative handle. No table logic lives here —
 * only referents CRUD wiring. Permission gating is an affordance only; the
 * backend re-authorizes each call.
 */
export function ReferentsTable() {
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
        await deleteReferent(row.id)
        toast.success(t('referents.form.deleted'))
        refreshGrid()
      } catch (error) {
        const status = axios.isAxiosError(error) ? error.response?.status : undefined
        toast.error(
          status === 403 ? t('referents.form.deleteForbidden') : t('referents.form.deleteError'),
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
    (referent: ReferentDetail) => {
      closeSheet()
      refreshGrid()
      queryClient.invalidateQueries({ queryKey: detailQueryKey(referent.id) })
    },
    [closeSheet, refreshGrid, queryClient],
  )

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader
        actions={
          <Can permission="referents.create">
            <Button onClick={() => setSheet({ kind: 'create' })}>
              <Plus aria-hidden="true" />
              {t('referents.form.newReferent')}
            </Button>
          </Can>
        }
      />

      <TableView
        ref={tableRef}
        domain={REFERENTS_DOMAIN}
        renderers={referentColumnRenderers}
        onAction={handleAction}
        isBusy={isBusy}
      />

      <Sheet open={sheet.kind !== 'none'} onOpenChange={onSheetOpenChange}>
        <SheetContent className="gap-0 sm:max-w-2xl">
          {sheet.kind === 'view' && (
            <>
              <SheetHeader className="sr-only">
                <SheetTitle>{t('referents.detail.title')}</SheetTitle>
                <SheetDescription>{t('referents.detail.subtitle')}</SheetDescription>
              </SheetHeader>
              <ViewReferentLoader referentId={sheet.row.id} />
            </>
          )}

          {sheet.kind === 'create' && (
            <>
              <SheetHeader>
                <SheetTitle>{t('referents.form.createTitle')}</SheetTitle>
                <SheetDescription>{t('referents.form.createSubtitle')}</SheetDescription>
              </SheetHeader>
              <ReferentForm
                mode={{ type: 'create' }}
                onSuccess={onMutationSuccess}
                onCancel={closeSheet}
              />
            </>
          )}

          {sheet.kind === 'edit' && (
            <>
              <SheetHeader>
                <SheetTitle>{t('referents.form.editTitle')}</SheetTitle>
                <SheetDescription>{t('referents.form.editSubtitle')}</SheetDescription>
              </SheetHeader>
              <EditReferentLoader
                referentId={sheet.row.id}
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

interface ViewReferentLoaderProps {
  referentId: number
}

/**
 * Fetches the fresh referent detail and hands it down to the (presentational)
 * `ReferentDetailView`, which owns no data-fetching state of its own.
 */
function ViewReferentLoader({ referentId }: ViewReferentLoaderProps) {
  const { t } = useTranslation()
  const {
    data: referent,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(referentId), () => fetchReferent(referentId))

  if (isError) {
    return (
      <DetailError
        message={t('referents.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !referent) {
    return <DetailLoading />
  }

  return <ReferentDetailView referent={referent} />
}

interface EditReferentLoaderProps {
  referentId: number
  onSuccess: (referent: ReferentDetail) => void
  onCancel: () => void
}

/**
 * Fetches the fresh, re-authorized referent detail before mounting the edit
 * form, so the partial PATCH starts from authoritative values rather than the
 * grid row snapshot.
 */
function EditReferentLoader({ referentId, onSuccess, onCancel }: EditReferentLoaderProps) {
  const { t } = useTranslation()
  const {
    data: referent,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(referentId), () => fetchReferent(referentId))

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive">{t('referents.detail.loadError')}</p>
        <Button variant="outline" size="sm" onClick={() => refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (isLoading || !referent) {
    return (
      <div className="flex flex-col gap-4 p-4">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  return <ReferentForm mode={{ type: 'edit', referent }} onSuccess={onSuccess} onCancel={onCancel} />
}
