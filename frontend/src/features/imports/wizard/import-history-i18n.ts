import i18n from '@/i18n'

/**
 * Registers the leads table's import/history toolbar menu labels (`menu.*`) as
 * a side-effect merge into the shared `importWizard` i18next namespace already
 * registered by `features/imports/wizard/i18n.ts`. `deep=true` +
 * `overwrite=true` keep this safe regardless of import order relative to that
 * module or any sibling lane's own i18n side-effect file.
 *
 * The former `history.*` table keys lived here too; they are gone now that the
 * history renders through the generic table (`features/imports/lead-imports-table`,
 * i18n namespace `leadImports`).
 */
const menuEn = {
  menu: {
    import: 'Import leads',
    history: 'Import history',
  },
}

const menuIt = {
  menu: {
    import: 'Importa lead',
    history: 'Storico import',
  },
}

i18n.addResourceBundle('en', 'importWizard', menuEn, true, true)
i18n.addResourceBundle('it', 'importWizard', menuIt, true, true)
