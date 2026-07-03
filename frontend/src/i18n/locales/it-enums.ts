/**
 * Localized labels for backend domain enums. Keyed by the snake_case enum key
 * (config/config.php → form_enums) then by the enum value. The frontend owns
 * these labels; the backend only supplies which values/colors/icons exist.
 *
 * Extracted from `it.ts` to keep that file within the engineering size limits
 * (see `.claude/rules/engineering.md` §6). Public API of `it.ts` is unchanged.
 */
export const enums = {
  locale: {
    en: 'Inglese',
    it: 'Italiano',
  },
  personal_data_type: {
    individual: 'Persona fisica',
    company: 'Azienda',
  },
  personal_title: {
    mr: 'Sig.',
    mrs: 'Sig.ra',
    ms: 'Sig.na',
    dr: 'Dott.',
    prof: 'Prof.',
  },
  contact_type: {
    phone: 'Telefono',
    mobile: 'Cellulare',
    fax: 'Fax',
    email: 'Email',
    pec: 'PEC',
    website: 'Sito web',
  },
  notification_level: {
    info: 'Info',
    success: 'Successo',
    warning: 'Avviso',
    error: 'Errore',
  },
}
