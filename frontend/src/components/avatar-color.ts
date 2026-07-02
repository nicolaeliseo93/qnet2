/**
 * Deterministic colors for the initials fallback, in the spirit of Google's
 * avatars: the same name always maps to the same color, so a user looks
 * consistent everywhere (sidebar, tables, forms). Each entry is a soft pastel
 * background paired with a darker, same-hue text color for a calm, readable
 * look (not loud or heavily saturated).
 */
export interface AvatarColor {
  /** Soft pastel background. */
  bg: string
  /** Darker same-hue text color, readable on top of `bg`. */
  fg: string
}

const AVATAR_COLORS: readonly AvatarColor[] = [
  { bg: '#d3e3fd', fg: '#1967d2' }, // blue
  { bg: '#fad2cf', fg: '#c5221f' }, // red
  { bg: '#ceead6', fg: '#137333' }, // green
  { bg: '#feefc3', fg: '#a05a00' }, // amber
  { bg: '#e9d2fd', fg: '#8430ce' }, // purple
  { bg: '#cbf0f8', fg: '#007b83' }, // teal
  { bg: '#fdcfe8', fg: '#b80672' }, // pink
  { bg: '#d7d8fc', fg: '#3f51b5' }, // indigo
  { bg: '#fde0c2', fg: '#b35309' }, // orange
  { bg: '#d4edbc', fg: '#3d6b1f' }, // lime
  { bg: '#e6e0d6', fg: '#5f5749' }, // sand
  { bg: '#d9d2f9', fg: '#6a3ea1' }, // violet
] as const

/** Stable hash so a name always lands on the same color. */
function hashName(name: string): number {
  let hash = 0
  for (let i = 0; i < name.length; i++) {
    hash = (hash << 5) - hash + name.charCodeAt(i)
    hash |= 0 // keep it a 32-bit integer
  }
  return Math.abs(hash)
}

/**
 * Picks a deterministic avatar color pair for a display name. Empty or
 * whitespace-only names fall back to the first palette entry.
 */
export function avatarColor(name: string): AvatarColor {
  const normalized = name.trim().toLowerCase()
  if (normalized.length === 0) return AVATAR_COLORS[0]
  return AVATAR_COLORS[hashName(normalized) % AVATAR_COLORS.length]
}
