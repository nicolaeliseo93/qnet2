import { useCallback, useMemo, useRef, useState } from 'react'
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
import { useTableConfig } from '@/features/table/use-table-config'
import type { RowActionHandler } from '@/features/table/row-actions'
import type {
  TableActionDefinition,
  TableConfig,
  TableRow,
} from '@/features/table/types'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { roleColumnRenderers } from '@/features/roles/column-renderers'
import { deleteRole, fetchRole } from '@/features/roles/api'
import { RoleForm } from '@/features/roles/role-form'
import { RoleDetailView } from '@/features/roles/role-detail'

/** Domain key used to mount the generic table for roles. */
const ROLES_DOMAIN = 'roles'

/** The privileged system role that can never be edited or deleted. */
const SYSTEM_ROLE = 'super-admin'

/** Which sheet (if any) is currently open and for which row. */
type SheetState =
  | { kind: 'none' }
  | { kind: 'create' }
  | { kind: 'view'; row: TableRow }
  | { kind: 'edit'; row: TableRow }

/**
 * Thin Roles adapter over the generic table. It mounts `<TableView>` with the
 * `roles` domain, its custom cell renderers and a row-action handler, and owns
 * the CRUD flows: opening a Sheet for view/edit/create, confirming + running the
 * delete mutation, and refreshing the SSRM grid after every mutation. No table
 * logic lives here — only roles CRUD wiring. Permission gating is an affordance
 * only; the backend re-authorizes each call.
 */
export function RolesTable() {
  const { t } = useTranslation()

  // The generic table loads and caches this config under the same query key, so
  // reading it here (for permission options) is a cache hit, not a 2nd request.
  const { data: config } = useTableConfig(ROLES_DOMAIN)
  const permissionOptions = useMemo(
    () => (config ? resolvePermissionOptions(config) : []),
    [config],
  )

  const tableRef = useRef<TableViewHandle>(null)
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])

  const [sheet, setSheet] = useState<SheetState>({ kind: 'none' })
  const [deletingId, setDeletingId] = useState<number | null>(null)

  const closeSheet = useCallback(() => setSheet({ kind: 'none' }), [])

  const runDelete = useCallback(
    async (row: TableRow) => {
      setDeletingId(row.id)
      try {
        await deleteRole(row.id)
        toast.success(t('roles.form.deleted'))
        refreshGrid()
      } catch (error) {
        const status = axios.isAxiosError(error) ? error.response?.status : undefined
        toast.error(
          status === 403
            ? t('roles.form.deleteForbidden')
            : t('roles.form.deleteError'),
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

  // Hide edit/delete on the protected super-admin system role (mutations are
  // forbidden server-side; this avoids offering dead-end actions). The backend
  // already omits them, this is a belt-and-braces affordance.
  const decorateRow = useCallback((row: TableRow): TableRow => {
    if (row.name === SYSTEM_ROLE) {
      return {
        ...row,
        actions: row.actions.filter((key) => key !== 'edit' && key !== 'delete'),
      }
    }
    return row
  }, [])

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
          <Can permission="roles.create">
            <Button onClick={() => setSheet({ kind: 'create' })}>
              <Plus aria-hidden="true" />
              {t('roles.form.newRole')}
            </Button>
          </Can>
        }
      />

      <TableView
        ref={tableRef}
        domain={ROLES_DOMAIN}
        renderers={roleColumnRenderers}
        onAction={handleAction}
        isBusy={isBusy}
        decorateRow={decorateRow}
      />

      <Sheet open={sheet.kind !== 'none'} onOpenChange={onSheetOpenChange}>
        <SheetContent className="gap-0" storageKey={`sheet-width:${ROLES_DOMAIN}`}>
          {sheet.kind === 'view' && (
            <>
              <SheetHeader className="sr-only">
                <SheetTitle>{t('roles.detail.title')}</SheetTitle>
                <SheetDescription>{t('roles.detail.subtitle')}</SheetDescription>
              </SheetHeader>
              <RoleDetailView roleId={sheet.row.id} />
            </>
          )}

          {sheet.kind === 'create' && (
            <>
              <SheetHeader>
                <SheetTitle>{t('roles.form.createTitle')}</SheetTitle>
                <SheetDescription>
                  {t('roles.form.createSubtitle')}
                </SheetDescription>
              </SheetHeader>
              <RoleForm
                mode={{ type: 'create' }}
                permissionOptions={permissionOptions}
                onSuccess={onMutationSuccess}
                onCancel={closeSheet}
              />
            </>
          )}

          {sheet.kind === 'edit' && (
            <>
              <SheetHeader>
                <SheetTitle>{t('roles.form.editTitle')}</SheetTitle>
                <SheetDescription>
                  {t('roles.form.editSubtitle')}
                </SheetDescription>
              </SheetHeader>
              <EditRoleLoader
                roleId={sheet.row.id}
                permissionOptions={permissionOptions}
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

/**
 * Resolves the full permission catalogue from the already-loaded table config —
 * the single source of truth shared with the `permissions` set filter/column.
 * Prefers the filter `options`, then falls back to the column `options`.
 */
function resolvePermissionOptions(config: TableConfig): string[] {
  const filter = config.filters.find((entry) => entry.columnId === 'permissions')
  if (filter?.options && filter.options.length > 0) {
    return filter.options
  }

  const column = config.columns.find((entry) => entry.id === 'permissions')
  return column?.options ?? []
}

interface EditRoleLoaderProps {
  roleId: number
  permissionOptions: string[]
  onSuccess: () => void
  onCancel: () => void
}

/**
 * Fetches the fresh, re-authorized role detail before mounting the edit form, so
 * the partial PATCH starts from authoritative values rather than the grid row
 * snapshot.
 */
function EditRoleLoader({
  roleId,
  permissionOptions,
  onSuccess,
  onCancel,
}: EditRoleLoaderProps) {
  const { t } = useTranslation()
  const {
    data: role,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(['roles', 'detail', roleId], () => fetchRole(roleId))

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive">{t('roles.detail.loadError')}</p>
        <Button variant="outline" size="sm" onClick={() => refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (isLoading || !role) {
    return (
      <div className="flex flex-col gap-4 p-4">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  return (
    <RoleForm
      mode={{ type: 'edit', role }}
      permissionOptions={permissionOptions}
      onSuccess={onSuccess}
      onCancel={onCancel}
    />
  )
}
