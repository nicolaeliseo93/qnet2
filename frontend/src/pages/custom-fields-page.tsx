import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { CustomFieldsTable } from '@/features/custom-fields/custom-fields-table'

/**
 * Custom fields admin page (spec 0021 AC-025). Light composition only: gates
 * access with `custom-fields.viewAny` and mounts the thin admin adapter,
 * which in turn mounts the generic table (`domain="custom-fields"`). No
 * business logic or data fetching lives here (mirrors `AttributesPage`).
 */
export default function CustomFieldsPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="custom-fields.viewAny"
      fallback={<p className="text-sm text-muted-foreground">{t('customFields.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <CustomFieldsTable />
      </div>
    </Can>
  )
}
