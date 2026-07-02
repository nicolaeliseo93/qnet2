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
