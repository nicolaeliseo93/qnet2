import { Link } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'

export default function NotFoundPage() {
  const { t } = useTranslation()
  return (
    <div className="flex min-h-svh flex-col items-center justify-center gap-4 p-4 text-center">
      <p className="text-6xl font-bold tracking-tight">404</p>
      <p className="text-muted-foreground">{t('common.notFound')}</p>
      <Button asChild>
        <Link to="/dashboard">{t('common.backToDashboard')}</Link>
      </Button>
    </div>
  )
}
