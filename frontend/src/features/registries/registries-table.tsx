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
import { registryColumnRenderers } from '@/features/registries/column-renderers'
import { deleteRegistry, fetchRegistry } from '@/features/registries/api'
import { RegistryForm } from '@/features/registries/registry-form'
import { RegistryDetailView } from '@/features/registries/registry-detail'
import { DetailError, DetailLoading } from '@/components/detail/detail-panel'
import type { RegistryDetail } from '@/features/registries/types'

/** Domain key used to mount the generic table for registries. */
const REGISTRIES_DOMAIN = 'registries'

/** Which sheet (if any) is currently open and for which row. */
type SheetState =
  | { kind: 'none' }
  | { kind: 'create' }
  | { kind: 'view'; row: TableRow }
  | { kind: 'edit'; row: TableRow }

/** Query key for a single registry's detail (fresh-on-open pattern). */
function detailQueryKey(id: number) {
  return ['registries', 'detail', id] as const
}

/**
 * Thin Registries adapter over the generic table. It mounts `<TableView>` with
 * the `registries` domain, its custom cell renderers and a row-action handler,
 * and owns the CRUD flows: opening a Sheet for view/edit/create, confirming +
 * running the delete mutation, and refreshing the SSRM grid after every
 * mutation via the table's imperative handle. No table logic lives here —
 * only registries CRUD wiring. Permission gating is an affordance only; the
 * backend re-authorizes each call.
 */
export function RegistriesTable() {
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
        await deleteRegistry(row.id)
        toast.success(t('registries.form.deleted'))
        refreshGrid()
      } catch (error) {
        const status = axios.isAxiosError(error) ? error.response?.status : undefined
        toast.error(
          status === 403 ? t('registries.form.deleteForbidden') : t('registries.form.deleteError'),
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
    (registry: RegistryDetail) => {
      closeSheet()
      refreshGrid()
      queryClient.invalidateQueries({ queryKey: detailQueryKey(registry.id) })
    },
    [closeSheet, refreshGrid, queryClient],
  )

  return (
    <div className="flex flex-1 flex-col gap-4">
      <PageHeader
        actions={
          <Can permission="registries.create">
            <Button onClick={() => setSheet({ kind: 'create' })}>
              <Plus aria-hidden="true" />
              {t('registries.form.newRegistry')}
            </Button>
          </Can>
        }
      />

      <TableView
        ref={tableRef}
        domain={REGISTRIES_DOMAIN}
        renderers={registryColumnRenderers}
        onAction={handleAction}
        isBusy={isBusy}
      />

      <Sheet open={sheet.kind !== 'none'} onOpenChange={onSheetOpenChange}>
        <SheetContent className="gap-0 sm:max-w-2xl">
          {sheet.kind === 'view' && (
            <>
              <SheetHeader className="sr-only">
                <SheetTitle>{t('registries.detail.title')}</SheetTitle>
                <SheetDescription>{t('registries.detail.subtitle')}</SheetDescription>
              </SheetHeader>
              <ViewRegistryLoader registryId={sheet.row.id} />
            </>
          )}

          {sheet.kind === 'create' && (
            <>
              <SheetHeader>
                <SheetTitle>{t('registries.form.createTitle')}</SheetTitle>
                <SheetDescription>{t('registries.form.createSubtitle')}</SheetDescription>
              </SheetHeader>
              <RegistryForm
                mode={{ type: 'create' }}
                onSuccess={onMutationSuccess}
                onCancel={closeSheet}
              />
            </>
          )}

          {sheet.kind === 'edit' && (
            <>
              <SheetHeader>
                <SheetTitle>{t('registries.form.editTitle')}</SheetTitle>
                <SheetDescription>{t('registries.form.editSubtitle')}</SheetDescription>
              </SheetHeader>
              <EditRegistryLoader
                registryId={sheet.row.id}
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

interface ViewRegistryLoaderProps {
  registryId: number
}

/**
 * Fetches the fresh registry detail and hands it down to the (presentational)
 * `RegistryDetailView`, which owns no data-fetching state of its own.
 */
function ViewRegistryLoader({ registryId }: ViewRegistryLoaderProps) {
  const { t } = useTranslation()
  const {
    data: registry,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(registryId), () => fetchRegistry(registryId))

  if (isError) {
    return (
      <DetailError
        message={t('registries.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !registry) {
    return <DetailLoading />
  }

  return <RegistryDetailView registry={registry} />
}

interface EditRegistryLoaderProps {
  registryId: number
  onSuccess: (registry: RegistryDetail) => void
  onCancel: () => void
}

/**
 * Fetches the fresh, re-authorized registry detail before mounting the edit
 * form, so the partial PATCH starts from authoritative values rather than the
 * grid row snapshot.
 */
function EditRegistryLoader({ registryId, onSuccess, onCancel }: EditRegistryLoaderProps) {
  const { t } = useTranslation()
  const {
    data: registry,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(detailQueryKey(registryId), () => fetchRegistry(registryId))

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive">{t('registries.detail.loadError')}</p>
        <Button variant="outline" size="sm" onClick={() => refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (isLoading || !registry) {
    return (
      <div className="flex flex-col gap-4 p-4">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  return <RegistryForm mode={{ type: 'edit', registry }} onSuccess={onSuccess} onCancel={onCancel} />
}
