import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { RotateCcw } from 'lucide-react'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Slider } from '@/components/ui/slider'
import { updateProfile } from '@/features/auth/api'
import { authKeys } from '@/features/auth/query-keys'
import { useUiScale } from '@/features/appearance/ui-scale-context'
import {
  UI_SCALE_DEFAULT,
  UI_SCALE_MAX,
  UI_SCALE_MIN,
  UI_SCALE_STEP,
  scaleToPercent,
} from '@/features/appearance/ui-scale'

const HEADING_ID = 'ui-scale-heading'

/**
 * Settings section for the per-user UI scale (Excel-like 0..100 slider). Dragging
 * the slider previews the whole app live through the shared UiScaleProvider; Save
 * persists via a partial PATCH /auth/me carrying only `ui_scale` and primes the
 * `['auth','me']` cache so the value survives a reload without a refetch.
 */
export function UiScaleForm() {
  const { t } = useTranslation()
  const { scale, setScale } = useUiScale()
  const queryClient = useQueryClient()
  const [isSaving, setIsSaving] = useState(false)
  const [error, setError] = useState<string | null>(null)

  const persist = async (value: number) => {
    setIsSaving(true)
    setError(null)
    try {
      const updatedUser = await updateProfile({ ui_scale: value })
      queryClient.setQueryData(authKeys.me, updatedUser)
      toast.success(t('settings.uiScale.saved'))
    } catch {
      setError(t('settings.genericError'))
    } finally {
      setIsSaving(false)
    }
  }

  const onSave = () => void persist(scale)

  // Restore the 100% default and apply + persist it in one click.
  const onReset = () => {
    setScale(UI_SCALE_DEFAULT)
    void persist(UI_SCALE_DEFAULT)
  }

  return (
    <div className="flex flex-col gap-4">
      <div className="flex flex-col gap-1">
        <h3 id={HEADING_ID} className="text-base font-semibold">
          {t('settings.uiScale.title')}
        </h3>
        <p className="text-sm text-muted-foreground">
          {t('settings.uiScale.subtitle')}
        </p>
      </div>

      <div className="flex items-center gap-4">
        <Slider
          aria-labelledby={HEADING_ID}
          min={UI_SCALE_MIN}
          max={UI_SCALE_MAX}
          step={UI_SCALE_STEP}
          value={[scale]}
          onValueChange={(values) => setScale(values[0] ?? UI_SCALE_DEFAULT)}
          className="max-w-xs"
        />
        <span
          className="w-14 shrink-0 text-right text-sm font-medium tabular-nums"
          aria-live="polite"
        >
          {Math.round(scaleToPercent(scale))}%
        </span>
      </div>

      {error && (
        <p className="text-sm font-medium text-destructive" role="alert">
          {error}
        </p>
      )}

      <div className="flex flex-wrap items-center gap-2">
        <Button type="button" onClick={onSave} disabled={isSaving}>
          {isSaving ? t('settings.savingProfile') : t('settings.saveProfile')}
        </Button>
        <Button
          type="button"
          variant="outline"
          onClick={onReset}
          disabled={isSaving}
        >
          <RotateCcw aria-hidden="true" />
          {t('settings.uiScale.reset')}
        </Button>
      </div>
    </div>
  )
}
