/**
 * Localized labels for backend domain enums. Keyed by the snake_case enum key
 * (config/config.php → form_enums) then by the enum value. The frontend owns
 * these labels; the backend only supplies which values/colors/icons exist.
 *
 * Extracted from `en.ts` to keep that file within the engineering size limits
 * (see `.claude/rules/engineering.md` §6). Public API of `en.ts` is unchanged.
 */
export const enums = {
  locale: {
    en: 'English',
    it: 'Italiano',
  },
  personal_data_type: {
    individual: 'Individual',
    company: 'Company',
  },
  personal_title: {
    mr: 'Mr',
    mrs: 'Mrs',
    ms: 'Ms',
    dr: 'Dr',
    prof: 'Prof',
  },
  contact_type: {
    phone: 'Phone',
    mobile: 'Mobile',
    fax: 'Fax',
    email: 'Email',
    pec: 'Pec',
    website: 'Website',
  },
  notification_level: {
    info: 'Info',
    success: 'Success',
    warning: 'Warning',
    error: 'Error',
  },
}
