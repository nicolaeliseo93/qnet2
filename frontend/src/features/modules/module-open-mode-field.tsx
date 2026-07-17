import { useTranslation } from 'react-i18next'
import { Label } from '@/components/ui/label'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { MODULE_REGISTRY } from '@/features/modules/module-registry'
import {
  OPEN_MODE_MODAL,
  OPEN_MODE_PAGE,
  PREFERENCE_MODE_CUSTOM,
  PREFERENCE_MODE_MODAL,
  PREFERENCE_MODE_PAGE,
  type ModuleOpenPreferenceMode,
  type ModuleOpenPreferences,
  type OpenMode,
} from '@/features/modules/types'

interface ModuleOpenModeFieldProps {
  value: ModuleOpenPreferences
  onChange: (next: ModuleOpenPreferences) => void
}

/**
 * Controlled settings control for the per-user module open-mode preference
 * (spec 0042): a 3-state mode selector (modal / single page / custom) plus,
 * in custom mode, one open-mode selector per registered module. Pure UI over
 * the `ModuleOpenPreferences` value — the parent form owns the state and puts
 * it in the `/auth/me` payload. Switching to a global mode keeps `overrides`
 * intact so no per-module choice is silently lost (AC-016).
 */
export function ModuleOpenModeField({ value, onChange }: ModuleOpenModeFieldProps) {
  const { t } = useTranslation()

  const handleModeChange = (mode: ModuleOpenPreferenceMode) => {
    onChange({ ...value, mode })
  }

  const handleOverrideChange = (domain: string, openMode: OpenMode) => {
    onChange({ ...value, overrides: { ...value.overrides, [domain]: openMode } })
  }

  return (
    <section className="flex flex-col gap-3" aria-labelledby="module-open-mode-title">
      <div className="flex flex-col gap-1">
        <h3 id="module-open-mode-title" className="text-sm font-semibold">
          {t('settings.moduleOpenMode.title')}
        </h3>
        <p className="text-xs text-muted-foreground">{t('settings.moduleOpenMode.subtitle')}</p>
      </div>

      <div className="grid gap-2">
        <Label htmlFor="module-open-mode-select" className="text-xs">
          {t('settings.moduleOpenMode.modeLabel')}
        </Label>
        <Select
          value={value.mode}
          onValueChange={(next) => handleModeChange(next as ModuleOpenPreferenceMode)}
        >
          <SelectTrigger id="module-open-mode-select" className="w-full">
            <SelectValue />
          </SelectTrigger>
          <SelectContent>
            <SelectItem value={PREFERENCE_MODE_MODAL}>
              {t('settings.moduleOpenMode.modeModal')}
            </SelectItem>
            <SelectItem value={PREFERENCE_MODE_PAGE}>
              {t('settings.moduleOpenMode.modePage')}
            </SelectItem>
            <SelectItem value={PREFERENCE_MODE_CUSTOM}>
              {t('settings.moduleOpenMode.modeCustom')}
            </SelectItem>
          </SelectContent>
        </Select>
      </div>

      {value.mode === PREFERENCE_MODE_CUSTOM && (
        <div className="flex flex-col gap-2 border-t pt-3">
          <p className="text-xs text-muted-foreground">
            {t('settings.moduleOpenMode.customHint')}
          </p>
          <ul className="flex flex-col divide-y">
            {MODULE_REGISTRY.map((entry) => {
              const moduleLabel = t(entry.labelKey)
              const current = value.overrides[entry.domain] ?? entry.defaultMode
              return (
                <li
                  key={entry.domain}
                  className="flex items-center justify-between gap-3 py-1.5"
                >
                  <span className="text-sm">{moduleLabel}</span>
                  <Select
                    value={current}
                    onValueChange={(next) =>
                      handleOverrideChange(entry.domain, next as OpenMode)
                    }
                  >
                    <SelectTrigger
                      className="h-8 w-40 text-xs"
                      aria-label={t('settings.moduleOpenMode.perModuleAria', {
                        module: moduleLabel,
                      })}
                    >
                      <SelectValue />
                    </SelectTrigger>
                    <SelectContent>
                      <SelectItem value={OPEN_MODE_MODAL}>
                        {t('settings.moduleOpenMode.valueModal')}
                      </SelectItem>
                      <SelectItem value={OPEN_MODE_PAGE}>
                        {t('settings.moduleOpenMode.valuePage')}
                      </SelectItem>
                    </SelectContent>
                  </Select>
                </li>
              )
            })}
          </ul>
        </div>
      )}
    </section>
  )
}
