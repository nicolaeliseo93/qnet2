import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { useQuery } from '@tanstack/react-query'
import { configQueryOptions } from '@/features/config/query-options'
import type { EnumOption } from '@/features/config/types'

/**
 * Loads the public application config (enum options, ...). The data is static
 * presentation metadata, so it never goes stale within a session. Query options
 * (key/fn/staleTime/retry) are shared with the boot prefetch — see
 * `configQueryOptions`.
 */
export function useConfig() {
  return useQuery(configQueryOptions)
}

/**
 * The options for a single domain enum (e.g. `contact_type`), ready to feed a
 * select. The backend supplies which values exist plus their color/icon/default
 * metadata; the human label is owned by the frontend i18n resources
 * (`enums.<key>.<value>`), falling back to the raw value when untranslated.
 * Returns an empty list until the config has loaded, so callers can render an
 * empty select without guarding for undefined.
 */
export function useEnumOptions(key: string): EnumOption[] {
  const { data } = useConfig()
  const { t } = useTranslation()

  return useMemo(
    () =>
      (data?.enums[key] ?? []).map((option) => ({
        ...option,
        label: t(`enums.${key}.${option.value}`, { defaultValue: option.value }),
      })),
    [data, key, t],
  )
}
