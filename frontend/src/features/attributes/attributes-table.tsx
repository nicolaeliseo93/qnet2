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
import { attributeColumnRenderers } from '@/features/attributes/column-renderers'
import { deleteAttribute, fetchAttribute } from '@/features/attributes/api'
import { AttributeForm } from '@/features/attributes/attribute-form'
import { AttributeDetailView } from '@/features/attributes/attribute-detail'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import type { AttributeDetail } from '@/features/attributes/types'

/** Domain key used to mount the generic table for attributes. */
const ATTRIBUTES_DOMAIN = 'attributes'

/** Which sheet (if any) is currently open and for which row. */
type SheetState =
  | { kind: 'none' }
  | { kind: 'create' }
  | { kind: 'view'; row: TableRow }
  | { kind: 'edit'; row: TableRow }

/** Query key for a single attribute's detail (fresh-on-open pattern). */
function detailQueryKey(id: number) {
  return ['attributes', 'detail', id] as const
}

/**
 * Thin Attributes adapter over the generic table. It mounts `<TableView>`
 * with the `attributes` domain, its custom cell renderers and a row-action
 * handler, and owns the CRUD flows: opening a Sheet for view/edit/create,
 * confirming + running the delete mutation, and refreshing the SSRM grid
 * after every mutation via the table's imperative handle (mirrors
 * `ReferentTypesTable`). Permission gating is an affordance only; the
 * backend re-authorizes each call.
 */
export function AttributesTable() {
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
        await deleteAttribute(row.id)
        toast.success(t('attributes.form.deleted'))
        refreshGrid()
      } catch (error) {
        const status = axios.isAxiosError(error) ? error.response?.status : undefined
        if (status === 403) {
          toast.error(t('attributes.form.deleteForbidden'))
        } else if (status === 409 || status === 422) {
          toast.error(t('attributes.form.deleteInUse'))
        } else {
          toast.error(t('attributes.form.deleteError'))
        }
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
    (attribute: AttributeDetail) => {
      closeSheet()
      refreshGrid()
      queryClient.invalidateQueries({ queryKey: detailQueryKey(attribute.id) })
    },
    [closeSheet, refreshGrid, queryClient],
  )

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader
        actions={
          <Can permission="attributes.create">
            <Button onClick={() => setSheet({ kind: 'create' })}>
              <Plus aria-hidden="true" />
              {t('attributes.form.newAttribute')}
            </Button>
          </Can>
        }
      />

      <TableView
        ref={tableRef}
        domain={ATTRIBUTES_DOMAIN}
        renderers={attributeColumnRenderers}
        onAction={handleAction}
        isBusy={isBusy}
      />

      <Sheet open={sheet.kind !== 'none'} onOpenChange={onSheetOpenChange}>
        <SheetContent className="gap-0" storageKey={`sheet-width:${ATTRIBUTES_DOMAIN}`}>
          {sheet.kind === 'view' && (
            <>
              <SheetHeader className="sr-only">
                <SheetTitle>{t('attributes.detail.title')}</SheetTitle>
                <SheetDescription>{t('attributes.detail.subtitle')}</SheetDescription>
              </SheetHeader>
              <ViewAttributeLoader attributeId={sheet.row.id} />
            </>
          )}

          {sheet.kind === 'create' && (
            <>
              <SheetHeader>
                <SheetTitle>{t('attributes.form.createTitle')}</SheetTitle>
                <SheetDescription>{t('attributes.form.createSubtitle')}</SheetDescription>
              </SheetHeader>
              <AttributeForm
                mode={{ type: 'create' }}
                onSuccess={onMutationSuccess}
                onCancel={closeSheet}
              />
            </>
          )}

          {sheet.kind === 'edit' && (
            <>
              <SheetHeader>
                <SheetTitle>{t('attributes.form.editTitle')}</SheetTitle>
                <SheetDescription>{t('attributes.form.editSubtitle')}</SheetDescription>
              </SheetHeader>
              <EditAttributeLoader
                attributeId={sheet.row.id}
                onSuccess={onMutationSuccess}
                onCancel={closeSheet}
              />
            </>
          )}
        </SheetContent>
      </Sheet>

      <ResourceActivityDialog
        resource={ATTRIBUTES_DOMAIN}
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

interface ViewAttributeLoaderProps {
  attributeId: number
}

/**
 * Fetches the fresh attribute detail and hands it down to the
 * (presentational) `AttributeDetailView`, which owns no data-fetching state
 * of its own.
 */
function ViewAttributeLoader({ attributeId }: ViewAttributeLoaderProps) {
  const { t } = useTranslation()
  const {
    data: attribute,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(attributeId), () => fetchAttribute(attributeId))

  if (isError) {
    return (
      <DetailError
        message={t('attributes.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !attribute) {
    return <DetailLoading />
  }

  return <AttributeDetailView attribute={attribute} />
}

interface EditAttributeLoaderProps {
  attributeId: number
  onSuccess: (attribute: AttributeDetail) => void
  onCancel: () => void
}

/**
 * Fetches the fresh, re-authorized attribute detail before mounting the edit
 * form, so the partial PATCH starts from authoritative values rather than the
 * grid row snapshot.
 */
function EditAttributeLoader({ attributeId, onSuccess, onCancel }: EditAttributeLoaderProps) {
  const { t } = useTranslation()
  const {
    data: attribute,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(attributeId), () => fetchAttribute(attributeId))

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive">{t('attributes.detail.loadError')}</p>
        <Button variant="outline" size="sm" onClick={() => refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (isLoading || !attribute) {
    return (
      <div className="flex flex-col gap-4 p-4">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  return (
    <AttributeForm mode={{ type: 'edit', attribute }} onSuccess={onSuccess} onCancel={onCancel} />
  )
}
