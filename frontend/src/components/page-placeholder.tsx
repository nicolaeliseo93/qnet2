import { useTranslation } from 'react-i18next'

/**
 * Empty page scaffold. Real screens replace this as features are built.
 */
export function PagePlaceholder({ title }: { title: string }) {
  const { t } = useTranslation()
  return (
    <div className="flex flex-1 flex-col gap-2">
      <h1 className="text-2xl font-semibold tracking-tight">{title}</h1>
      <p className="text-sm text-muted-foreground">{t('common.comingSoon')}</p>
    </div>
  )
}
