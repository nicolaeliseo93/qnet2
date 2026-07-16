import { cn } from '@/lib/utils'

/**
 * Motion-safe staggered entrance for top-level `FormSection`s: fade + slide
 * with a per-index delay. The delay classes are literal (never interpolated)
 * so Tailwind's scanner can generate them; `fill-mode-backwards` keeps a
 * section hidden while its delay runs. Indexes beyond the ladder reuse the
 * last delay. Lives outside `form-section.tsx` so that file keeps
 * component-only exports (react-refresh).
 */
const SECTION_REVEAL_BASE =
  'motion-safe:animate-in motion-safe:fade-in-0 motion-safe:slide-in-from-bottom-1 motion-safe:duration-300 motion-safe:fill-mode-backwards'

const SECTION_REVEAL_DELAYS = [
  'motion-safe:delay-0',
  'motion-safe:delay-50',
  'motion-safe:delay-100',
  'motion-safe:delay-150',
  'motion-safe:delay-200',
  'motion-safe:delay-250',
] as const

export function sectionRevealClassName(index: number): string {
  return cn(
    SECTION_REVEAL_BASE,
    SECTION_REVEAL_DELAYS[Math.min(index, SECTION_REVEAL_DELAYS.length - 1)],
  )
}
