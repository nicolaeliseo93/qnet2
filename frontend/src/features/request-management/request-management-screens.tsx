/* eslint-disable react-refresh/only-export-components -- registry adapter: components + moduleScreen descriptor colocated by design (spec 0042) */
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { OPEN_MODE_PAGE } from '@/features/modules/types'
import type { ModuleFormScreenProps, ModuleRegistryEntry } from '@/features/modules/types'
import { RequestWorkPanelScreen } from '@/features/request-management/request-work-panel'
import { REQUEST_MANAGEMENT_DOMAIN } from '@/features/request-management/types'

/**
 * There is no create/edit form for this module (spec 0049 D-9/D-10): the
 * record IS an Opportunity, never created or deleted from Request
 * Management. `FormScreen` only exists because `ModuleRegistryEntry`
 * requires one — it renders a not-applicable notice and is never reachable
 * in practice: `RequestManagementTable` only ever calls `openView` (never
 * `openCreate`/`openEdit`/`openDuplicate`), and `generateRoutes: false`
 * means no `new`/`:id/edit`/`:id/duplicate` deep-link route is generated
 * either.
 */
function RequestManagementFormScreen({ onCancel }: ModuleFormScreenProps) {
  const { t } = useTranslation()
  return (
    <div className="flex flex-col items-start gap-3 p-4">
      <p className="text-sm text-muted-foreground">
        {t('requestManagement.form.notApplicable', {
          defaultValue: 'Request Management has no create or edit form: work the record from its detail panel.',
        })}
      </p>
      <Button variant="outline" size="sm" onClick={onCancel}>
        {t('common.cancel')}
      </Button>
    </div>
  )
}

/**
 * Auto-registered in the module registry (spec 0042). `defaultMode = 'page'`
 * (D-9: the record's own read-only context, contacts and dynamic fields
 * warrant the richer dedicated page over a Sheet); `generateRoutes: false`
 * since the list route (`/request-management`) and the `:id` deep-link are
 * both declared by hand in `router.tsx` (D-9: no generic `new`/`:id/edit`
 * routes for a module with no create/edit form).
 */
export const moduleScreen: ModuleRegistryEntry = {
  domain: REQUEST_MANAGEMENT_DOMAIN,
  basePath: '/request-management',
  defaultMode: OPEN_MODE_PAGE,
  labelKey: 'navigation.requestManagement',
  DetailScreen: RequestWorkPanelScreen,
  FormScreen: RequestManagementFormScreen,
  generateRoutes: false,
}
