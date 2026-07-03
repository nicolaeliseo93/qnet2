import { useCallback, useRef, useState } from 'react'
import { useTranslation } from 'react-i18next'
import axios from 'axios'
import { Plus, Upload } from 'lucide-react'
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
import { DropdownMenuItem } from '@/components/ui/dropdown-menu'
import { Can } from '@/features/auth/can'
import { useAuth } from '@/features/auth/use-auth'
import { TableView, type TableViewHandle } from '@/features/table/table-view'
import type { RowActionHandler } from '@/features/table/row-actions'
import type {
  TableActionDefinition,
  TableRow,
} from '@/features/table/types'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { userColumnRenderers } from '@/features/users/column-renderers'
import { deleteUser, fetchUser } from '@/features/users/api'
import { UserForm } from '@/features/users/user-form'
import { UserDetailView } from '@/features/users/user-detail'
import { ImportDialog } from '@/features/imports/import-dialog'

/** Domain key used to mount the generic table for users. */
const USERS_DOMAIN = 'users'

/** Which sheet (if any) is currently open and for which row. */
type SheetState =
  | { kind: 'none' }
  | { kind: 'create' }
  | { kind: 'view'; row: TableRow }
  | { kind: 'edit'; row: TableRow }

/**
 * Thin Users adapter over the generic table. It mounts `<TableView>` with the
 * `users` domain, its custom cell renderers and a row-action handler, and owns
 * the CRUD flows: opening a Sheet for view/edit/create, confirming + running the
 * delete mutation, and refreshing the SSRM grid after every mutation via the
 * table's imperative handle. No table logic lives here — only users CRUD wiring.
 * Permission gating is an affordance only; the backend re-authorizes each call.
 */
export function UsersTable() {
  const { t } = useTranslation()
  const { user: currentUser } = useAuth()

  const tableRef = useRef<TableViewHandle>(null)
  const refreshGrid = useCallback(() => tableRef.current?.refresh(), [])

  const [sheet, setSheet] = useState<SheetState>({ kind: 'none' })
  const [deletingId, setDeletingId] = useState<number | null>(null)
  const [importOpen, setImportOpen] = useState(false)

  const closeSheet = useCallback(() => setSheet({ kind: 'none' }), [])

  const runDelete = useCallback(
    async (row: TableRow) => {
      setDeletingId(row.id)
      try {
        await deleteUser(row.id)
        toast.success(t('users.form.deleted'))
        refreshGrid()
      } catch (error) {
        // 403 covers self-delete (and any other policy denial) surfaced as a
        // dedicated message; everything else falls back to a generic error.
        const status = axios.isAxiosError(error) ? error.response?.status : undefined
        toast.error(
          status === 403
            ? t('users.form.deleteForbidden')
            : t('users.form.deleteError'),
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

  // Hide the delete action on the current user's own row (self-delete is
  // forbidden server-side; this avoids offering a dead-end action).
  const decorateRow = useCallback(
    (row: TableRow): TableRow => {
      if (currentUser && row.id === currentUser.id) {
        return { ...row, actions: row.actions.filter((key) => key !== 'delete') }
      }
      return row
    },
    [currentUser],
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
          <Can permission="users.create">
            <Button onClick={() => setSheet({ kind: 'create' })}>
              <Plus aria-hidden="true" />
              {t('users.form.newUser')}
            </Button>
          </Can>
        }
      />

      <TableView
        ref={tableRef}
        domain={USERS_DOMAIN}
        renderers={userColumnRenderers}
        onAction={handleAction}
        isBusy={isBusy}
        decorateRow={decorateRow}
        importSlot={
          <Can permission="users.import">
            <DropdownMenuItem
              onSelect={(event) => {
                event.preventDefault()
                setImportOpen(true)
              }}
            >
              <Upload aria-hidden="true" />
              {t('imports.action')}
            </DropdownMenuItem>
          </Can>
        }
      />

      <ImportDialog
        domain={USERS_DOMAIN}
        resource={USERS_DOMAIN}
        open={importOpen}
        onOpenChange={setImportOpen}
      />

      <Sheet open={sheet.kind !== 'none'} onOpenChange={onSheetOpenChange}>
        <SheetContent className="gap-0 sm:max-w-2xl">
          {sheet.kind === 'view' && (
            <>
              <SheetHeader>
                <SheetTitle>{t('users.detail.title')}</SheetTitle>
                <SheetDescription>{t('users.detail.subtitle')}</SheetDescription>
              </SheetHeader>
              <UserDetailView userId={sheet.row.id} />
            </>
          )}

          {sheet.kind === 'create' && (
            <>
              <SheetHeader>
                <SheetTitle>{t('users.form.createTitle')}</SheetTitle>
                <SheetDescription>
                  {t('users.form.createSubtitle')}
                </SheetDescription>
              </SheetHeader>
              <UserForm
                mode={{ type: 'create' }}
                onSuccess={onMutationSuccess}
                onCancel={closeSheet}
              />
            </>
          )}

          {sheet.kind === 'edit' && (
            <>
              <SheetHeader>
                <SheetTitle>{t('users.form.editTitle')}</SheetTitle>
                <SheetDescription>
                  {t('users.form.editSubtitle')}
                </SheetDescription>
              </SheetHeader>
              <EditUserLoader
                userId={sheet.row.id}
                onSuccess={onMutationSuccess}
                onCancel={closeSheet}
                onAvatarChange={refreshGrid}
              />
            </>
          )}
        </SheetContent>
      </Sheet>
    </div>
  )
}

interface EditUserLoaderProps {
  userId: number
  onSuccess: () => void
  onCancel: () => void
  onAvatarChange: () => void
}

/**
 * Fetches the fresh, re-authorized user detail before mounting the edit form,
 * so the partial PATCH starts from authoritative values rather than the grid
 * row snapshot.
 */
function EditUserLoader({
  userId,
  onSuccess,
  onCancel,
  onAvatarChange,
}: EditUserLoaderProps) {
  const { t } = useTranslation()
  const {
    data: user,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(['users', 'detail', userId], () => fetchUser(userId))

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive">{t('users.detail.loadError')}</p>
        <Button variant="outline" size="sm" onClick={() => refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (isLoading || !user) {
    return (
      <div className="flex flex-col gap-4 p-4">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  return (
    <UserForm
      mode={{ type: 'edit', user }}
      onSuccess={onSuccess}
      onCancel={onCancel}
      onAvatarChange={onAvatarChange}
    />
  )
}
