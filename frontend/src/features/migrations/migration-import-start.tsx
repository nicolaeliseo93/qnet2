import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'

interface MigrationImportStartProps {
  onStart: () => void
  isStarting: boolean
  startError: string | null
}

/**
 * The pre-import confirmation step: explains what the import does (creates
 * records in background, idempotent re-runs) and starts it on confirm. Shown
 * before any `MigrationRun` exists.
 */
export function MigrationImportStart({
  onStart,
  isStarting,
  startError,
}: MigrationImportStartProps) {
  const { t } = useTranslation('migrations')

  return (
    <div className="flex flex-col gap-4">
      <p className="text-sm text-muted-foreground">{t('import.confirmDescription')}</p>

      {startError ? (
        <p className="text-sm text-destructive" role="alert">
          {startError}
        </p>
      ) : null}

      <div className="flex justify-end">
        <Button type="button" onClick={onStart} disabled={isStarting}>
          {isStarting ? t('import.starting') : t('import.start')}
        </Button>
      </div>
    </div>
  )
}
