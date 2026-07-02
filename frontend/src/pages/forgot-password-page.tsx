import { useState } from 'react'
import { Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { AuthCard } from '@/features/auth/auth-card'
import { ForgotPasswordForm } from '@/features/auth/forgot-password-form'

export default function ForgotPasswordPage() {
  const { t } = useTranslation()
  const [submitted, setSubmitted] = useState(false)

  return (
    <AuthCard title={t('auth.forgotPasswordTitle')} description={t('auth.forgotPasswordSubtitle')}>
      {submitted ? (
        <p className="text-sm text-muted-foreground" role="status">
          {t('auth.resetLinkSent')}
        </p>
      ) : (
        <ForgotPasswordForm onSuccess={() => setSubmitted(true)} />
      )}
      <div className="mt-4 text-center text-sm">
        <Link to="/login" className="text-muted-foreground underline-offset-4 hover:underline">
          {t('auth.backToSignIn')}
        </Link>
      </div>
    </AuthCard>
  )
}
