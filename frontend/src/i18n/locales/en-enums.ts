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
  gender: {
    male: 'Male',
    female: 'Female',
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
  // User employment profile (spec 0015).
  relationship_type: {
    employee: 'Employee',
    self_employed: 'Self-employed',
    other: 'Other',
  },
  qualification_type: {
    employee_level_5: 'Employee Level 5',
    administrative: 'Administrative',
    coordinator: 'Coordinator',
    iso_consultant: 'ISO Consultant',
    teacher_cococo: 'Co.Co.Co. Teacher',
    teacher_vat: 'VAT Teacher',
    trainee_cost: 'Trainee Cost',
    hourly_cost_me: 'Hourly Cost M.E.',
  },
  // Referent contact ambit (spec 0016).
  referent_contact_scope: {
    internal: 'Internal',
    external: 'External',
  },
  // Product classification (spec 0017).
  product_type: {
    SERVICE: 'Service',
  },
  // Registry convention status (spec 0020).
  agreement_status: {
    negotiating: 'Negotiating',
    rejected: 'Rejected',
    agreed: 'Agreed',
  },
  // Registry size class (spec 0020).
  size_class: {
    micro: 'Micro',
    small: 'Small',
    medium: 'Medium',
    large: 'Large',
  },
}
