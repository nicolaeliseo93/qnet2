import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { FullScreenLoader } from '@/components/full-screen-loader'
import { useConfig } from '@/features/config/use-config'

/**
 * Boot gate enforcing the config-first bootstrap (ADR 0009): GET /api/config is
 * the first call and nothing downstream — in particular the AuthProvider's `me`
 * fetch — mounts until the config has loaded.
 *
 * - pending: full-screen splash, children withheld.
 * - error: full-screen message with a Retry button, children withheld.
 * - success: children rendered.
 */
export function ConfigGate({ children }: { children: ReactNode }) {
  const { t } = useTranslation()
  const { isError, isSuccess, refetch } = useConfig()

  if (isError) {
    return (
      <div
        className="flex min-h-svh flex-col items-center justify-center gap-4 p-4 text-center"
        role="alert"
      >
        <p className="text-xl font-semibold tracking-tight">
          {t('config.error.title')}
        </p>
        <p className="max-w-md text-muted-foreground">
          {t('config.error.description')}
        </p>
        <Button onClick={() => void refetch()}>{t('config.error.retry')}</Button>
      </div>
    )
  }

  // Treat any non-success state as "still booting": withhold children until the
  // config is in the cache. Covers the initial pending load and keeps children
  // unmounted across a manual refetch triggered after an error.
  if (!isSuccess) {
    return <FullScreenLoader />
  }

  return <>{children}</>
}
