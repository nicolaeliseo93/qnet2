import { Eye, History, MoreHorizontal, Pencil, Trash, type LucideIcon } from 'lucide-react'

/** Action icon name → Lucide component. Provided by domains for custom icons. */
export type ActionIconMap = Record<string, LucideIcon>

/**
 * Default action-icon map shared by every domain. Domain-agnostic: covers the
 * common CRUD icons plus the `activity` row-action icon (spec 0034, shared by
 * every module that enables the aggregated activity log). Domains that
 * advertise other new icon names inject them via the table props (merged over
 * these defaults) instead of editing this file.
 */
export const defaultActionIconMap: ActionIconMap = {
  eye: Eye,
  pencil: Pencil,
  trash: Trash,
  history: History,
}

/** Neutral fallback used when an icon name is not mapped, so the menu never breaks. */
export const fallbackActionIcon: LucideIcon = MoreHorizontal

/**
 * Resolves an icon name to a Lucide component, consulting the (optional)
 * domain-supplied overrides before the shared defaults and finally a neutral
 * fallback. Overrides win, so domains can also re-map a default name.
 */
export function resolveActionIcon(
  name: string,
  overrides?: ActionIconMap,
): LucideIcon {
  return overrides?.[name] ?? defaultActionIconMap[name] ?? fallbackActionIcon
}
