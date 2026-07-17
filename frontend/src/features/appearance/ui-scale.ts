/**
 * Per-user UI scale: an Excel-like 0..100 slider the user drags to make the
 * whole app (layout, text and data-table grids) larger or smaller. The slider
 * value maps linearly onto a safe, readable font-size band so the extremes stay
 * usable — 0 never blanks the UI.
 *
 *   slider   0 -> 80%   (smallest, still readable)
 *   slider  40 -> 100%  (normal size, the default)
 *   slider 100 -> 130%  (largest)
 *
 * The percentage drives the root `<html>` font-size (every Tailwind rem-based
 * size follows), and the derived factor rescales the AG Grid theme, whose sizes
 * are absolute pixels and would not otherwise track the root font-size.
 */

/** Slider bounds exposed to the user. */
export const UI_SCALE_MIN = 0
export const UI_SCALE_MAX = 100
export const UI_SCALE_STEP = 1

/** Slider value that maps to 100% (normal size). Matches the backend default. */
export const UI_SCALE_DEFAULT = 40

/** Font-size band the 0..100 slider maps onto, as whole percentages. */
export const SCALE_PERCENT_MIN = 80
export const SCALE_PERCENT_MAX = 130

/** Clamp any input to the valid, integer slider range. */
export function clampScale(scale: number): number {
  if (!Number.isFinite(scale)) {
    return UI_SCALE_DEFAULT
  }
  return Math.min(UI_SCALE_MAX, Math.max(UI_SCALE_MIN, Math.round(scale)))
}

/**
 * Map a slider value (0..100) to its font-size percentage (80..130). Linear:
 * percent = SCALE_PERCENT_MIN + slider * (range / 100), so 40 -> 100 exactly.
 */
export function scaleToPercent(scale: number): number {
  const clamped = clampScale(scale)
  const range = SCALE_PERCENT_MAX - SCALE_PERCENT_MIN
  return SCALE_PERCENT_MIN + (clamped * range) / UI_SCALE_MAX
}

/** The same mapping as a multiplier (0.8..1.3) for pixel-based subsystems. */
export function scaleToFactor(scale: number): number {
  return scaleToPercent(scale) / 100
}
