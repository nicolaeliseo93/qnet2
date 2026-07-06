/**
 * Public application bootstrap config served by GET /api/config (ADR 0008).
 *
 * The backend exposes domain enum options (for selects/badges) as a fixed,
 * server-side allowlist with labels already localized via the request locale.
 * The frontend consumes them instead of hardcoding enum values/labels, so a new
 * case added on the backend appears in the UI with no frontend change.
 */

/** A single selectable enum case with its presentation metadata. */
export interface EnumOption {
  value: string
  label: string
  color: string | null
  icon: string | null
  is_default: boolean
  hidden_on_form: boolean
}

/**
 * The bootstrap payload (envelope `data`). `enums` is keyed by the snake_case
 * enum key declared in the backend allowlist (config/config.php → form_enums),
 * e.g. `personal_data_type`, `contact_type`.
 */
export interface AppConfig {
  enums: Record<string, EnumOption[]>
}
