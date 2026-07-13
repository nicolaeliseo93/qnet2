import { useTranslation } from 'react-i18next'
import { Can } from '@/features/auth/can'
import { CampaignsTable } from '@/features/campaigns/campaigns-table'

/**
 * Campaigns page. Light composition only: gates access with
 * `campaigns.viewAny` and mounts the thin Campaigns adapter, which in turn
 * mounts the generic table (`domain="campaigns"`). The generic table owns
 * config loading and loading/error/empty states; no business logic or data
 * fetching lives here.
 */
export default function CampaignsPage() {
  const { t } = useTranslation()

  return (
    <Can
      permission="campaigns.viewAny"
      fallback={<p className="text-sm text-muted-foreground">{t('campaigns.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-6">
        <CampaignsTable />
      </div>
    </Can>
  )
}
