import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { BusinessFunctionsTable } from '@/features/business-functions/business-functions-table'

/**
 * Business Functions page. Light composition only: gates access with
 * `business-functions.viewAny` and mounts the thin Business Functions
 * adapter, which in turn mounts the generic table
 * (`domain="business-functions"`). The generic table owns config loading and
 * loading/error/empty states; no business logic or data fetching lives here.
 */
export default function BusinessFunctionsPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="business-functions.viewAny"
      fallback={
        <p className="text-sm text-muted-foreground">
          {t('businessFunctions.forbidden')}
        </p>
      }
    >
      <div className="flex flex-1 flex-col gap-6">
        <BusinessFunctionsTable />
      </div>
    </Can>
  )
}
