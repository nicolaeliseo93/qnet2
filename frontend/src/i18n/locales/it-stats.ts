/**
 * Statistiche di modulo (spec 0026). Mirrors `en-stats.ts`.
 */

export const statsPanel = {
  toggle: 'Statistiche',
  show: 'Mostra statistiche',
  hide: 'Nascondi statistiche',
  regionLabel: 'Statistiche del modulo',
  empty: 'Non ci sono statistiche disponibili per questo modulo.',
  noData: 'Nessun dato',
  loadError: 'Impossibile caricare le statistiche. Riprova.',
}

export const moduleStats = {
  projects: {
    total: 'Progetti',
    campaigns: 'Campagne',
    leads: 'Lead',
    conversionRate: 'Tasso di conversione',
    conversionRateSubtitle_one: '{{count}} convertito',
    conversionRateSubtitle_other: '{{count}} convertiti',
    totalBudget: 'Budget totale',
    byStatus: 'Per stato',
    trend: 'Nuovi progetti per mese',
  },
  registries: {
    total: 'Anagrafiche',
    suppliers: 'Fornitori',
    suppliersSubtitle_one: '{{count}} fornitore',
    suppliersSubtitle_other: '{{count}} fornitori',
    qualifiedSuppliers: 'Fornitori qualificati',
    qualifiedSuppliersSubtitle_one: '{{count}} fornitore qualificato',
    qualifiedSuppliersSubtitle_other: '{{count}} fornitori qualificati',
    byAgreementStatus: 'Per stato accordo',
    bySizeClass: 'Per classe dimensionale',
    trend: 'Nuove anagrafiche per mese',
  },
  referents: {
    total: 'Referenti',
    internal: 'Interni',
    external: 'Esterni',
    byType: 'Per tipo referente',
    trend: 'Nuovi referenti per mese',
  },
  companies: {
    total: 'Aziende',
    withVatNumber: 'Con partita IVA',
    withVatNumberSubtitle_one: '{{count}} con partita IVA',
    withVatNumberSubtitle_other: '{{count}} con partita IVA',
    trend: 'Nuove aziende per mese',
  },
  operationalSites: {
    total: 'Sedi operative',
    byRegion: 'Per regione',
    trend: 'Nuove sedi per mese',
  },
  companySites: {
    total: 'Sedi aziendali',
    defaultSites: 'Sedi predefinite',
    byCompany: 'Per azienda',
  },
  products: {
    total: 'Prodotti',
    averagePrice: 'Prezzo medio',
    averageMargin: 'Margine medio',
    byType: 'Per tipo prodotto',
    byCategory: 'Per categoria',
  },
  campaigns: {
    total: 'Campagne',
    linkedToProject: 'Collegate a un progetto',
    totalBudget: 'Budget totale',
    generatedLeads: 'Lead generati',
    byProjectStatus: 'Per stato progetto',
    trend: 'Nuove campagne per mese',
  },
  leads: {
    total: 'Lead',
    converted: 'Convertiti',
    conversionRate: 'Tasso di conversione',
    conversionRateSubtitle_one: '{{count}} convertito',
    conversionRateSubtitle_other: '{{count}} convertiti',
    bySource: 'Per fonte',
    byOperator: 'Per operatore',
    trend: 'Nuovi lead per mese',
  },
  businessFunctions: {
    total: 'Funzioni aziendali',
    businessUnits: 'Business unit',
    businessServices: 'Business service',
    byUsers: 'Utenti per funzione',
  },
  productCategories: {
    total: 'Categorie',
    rootCategories: 'Categorie radice',
    byProducts: 'Prodotti per categoria',
  },
  users: {
    total: 'Utenti',
    active: 'Attivi',
    inactive: 'Disattivati',
    byRole: 'Per ruolo',
    byBusinessFunction: 'Per funzione aziendale',
    trend: 'Assunzioni per mese',
  },
}
