import { useEffect, useMemo, useState, type ReactNode } from 'react'
import { useAuth } from '@/features/auth/use-auth'
import {
  UiScaleContext,
  type UiScaleContextValue,
} from '@/features/appearance/ui-scale-context'
import {
  UI_SCALE_DEFAULT,
  clampScale,
  scaleToFactor,
  scaleToPercent,
} from '@/features/appearance/ui-scale'

/**
 * Owns the applied UI scale and pushes it to the DOM: it sets the root `<html>`
 * font-size (percent) so every rem-based Tailwind size scales, and exposes the
 * derived factor for the AG Grid theme (absolute pixels). The authoritative
 * value is the authenticated user's `ui_scale`; the settings form previews live
 * via `setScale` and persists through PATCH /auth/me, after which the primed
 * cache flows back here and this stays in sync.
 */
export function UiScaleProvider({ children }: { children: ReactNode }) {
  const { user } = useAuth()
  const serverScale = user?.ui_scale
  const [scale, setScale] = useState<number>(() =>
    clampScale(serverScale ?? UI_SCALE_DEFAULT),
  )

  // Re-sync to the server value whenever it actually changes — after login, a
  // refetch, or a save priming the ['auth','me'] cache. Adjusted during render
  // (React's endorsed alternative to a syncing effect): a local drag preview
  // leaves serverScale untouched, so it never fights the preview.
  const [prevServerScale, setPrevServerScale] = useState(serverScale)
  if (serverScale !== prevServerScale) {
    setPrevServerScale(serverScale)
    setScale(clampScale(serverScale ?? UI_SCALE_DEFAULT))
  }

  // Apply the root font-size; the browser reads html font-size percent relative
  // to its own default, so this also honors the user's browser zoom preference.
  useEffect(() => {
    document.documentElement.style.fontSize = `${scaleToPercent(scale)}%`
  }, [scale])

  const value = useMemo<UiScaleContextValue>(
    () => ({ scale, factor: scaleToFactor(scale), setScale }),
    [scale],
  )

  return (
    <UiScaleContext.Provider value={value}>{children}</UiScaleContext.Provider>
  )
}
