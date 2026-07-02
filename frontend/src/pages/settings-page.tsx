import { useTranslation } from 'react-i18next'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { ProfileForm } from '@/features/auth/profile-form'
import { PasswordForm } from '@/features/auth/password-form'
import { AvatarForm } from '@/features/auth/avatar-form'

export default function SettingsPage() {
  const { t } = useTranslation()

  return (
    <div className="flex flex-1 flex-col gap-6">
      <div className="flex flex-col gap-1">
        <h1 className="text-2xl font-semibold tracking-tight">{t('settings.title')}</h1>
        <p className="text-sm text-muted-foreground">{t('settings.subtitle')}</p>
      </div>

      <div className="grid gap-6 lg:max-w-2xl">
        <Card>
          <CardHeader>
            <CardTitle>{t('settings.avatarTitle')}</CardTitle>
            <CardDescription>{t('settings.avatarSubtitle')}</CardDescription>
          </CardHeader>
          <CardContent>
            <AvatarForm />
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>{t('settings.profileTitle')}</CardTitle>
            <CardDescription>{t('settings.profileSubtitle')}</CardDescription>
          </CardHeader>
          <CardContent>
            <ProfileForm />
          </CardContent>
        </Card>

        <Card>
          <CardHeader>
            <CardTitle>{t('settings.passwordTitle')}</CardTitle>
            <CardDescription>{t('settings.passwordSubtitle')}</CardDescription>
          </CardHeader>
          <CardContent>
            <PasswordForm />
          </CardContent>
        </Card>
      </div>
    </div>
  )
}
