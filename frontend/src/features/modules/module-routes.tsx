import type { RouteObject } from 'react-router-dom'
import { MODULE_REGISTRY } from '@/features/modules/module-registry'
import ModuleDetailPage from '@/features/modules/module-detail-page'
import ModuleFormPage from '@/features/modules/module-form-page'

/**
 * Generates the `new`/`:id`/`:id/edit` deep-link routes for every registered
 * module (spec 0042, AC-012/AC-022): the list route (`basePath` itself)
 * stays declared manually in `router.tsx`, same as every module not yet in
 * the registry (see `ModuleRegistryEntry` for why). Meant to be spread into
 * the same route array `router.tsx` builds by hand for the rest.
 */
export function buildModuleRoutes(): RouteObject[] {
  return MODULE_REGISTRY.filter((entry) => entry.generateRoutes !== false).flatMap((entry) => {
    const base = entry.basePath.replace(/^\//, '')
    return [
      { path: `${base}/new`, element: <ModuleFormPage domain={entry.domain} /> },
      { path: `${base}/:id`, element: <ModuleDetailPage domain={entry.domain} /> },
      { path: `${base}/:id/edit`, element: <ModuleFormPage domain={entry.domain} /> },
      { path: `${base}/:id/duplicate`, element: <ModuleFormPage domain={entry.domain} variant="duplicate" /> },
    ]
  })
}
