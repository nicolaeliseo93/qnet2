import { useTranslation } from 'react-i18next'
import type { TFunction } from 'i18next'
import { UserAvatar } from '@/components/user-avatar'
import { formatDateTime } from '@/features/table/cell-renderers'
import type {
  BusinessFunctionDetail,
  BusinessFunctionMember,
} from '@/features/business-functions/types'

interface BusinessFunctionDetailViewProps {
  businessFunction: BusinessFunctionDetail
}

/**
 * Read-only detail of a single business function. Purely presentational: the
 * caller (the table's "view" sheet, owned elsewhere) fetches the fresh detail
 * and passes it down — this component owns no data-fetching state, mirroring
 * the field layout of `UserDetailView` for visual consistency.
 */
export function BusinessFunctionDetailView({ businessFunction }: BusinessFunctionDetailViewProps) {
  const { t } = useTranslation()
  const createdAt = formatDateTime(businessFunction.created_at)

  return (
    <dl className="flex flex-col gap-4 overflow-y-auto p-4 text-sm">
      <Field label={t('businessFunctions.detail.name')}>{businessFunction.name}</Field>
      <Field label={t('businessFunctions.detail.type')}>
        {typeLabel(t, businessFunction.type)}
      </Field>
      <Field label={t('businessFunctions.detail.manager')}>
        <MemberField member={businessFunction.manager} />
      </Field>
      <Field label={t('businessFunctions.detail.users')}>
        {businessFunction.users.length > 0 ? (
          <div className="flex flex-col gap-2">
            {businessFunction.users.map((user) => (
              <MemberField key={user.id} member={user} />
            ))}
          </div>
        ) : (
          <EmptyValue />
        )}
      </Field>
      <Field label={t('businessFunctions.detail.created_at')}>
        {createdAt || <EmptyValue />}
      </Field>
    </dl>
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

/** An avatar next to the member's name, or an em dash when there is no member. */
function MemberField({ member }: { member: BusinessFunctionMember | null }) {
  if (!member) {
    return <EmptyValue />
  }
  return (
    <div className="flex items-center gap-2">
      <UserAvatar name={member.name} src={member.avatar_url} className="size-7" />
      <span>{member.name}</span>
    </div>
  )
}

/** Em-dash placeholder for an empty field value. */
function EmptyValue() {
  return <span className="text-muted-foreground">—</span>
}

function Field({ label, children }: { label: string; children: React.ReactNode }) {
  return (
    <div className="flex flex-col gap-1">
      <dt className="font-medium text-muted-foreground">{label}</dt>
      <dd>{children}</dd>
    </div>
  )
}
