/**
 * Module statistics (spec 0026). `statsPanel` holds the strings of the generic
 * panel; `moduleStats` holds the widget labels of each module, merged into its
 * own namespace in `en.ts` so that a backend label resolves as
 * `{domainCamel}.stats.{key}` (contract D-4).
 */

export const statsPanel = {
  toggle: 'Statistics',
  show: 'Show statistics',
  hide: 'Hide statistics',
  regionLabel: 'Module statistics',
  empty: 'No statistics are available for this module.',
  noData: 'No data',
  loadError: 'Unable to load the statistics. Please try again.',
}

export const moduleStats = {
  projects: {
    total: 'Projects',
    campaigns: 'Campaigns',
    leads: 'Leads',
    conversionRate: 'Conversion rate',
    conversionRateSubtitle_one: '{{count}} converted',
    conversionRateSubtitle_other: '{{count}} converted',
    totalBudget: 'Total budget',
    byStatus: 'By status',
    trend: 'New projects per month',
  },
  registries: {
    total: 'Registries',
    suppliers: 'Suppliers',
    suppliersSubtitle_one: '{{count}} supplier',
    suppliersSubtitle_other: '{{count}} suppliers',
    qualifiedSuppliers: 'Qualified suppliers',
    qualifiedSuppliersSubtitle_one: '{{count}} qualified supplier',
    qualifiedSuppliersSubtitle_other: '{{count}} qualified suppliers',
    byAgreementStatus: 'By agreement status',
    bySizeClass: 'By size class',
    trend: 'New registries per month',
  },
  referents: {
    total: 'Referents',
    internal: 'Internal',
    external: 'External',
    byType: 'By referent type',
    trend: 'New referents per month',
  },
  companies: {
    total: 'Companies',
    withVatNumber: 'With VAT number',
    withVatNumberSubtitle_one: '{{count}} with VAT number',
    withVatNumberSubtitle_other: '{{count}} with VAT number',
    trend: 'New companies per month',
  },
  operationalSites: {
    total: 'Operational sites',
    byRegion: 'By region',
    trend: 'New sites per month',
  },
  companySites: {
    total: 'Company sites',
    defaultSites: 'Default sites',
    byCompany: 'By company',
  },
  products: {
    total: 'Products',
    averagePrice: 'Average price',
    averageMargin: 'Average margin',
    byType: 'By product type',
    byCategory: 'By category',
  },
  campaigns: {
    total: 'Campaigns',
    linkedToProject: 'Linked to a project',
    totalBudget: 'Total budget',
    generatedLeads: 'Generated leads',
    byProjectStatus: 'By project status',
    trend: 'New campaigns per month',
  },
  leads: {
    total: 'Leads',
    converted: 'Converted',
    conversionRate: 'Conversion rate',
    conversionRateSubtitle_one: '{{count}} converted',
    conversionRateSubtitle_other: '{{count}} converted',
    bySource: 'By source',
    byOperator: 'By operator',
    trend: 'New leads per month',
  },
  businessFunctions: {
    total: 'Business functions',
    businessUnits: 'Business units',
    businessServices: 'Business services',
    byUsers: 'Users per function',
  },
  productCategories: {
    total: 'Categories',
    rootCategories: 'Root categories',
    byProducts: 'Products per category',
  },
  users: {
    total: 'Users',
    active: 'Active',
    inactive: 'Deactivated',
    byRole: 'By role',
    byBusinessFunction: 'By business function',
    trend: 'Hires per month',
  },
}
