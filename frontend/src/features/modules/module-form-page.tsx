import { useCallback } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate, useParams } from 'react-router-dom'
import { Can } from '@/features/auth/can'
import { PageHeader } from '@/components/page-header'
import { getModuleRegistryEntry } from '@/features/modules/module-registry'
import type { ModuleFormScreenMode } from '@/features/modules/types'
import { parseEntityId } from '@/routes/entity-id'
import NotFoundPage from '@/pages/not-found-page'

interface ModuleFormPageProps {
  /** The registry domain this route was generated for (spec 0042). */
  domain: string
}

/**
 * Generic dedicated create/edit page for any registered module (spec 0042).
 * One page serves both `${basePath}/new` (no `:id`) and `${basePath}/:id/edit`,
 * gated by the matching `.create`/`.update` permission. Replaces the
 * per-module `*-form-page.tsx` files for the 4 Wave 0 modules: the header
 * chrome (title/subtitle) is generic because every module already follows
 * the same `${domain}.form.{create,edit}{Title,Subtitle}` i18n convention;
 * everything else (fetch-for-edit, the actual form) lives in the domain's
 * `FormScreen`.
 */
export default function ModuleFormPage({ domain }: ModuleFormPageProps) {
  const { t } = useTranslation()
  const { id } = useParams()
  const navigate = useNavigate()

  const entry = getModuleRegistryEntry(domain)
  // Only used so the hooks below stay unconditional (rules-of-hooks) up to
  // the invariant check right before render.
  const basePath = entry?.basePath ?? ''

  const isEdit = id !== undefined
  const entityId = parseEntityId(id)

  const onSuccess = useCallback(
    (savedId: number) => {
      void navigate(`${basePath}/${savedId}`)
    },
    [navigate, basePath],
  )

  const onCancel = useCallback(() => {
    void navigate(isEdit ? `${basePath}/${entityId}` : basePath)
  }, [isEdit, navigate, basePath, entityId])

  if (!entry) {
    throw new Error(`ModuleFormPage: "${domain}" is not registered in the module registry.`)
  }

  if (isEdit && entityId === null) {
    return <NotFoundPage />
  }

  const { FormScreen } = entry

  // Narrow on `entityId` itself (not `isEdit`) so TS refines it to `number` in
  // the edit branch without a cast: the `isEdit && entityId === null` case
  // already returned above, so here `entityId !== null` is exactly "edit".
  let mode: ModuleFormScreenMode
  if (entityId !== null) {
    mode = { type: 'edit', id: entityId }
  } else {
    mode = { type: 'create' }
  }

  return (
    <Can
      permission={`${domain}.${isEdit ? 'update' : 'create'}`}
      fallback={<p className="text-sm text-muted-foreground">{t(`${domain}.forbidden`)}</p>}
    >
      <div className="flex flex-1 flex-col gap-4">
        <PageHeader />

        <div className="flex flex-1 flex-col overflow-hidden rounded-lg border bg-card">
          <header className="flex flex-col gap-1 border-b px-4 py-3">
            <h2 className="text-base font-semibold">
              {t(`${domain}.form.${isEdit ? 'edit' : 'create'}Title`)}
            </h2>
            <p className="text-sm text-muted-foreground">
              {t(`${domain}.form.${isEdit ? 'edit' : 'create'}Subtitle`)}
            </p>
          </header>

          <FormScreen mode={mode} onSuccess={onSuccess} onCancel={onCancel} />
        </div>
      </div>
    </Can>
  )
}
