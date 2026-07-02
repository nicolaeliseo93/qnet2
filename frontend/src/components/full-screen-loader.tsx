import { Loader2 } from 'lucide-react'
import { useTranslation } from 'react-i18next'

export function FullScreenLoader() {
  const { t } = useTranslation()
  return (
    <div
      className="flex min-h-svh items-center justify-center text-muted-foreground"
      role="status"
      aria-live="polite"
    >
      <Loader2 className="size-6 animate-spin" aria-hidden />
      <span className="sr-only">{t('common.loading')}</span>
    </div>
  )
}
