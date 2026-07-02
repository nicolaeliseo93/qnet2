import { useState } from 'react'
import { Link, useSearchParams } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { AuthCard } from '@/features/auth/auth-card'
import { ResetPasswordForm } from '@/features/auth/reset-password-form'

export default function ResetPasswordPage() {
  const { t } = useTranslation()
  const [searchParams] = useSearchParams()
  const [done, setDone] = useState(false)

  const token = searchParams.get('token')
  const email = searchParams.get('email')

  return (
    <AuthCard title={t('auth.resetPasswordTitle')} description={t('auth.resetPasswordSubtitle')}>
      {!token || !email ? (
        <p className="text-sm font-medium text-destructive" role="alert">
          {t('auth.resetLinkInvalid')}
        </p>
      ) : done ? (
        <p className="text-sm text-muted-foreground" role="status">
          {t('auth.passwordResetSuccess')}
        </p>
      ) : (
        <ResetPasswordForm token={token} email={email} onSuccess={() => setDone(true)} />
      )}
      <div className="mt-4 text-center text-sm">
        <Link to="/login" className="text-muted-foreground underline-offset-4 hover:underline">
          {t('auth.backToSignIn')}
        </Link>
      </div>
    </AuthCard>
  )
}
