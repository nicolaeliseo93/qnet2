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
import { customFieldColumnRenderers } from '@/features/custom-fields/column-renderers'
import { deleteCustomFieldDefinition, fetchCustomFieldDefinition } from '@/features/custom-fields/api'
import { CustomFieldDefinitionForm } from '@/features/custom-fields/custom-field-definition-form'
import { CustomFieldDetailView } from '@/features/custom-fields/custom-field-detail'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import type { CustomFieldDefinitionDetail } from '@/features/custom-fields/types'

/** Domain key used to mount the generic table for the admin custom-fields catalogue. */
const CUSTOM_FIELDS_DOMAIN = 'custom-fields'

/** Which sheet (if any) is currently open and for which row. */
type SheetState =
  | { kind: 'none' }
  | { kind: 'create' }
  | { kind: 'view'; row: TableRow }
  | { kind: 'edit'; row: TableRow }

/** Query key for a single definition's detail (fresh-on-open pattern). */
function detailQueryKey(id: number) {
  return ['custom-fields', 'detail', id] as const
}

/**
 * Thin admin adapter over the generic table (spec 0021 AC-025). It mounts
 * `<TableView>` with the `custom-fields` domain, its custom cell renderers
 * and a row-action handler, and owns the CRUD flows: opening a Sheet for
 * view/edit/create, confirming + running the delete mutation, and refreshing
 * the SSRM grid after every mutation via the table's imperative handle
 * (mirrors `AttributesTable`). Permission gating is an affordance only: the
 * backend re-authorizes each call.
 */
export function CustomFieldsTable() {
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
        await deleteCustomFieldDefinition(row.id)
        toast.success(t('customFields.form.deleted'))
        refreshGrid()
      } catch (error) {
        const status = axios.isAxiosError(error) ? error.response?.status : undefined
        if (status === 403) {
          toast.error(t('customFields.form.deleteForbidden'))
        } else {
          toast.error(t('customFields.form.deleteError'))
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
    (definition: CustomFieldDefinitionDetail) => {
      closeSheet()
      refreshGrid()
      queryClient.invalidateQueries({ queryKey: detailQueryKey(definition.id) })
    },
    [closeSheet, refreshGrid, queryClient],
  )

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader
        actions={
          <Can permission="custom-fields.create">
            <Button onClick={() => setSheet({ kind: 'create' })}>
              <Plus aria-hidden="true" />
              {t('customFields.form.newDefinition')}
            </Button>
          </Can>
        }
      />

      <TableView
        ref={tableRef}
        domain={CUSTOM_FIELDS_DOMAIN}
        renderers={customFieldColumnRenderers}
        onAction={handleAction}
        isBusy={isBusy}
      />

      <Sheet open={sheet.kind !== 'none'} onOpenChange={onSheetOpenChange}>
        <SheetContent className="gap-0" storageKey={`sheet-width:${CUSTOM_FIELDS_DOMAIN}`}>
          {sheet.kind === 'view' && (
            <>
              <SheetHeader className="sr-only">
                <SheetTitle>{t('customFields.detail.title')}</SheetTitle>
                <SheetDescription>{t('customFields.detail.subtitle')}</SheetDescription>
              </SheetHeader>
              <ViewDefinitionLoader definitionId={sheet.row.id} />
            </>
          )}

          {sheet.kind === 'create' && (
            <>
              <SheetHeader>
                <SheetTitle>{t('customFields.form.createTitle')}</SheetTitle>
                <SheetDescription>{t('customFields.form.createSubtitle')}</SheetDescription>
              </SheetHeader>
              <CustomFieldDefinitionForm
                mode={{ type: 'create' }}
                onSuccess={onMutationSuccess}
                onCancel={closeSheet}
              />
            </>
          )}

          {sheet.kind === 'edit' && (
            <>
              <SheetHeader>
                <SheetTitle>{t('customFields.form.editTitle')}</SheetTitle>
                <SheetDescription>{t('customFields.form.editSubtitle')}</SheetDescription>
              </SheetHeader>
              <EditDefinitionLoader
                definitionId={sheet.row.id}
                onSuccess={onMutationSuccess}
                onCancel={closeSheet}
              />
            </>
          )}
        </SheetContent>
      </Sheet>

      <ResourceActivityDialog
        resource={CUSTOM_FIELDS_DOMAIN}
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

interface ViewDefinitionLoaderProps {
  definitionId: number
}

/**
 * Fetches the fresh definition detail and hands it down to the
 * (presentational) `CustomFieldDetailView`, which owns no data-fetching state
 * of its own.
 */
function ViewDefinitionLoader({ definitionId }: ViewDefinitionLoaderProps) {
  const { t } = useTranslation()
  const {
    data: definition,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(definitionId), () => fetchCustomFieldDefinition(definitionId))

  if (isError) {
    return (
      <DetailError
        message={t('customFields.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !definition) {
    return <DetailLoading />
  }

  return <CustomFieldDetailView definition={definition} />
}

interface EditDefinitionLoaderProps {
  definitionId: number
  onSuccess: (definition: CustomFieldDefinitionDetail) => void
  onCancel: () => void
}

/**
 * Fetches the fresh, re-authorized definition detail before mounting the
 * edit form, so the partial PATCH starts from authoritative values rather
 * than the grid row snapshot.
 */
function EditDefinitionLoader({ definitionId, onSuccess, onCancel }: EditDefinitionLoaderProps) {
  const { t } = useTranslation()
  const {
    data: definition,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(definitionId), () => fetchCustomFieldDefinition(definitionId))

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive">{t('customFields.detail.loadError')}</p>
        <Button variant="outline" size="sm" onClick={() => refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (isLoading || !definition) {
    return (
      <div className="flex flex-col gap-4 p-4">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  return (
    <CustomFieldDefinitionForm
      mode={{ type: 'edit', definition }}
      onSuccess={onSuccess}
      onCancel={onCancel}
    />
  )
}
