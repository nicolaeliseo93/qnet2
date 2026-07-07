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
  gender: {
    male: 'Maschio',
    female: 'Femmina',
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
  // Profilo di impiego utente (spec 0015).
  relationship_type: {
    employee: 'Dipendente',
    self_employed: 'Partita IVA',
    other: 'Altro',
  },
  qualification_type: {
    employee_level_5: 'Impiegato 5° Liv.',
    administrative: 'Amministrativo',
    coordinator: 'Coordinatore',
    iso_consultant: 'Consulente ISO',
    teacher_cococo: 'Docenti Co.Co.Co.',
    teacher_vat: 'Docenti P.IVA',
    trainee_cost: 'Costo Tirocinante',
    hourly_cost_me: 'Costo orario M.E.',
  },
  // Ambito di contatto del referente (spec 0016).
  referent_contact_scope: {
    internal: 'Interno',
    external: 'Esterno',
  },
  // Tipo dato dell'attributo dinamico (spec 0017).
  attribute_type: {
    STRING: 'Testo',
    INTEGER: 'Numero intero',
    DECIMAL: 'Numero decimale',
    BOOLEAN: 'Sì/No',
    ENUM: 'Elenco di opzioni',
  },
  // Classificazione del prodotto (spec 0017).
  product_type: {
    SERVICE: 'Servizio',
  },
}
