import { useTranslation } from 'react-i18next'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { useEnumOptions } from '@/features/config/use-config'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchUser } from '@/features/users/api'

interface UserDetailProps {
  userId: number
}

/**
 * Read-only detail of a single user, fetched fresh from the (re-authorized)
 * detail endpoint. Handles loading and error states; rendered inside a Sheet.
 */
export function UserDetailView({ userId }: UserDetailProps) {
  const { t, i18n } = useTranslation()
  const localeOptions = useEnumOptions('locale')
  const {
    data: user,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(['users', 'detail', userId], () => fetchUser(userId))

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive">{t('users.detail.loadError')}</p>
        <Button variant="outline" size="sm" onClick={() => refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (isLoading || !user) {
    return (
      <div className="flex flex-col gap-4 p-4">
        <Skeleton className="h-6 w-1/2" />
        <Skeleton className="h-6 w-2/3" />
        <Skeleton className="h-6 w-1/3" />
      </div>
    )
  }

  const createdAt = formatDateTime(user.created_at, i18n.language)

  return (
    <dl className="flex flex-col gap-4 overflow-y-auto p-4 text-sm">
      <Field label={t('users.form.name')}>{user.name}</Field>
      <Field label={t('users.form.email')}>{user.email}</Field>
      <Field label={t('users.form.locale')}>
        {localeOptions.find((option) => option.value === user.locale)?.label ??
          user.locale}
      </Field>
      <Field label={t('users.form.roles')}>
        {user.roles.length > 0 ? (
          <div className="flex flex-wrap gap-1">
            {user.roles.map((role) => (
              <Badge key={role.id} variant="secondary">
                {role.name}
              </Badge>
            ))}
          </div>
        ) : (
          <span className="text-muted-foreground">—</span>
        )}
      </Field>
      <Field label={t('users.columns.created_at')}>
        {createdAt || <span className="text-muted-foreground">—</span>}
      </Field>

      {user.employment && (
        <div className="flex flex-col gap-4 border-t pt-4">
          <h3 className="text-sm font-semibold text-foreground">
            {t('users.detail.employment.title')}
          </h3>
          <Field label={t('users.detail.employment.isManager')}>
            {user.employment.is_manager ? t('common.yes') : t('common.no')}
          </Field>
          <Field label={t('users.detail.employment.businessFunction')}>
            {user.employment.business_function?.label ?? (
              <span className="text-muted-foreground">—</span>
            )}
          </Field>
          <Field label={t('users.detail.employment.reportsTo')}>
            {user.employment.reports_to?.label ?? (
              <span className="text-muted-foreground">—</span>
            )}
          </Field>
          <Field label={t('users.detail.employment.jobDescription')}>
            {user.employment.job_description || <span className="text-muted-foreground">—</span>}
          </Field>
          <Field label={t('users.detail.employment.relationshipType')}>
            {user.employment.relationship_type ? (
              t(`enums.relationship_type.${user.employment.relationship_type}`)
            ) : (
              <span className="text-muted-foreground">—</span>
            )}
          </Field>
          <Field label={t('users.detail.employment.company')}>
            {user.employment.company?.label ?? <span className="text-muted-foreground">—</span>}
          </Field>
          <Field label={t('users.detail.employment.operationalSite')}>
            {user.employment.operational_site?.label ?? (
              <span className="text-muted-foreground">—</span>
            )}
          </Field>
          <Field label={t('users.detail.employment.qualificationType')}>
            {user.employment.qualification_type ? (
              t(`enums.qualification_type.${user.employment.qualification_type}`)
            ) : (
              <span className="text-muted-foreground">—</span>
            )}
          </Field>
          <Field label={t('users.detail.employment.hiredAt')}>
            {formatDate(user.employment.hired_at, i18n.language) || (
              <span className="text-muted-foreground">—</span>
            )}
          </Field>
          <Field label={t('users.detail.employment.terminatedAt')}>
            {formatDate(user.employment.terminated_at, i18n.language) || (
              <span className="text-muted-foreground">—</span>
            )}
          </Field>
          <Field label={t('users.detail.employment.standardDailyMinutes')}>
            {formatMinutes(user.employment.standard_daily_minutes) ?? (
              <span className="text-muted-foreground">—</span>
            )}
          </Field>
          <Field label={t('users.detail.employment.breakDailyMinutes')}>
            {formatMinutes(user.employment.break_daily_minutes) ?? (
              <span className="text-muted-foreground">—</span>
            )}
          </Field>
        </div>
      )}
    </dl>
  )
}

function Field({
  label,
  children,
}: {
  label: string
  children: React.ReactNode
}) {
  return (
    <div className="flex flex-col gap-1">
      <dt className="font-medium text-muted-foreground">{label}</dt>
      <dd>{children}</dd>
    </div>
  )
}

function formatDateTime(value: string | null, language: string): string {
  if (!value) {
    return ''
  }
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) {
    return ''
  }
  return new Intl.DateTimeFormat(language, {
    dateStyle: 'medium',
    timeStyle: 'short',
  }).format(date)
}

/** Formats a `Y-m-d` employment date, no time part (spec 0015). */
function formatDate(value: string | null, language: string): string {
  if (!value) {
    return ''
  }
  const date = new Date(value)
  if (Number.isNaN(date.getTime())) {
    return ''
  }
  return new Intl.DateTimeFormat(language, { dateStyle: 'medium' }).format(date)
}

/** Formats a total-minutes duration as `H:MM`, or `null` when unset (spec 0015). */
function formatMinutes(value: number | null): string | null {
  if (value === null) {
    return null
  }
  const hours = Math.floor(value / 60)
  const minutes = value % 60
  return `${hours}:${String(minutes).padStart(2, '0')}`
}
