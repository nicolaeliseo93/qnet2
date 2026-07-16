import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { ResourcePermissionsProvider } from '@/features/authorization/permissions'
import { fetchCampaignNextCode } from '@/features/campaigns/api'
import { useCampaignFormMeta } from '@/features/campaigns/use-campaign-form-meta'
import { CampaignFormBody } from '@/features/campaigns/campaign-form-body'
import type { CampaignDetail, CampaignFormMode } from '@/features/campaigns/types'

interface CampaignFormProps {
  mode: CampaignFormMode
  /** Called after a successful create/update so the caller can navigate to the detail page. */
  onSuccess: (campaign: CampaignDetail) => void
  /** Called when the user cancels the form. */
  onCancel: () => void
}

/**
 * Reusable RHF + Zod form used for both creating and editing a campaign.
 * Metadata-driven (spec 0004): resolves the resource's `ResourcePermissions`
 * before rendering — edit mode from the loaded instance detail, create mode
 * from `GET /meta/campaigns` — then hands off to `CampaignFormBody`.
 */
export function CampaignForm(props: CampaignFormProps) {
  const { t } = useTranslation()
  const meta = useCampaignFormMeta(props.mode)

  // Create-only: fetch the next sequential code to auto-fill the (required,
  // editable) `code` field. Kept uncached (staleTime/gcTime 0) so each new
  // form gets a fresh suggestion; an error degrades gracefully to an empty
  // field the user fills manually.
  const isCreate = props.mode.type === 'create'
  const nextCode = useQuery({
    queryKey: ['campaigns', 'next-code'],
    queryFn: fetchCampaignNextCode,
    enabled: isCreate,
    staleTime: 0,
    gcTime: 0,
  })

  if (meta.status === 'loading' || (isCreate && nextCode.isLoading)) {
    return (
      <div className="flex flex-col gap-4 p-4" aria-hidden="true">
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
        <Skeleton className="h-9 w-full" />
      </div>
    )
  }

  if (meta.status === 'error') {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive" role="alert">
          {t('authorization.loadError')}
        </p>
        <Button variant="outline" size="sm" onClick={meta.retry}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  return (
    <ResourcePermissionsProvider permissions={meta.permissions}>
      <CampaignFormBody {...props} initialCode={isCreate ? (nextCode.data ?? '') : undefined} />
    </ResourcePermissionsProvider>
  )
}
