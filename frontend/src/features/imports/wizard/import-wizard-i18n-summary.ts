import i18n from '@/i18n'

/**
 * Registers lane F3's own `importWizard` keys (summary totals/selected
 * values/mapped columns/extra fields/warnings/confirm action, and the
 * processing/completed/failed progress view) as a side effect of importing
 * this module. `wizard/i18n.ts` (owned by F1) already registers the base
 * `importWizard` namespace (including `summary.title`); this module
 * deep-merges on top of it (`deep=true`) instead of editing
 * `i18n/locales/{en,it}-import-wizard.ts` directly, so F1/F2/F3 never touch
 * the same file. `overwrite=true` lets a re-import (e.g. hot reload) refresh
 * the keys instead of being a no-op; ES module caching still makes a single
 * import idempotent per module graph.
 */
i18n.addResourceBundle(
  'en',
  'importWizard',
  {
    summary: {
      loading: 'Loading the summary…',
      loadError: 'Unable to load the summary. Please try again.',
      confirm: 'Confirm and import',
      confirming: 'Confirming…',
      configTitle: 'Selected values',
      configEmpty: 'No global values configured.',
      mappedFieldsTitle: 'Mapped columns',
      extraFieldsTitle: 'Extra fields',
      warningsTitle: 'Warnings',
      totals: {
        total: 'Total rows',
        valid: 'Valid',
        warning: 'Warnings',
        error: 'Errors',
        duplicate: 'Duplicates',
        modified: 'Modified',
      },
    },
    progress: {
      processing: 'Import in progress…',
      completed: '{{imported}} leads imported, {{errors}} errors.',
      failed: 'The import failed. Please check the file and try again.',
      notified: 'A notification was sent once the import completed.',
    },
  },
  true,
  true,
)

i18n.addResourceBundle(
  'it',
  'importWizard',
  {
    summary: {
      loading: 'Caricamento del riepilogo…',
      loadError: 'Impossibile caricare il riepilogo. Riprova.',
      confirm: 'Conferma e importa',
      confirming: 'Conferma in corso…',
      configTitle: 'Valori selezionati',
      configEmpty: 'Nessun valore globale configurato.',
      mappedFieldsTitle: 'Colonne mappate',
      extraFieldsTitle: 'Campi extra',
      warningsTitle: 'Avvisi',
      totals: {
        total: 'Righe totali',
        valid: 'Valide',
        warning: 'Avvisi',
        error: 'Errori',
        duplicate: 'Duplicate',
        modified: 'Modificate',
      },
    },
    progress: {
      processing: 'Importazione in corso…',
      completed: '{{imported}} lead importati, {{errors}} errori.',
      failed: "L'importazione è fallita. Controlla il file e riprova.",
      notified: "È stata inviata una notifica al termine dell'importazione.",
    },
  },
  true,
  true,
)
