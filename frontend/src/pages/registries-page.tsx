import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { RegistriesTable } from '@/features/registries/registries-table'

/**
 * Registries ("Anagrafiche") page. Light composition only: gates access with
 * `registries.viewAny` and mounts the thin Registries adapter, which in turn
 * mounts the generic table (`domain="registries"`). The generic table owns
 * config loading and loading/error/empty states; no business logic or data
 * fetching lives here.
 */
export default function RegistriesPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="registries.viewAny"
      fallback={<p className="text-sm text-muted-foreground">{t('registries.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <RegistriesTable />
      </div>
    </Can>
  )
}
