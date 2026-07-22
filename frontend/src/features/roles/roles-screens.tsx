/* eslint-disable react-refresh/only-export-components -- module registry adapter: exports the `moduleScreen` descriptor alongside its screen components (spec 0042 pattern, same as `project-screens.tsx`) */
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { useTableConfig } from '@/features/table/use-table-config'
import { scalarColumnOptions } from '@/features/table/column-options'
import type { TableConfig } from '@/features/table/types'
import { fetchRole } from '@/features/roles/api'
import { RoleForm } from '@/features/roles/role-form'
import { RoleDetailView } from '@/features/roles/role-detail'
import { OPEN_MODE_MODAL } from '@/features/modules/types'
import type {
  ModuleDetailScreenProps,
  ModuleFormScreenProps,
  ModuleRegistryEntry,
} from '@/features/modules/types'
import type { RoleDetail } from '@/features/roles/types'

/**
 * Content-only `roles` screens for the module registry (spec 0042). Reused
 * as-is by the modal Sheet (`useModuleOpener`) and by the generic dedicated
 * pages (`ModuleDetailPage`/`ModuleFormPage`), which own the surrounding
 * chrome. `RoleDetailView` already owns its own fetch/loading/error, so this
 * screen only forwards the id.
 */
export function RoleDetailScreen({ id }: ModuleDetailScreenProps) {
  return <RoleDetailView roleId={id} />
}

export function RoleFormScreen({ mode, onSuccess, onCancel }: ModuleFormScreenProps) {
  const queryClient = useQueryClient()
  // The generic table loads and caches this config under the same query key,
  // so reading it here (for permission options) is a cache hit, not a 2nd
  // request — same rationale `RolesTable` used before the rewire.
  const { data: config } = useTableConfig('roles')
  const permissionOptions = config ? resolvePermissionOptions(config) : []

  const handleSuccess = (saved: RoleDetail) => {
    queryClient.invalidateQueries({ queryKey: ['roles', 'detail', saved.id] })
    onSuccess(saved.id)
  }

  if (mode.type === 'create') {
    return (
      <RoleForm
        mode={{ type: 'create' }}
        permissionOptions={permissionOptions}
        onSuccess={handleSuccess}
        onCancel={onCancel}
      />
    )
  }

  return (
    <EditRoleLoader
      roleId={mode.id}
      permissionOptions={permissionOptions}
      onSuccess={handleSuccess}
      onCancel={onCancel}
    />
  )
}

/**
 * Resolves the full permission catalogue from the already-loaded table
 * config — the single source of truth shared with the `permissions` set
 * filter/column. Prefers the filter `options`, then falls back to the
 * column `options`. Moved verbatim from `RolesTable`.
 */
function resolvePermissionOptions(config: TableConfig): string[] {
  const filter = config.filters.find((entry) => entry.columnId === 'permissions')
  if (filter?.options && filter.options.length > 0) {
    return filter.options
  }

  return scalarColumnOptions(config.columns.find((entry) => entry.id === 'permissions'))
}

interface EditRoleLoaderProps {
  roleId: number
  permissionOptions: string[]
  onSuccess: (role: RoleDetail) => void
  onCancel: () => void
}

/**
 * Fetches the fresh, re-authorized role detail before mounting the edit
 * form, so the partial PATCH starts from authoritative values rather than a
 * stale snapshot. Moved verbatim from `RolesTable`'s inline loader, which
 * the rewire removed.
 */
function EditRoleLoader({ roleId, permissionOptions, onSuccess, onCancel }: EditRoleLoaderProps) {
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

/** Auto-registered in the module registry (spec 0042). */
export const moduleScreen: ModuleRegistryEntry = {
  domain: 'roles',
  basePath: '/roles',
  defaultMode: OPEN_MODE_MODAL,
  labelKey: 'navigation.roles',
  DetailScreen: RoleDetailScreen,
  FormScreen: RoleFormScreen,
}
