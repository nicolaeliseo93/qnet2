import i18n from '@/i18n'

/**
 * Resolves an enum `value` to its localized label, reading from the frontend
 * i18n resources (`enums.<enumKey>.<value>`). The frontend owns enum labels; the
 * backend config is only consulted for which values/colors/icons exist, not for
 * their copy. This non-hook variant exists for module-level renderers (e.g. AG
 * Grid cells) that cannot call hooks; for hook contexts prefer `useEnumOptions`.
 * Falls back to the raw value when no translation is registered, so the caller
 * always has something to show.
 */
export function enumLabelOf(enumKey: string, value: string): string {
  return i18n.t(`enums.${enumKey}.${value}`, { defaultValue: value })
}
