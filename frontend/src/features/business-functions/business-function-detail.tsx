import { useTranslation } from 'react-i18next'
import type { TFunction } from 'i18next'
import { Network, UserCog, Users } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import {
  DetailEmpty,
  DetailHero,
  DetailMeta,
  DetailMonogram,
  DetailPanel,
  DetailPerson,
  DetailSection,
} from '@/components/detail/detail-panel'
import { formatDateTime } from '@/features/table/cell-renderers'
import type { BusinessFunctionDetail } from '@/features/business-functions/types'

interface BusinessFunctionDetailViewProps {
  businessFunction: BusinessFunctionDetail
}

/**
 * Read-only detail of a single business function. Purely presentational: the
 * caller (the table's "view" sheet) fetches the fresh detail and passes it
 * down. Composed from the shared detail kit for a consistent CRM look.
 */
export function BusinessFunctionDetailView({ businessFunction }: BusinessFunctionDetailViewProps) {
  const { t } = useTranslation()
  const createdAt = formatDateTime(businessFunction.created_at)

  return (
    <DetailPanel>
      <DetailHero
        media={<DetailMonogram name={businessFunction.name} icon={<Network />} />}
        title={businessFunction.name}
        badges={<Badge variant="secondary">{typeLabel(t, businessFunction.type)}</Badge>}
      />

      <DetailSection title={t('businessFunctions.detail.manager')} icon={<UserCog />}>
        {businessFunction.manager ? (
          <DetailPerson
            name={businessFunction.manager.name}
            avatarUrl={businessFunction.manager.avatar_url}
          />
        ) : (
          <DetailEmpty />
        )}
      </DetailSection>

      <DetailSection
        title={t('businessFunctions.detail.users')}
        icon={<Users />}
        action={
          businessFunction.users.length > 0 ? (
            <Badge variant="secondary">{businessFunction.users.length}</Badge>
          ) : null
        }
      >
        {businessFunction.users.length > 0 ? (
          <div className="flex flex-col gap-3">
            {businessFunction.users.map((user) => (
              <DetailPerson key={user.id} name={user.name} avatarUrl={user.avatar_url} />
            ))}
          </div>
        ) : (
          <DetailEmpty />
        )}
      </DetailSection>

      {createdAt ? (
        <DetailMeta label={t('businessFunctions.detail.created_at')}>{createdAt}</DetailMeta>
      ) : null}
    </DetailPanel>
  )
}

/** Maps the mutually-exclusive `type` to its localized label. */
function typeLabel(t: TFunction, type: BusinessFunctionDetail['type']): string {
  if (type === 'business_unit') {
    return t('businessFunctions.form.type.businessUnit')
  }
  if (type === 'business_service') {
    return t('businessFunctions.form.type.businessService')
  }
  return t('businessFunctions.form.type.none')
}
