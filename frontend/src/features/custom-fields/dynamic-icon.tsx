import type { LucideProps } from 'lucide-react'
import { ICON_CATALOG } from '@/features/custom-fields/icon-catalog'

interface DynamicIconProps extends Omit<LucideProps, 'ref'> {
  /** Canonical kebab-case lucide name stored on the definition/option. */
  name: string | null | undefined
}

/**
 * Renders a lucide glyph resolved by its stored `name` against the curated
 * {@link ICON_CATALOG}. Returns `null` for a missing/unknown name so callers can
 * render it unconditionally (`<DynamicIcon name={descriptor.icon} />`) without
 * guarding first. Decorative by default (`aria-hidden`); the surrounding label
 * carries the accessible text.
 */
export function DynamicIcon({ name, ...props }: DynamicIconProps) {
  const Icon = name ? ICON_CATALOG[name] : undefined
  if (!Icon) {
    return null
  }
  return <Icon aria-hidden="true" {...props} />
}
