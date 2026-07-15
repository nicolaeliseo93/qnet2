import i18n from '@/i18n'

/**
 * Registers this lane's (F5, spec 0033 AC-018) `menu.*`/`history.*` keys as a
 * side-effect merge into the shared `importWizard` i18next namespace already
 * registered by `features/imports/wizard/i18n.ts` (F1). `deep=true` +
 * `overwrite=true` on `addResourceBundle` make this safe regardless of import
 * order relative to that module or any sibling lane's own i18n side-effect
 * file (e.g. `import-wizard-i18n-summary`) — each merge only adds its own
 * branch, none overwrites the whole namespace.
 */
const historyEn = {
  menu: {
    import: 'Import leads',
    history: 'Import history',
  },
  history: {
    title: 'Import history',
    subtitle: 'Your past lead import runs.',
    empty: 'No import runs yet.',
    loadError: 'Unable to load the import history. Please try again.',
    columns: {
      date: 'Date',
      file: 'File',
      records: 'Records',
      imported: 'Imported',
      errors: 'Errors',
      status: 'Status',
    },
    viewRun: 'View',
    pagination: 'Page {{page}} of {{totalPages}}',
    previous: 'Previous',
    next: 'Next',
  },
}

const historyIt = {
  menu: {
    import: 'Importa lead',
    history: 'Storico import',
  },
  history: {
    title: 'Storico import',
    subtitle: 'I tuoi import di lead passati.',
    empty: 'Nessun import ancora eseguito.',
    loadError: "Impossibile caricare lo storico degli import. Riprova.",
    columns: {
      date: 'Data',
      file: 'File',
      records: 'Record',
      imported: 'Importati',
      errors: 'Errori',
      status: 'Stato',
    },
    viewRun: 'Apri',
    pagination: 'Pagina {{page}} di {{totalPages}}',
    previous: 'Precedente',
    next: 'Successiva',
  },
}

i18n.addResourceBundle('en', 'importWizard', historyEn, true, true)
i18n.addResourceBundle('it', 'importWizard', historyIt, true, true)
