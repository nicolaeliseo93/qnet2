import { Link, Navigate, useLocation, useNavigate } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { AuthCard } from '@/features/auth/auth-card'
import { LoginForm } from '@/features/auth/login-form'
import { useAuth } from '@/features/auth/use-auth'

interface LocationState {
  from?: { pathname: string }
}

export default function LoginPage() {
  const { t } = useTranslation()
  const { isAuthenticated } = useAuth()
  const navigate = useNavigate()
  const location = useLocation()

  const redirectTo = (location.state as LocationState | null)?.from?.pathname ?? '/dashboard'

  if (isAuthenticated) {
    return <Navigate to={redirectTo} replace />
  }

  return (
    <AuthCard title={t('auth.signInTitle')} description={t('auth.signInSubtitle')}>
      <LoginForm onSuccess={() => navigate(redirectTo, { replace: true })} />
      <div className="mt-4 text-center text-sm">
        <Link to="/forgot-password" className="text-muted-foreground underline-offset-4 hover:underline">
          {t('auth.forgotPasswordLink')}
        </Link>
      </div>
    </AuthCard>
  )
}
