/**
 * Localized strings for the external data migrations module (spec 0013):
 * the super-admin-only preview (fase 1) page and the import (fase 2)
 * confirm/progress/summary dialog.
 *
 * Extracted from `it.ts` to keep that file within the engineering size limits
 * (see `.claude/rules/engineering.md` §6). Mirrors `en-migrations.ts` 1:1.
 */
export const migrations = {
  nav: {
    label: 'Migrazioni',
  },
  page: {
    import: 'Importa',
    sourceLabel: 'Sorgente',
    sourcePlaceholder: 'Seleziona una sorgente…',
    sourcesLoadError: 'Impossibile caricare le sorgenti di migrazione. Riprova.',
    sourcesEmpty: 'Nessuna sorgente di migrazione registrata.',
    pageIndicator: 'Pagina {{page}}',
    previous: 'Precedente',
    next: 'Successiva',
  },
  preview: {
    loadError: "Impossibile caricare l'anteprima. Riprova.",
    empty: 'Nessun record trovato per questa sorgente.',
  },
  import: {
    title: 'Importa {{source}}',
    subtitle:
      "L'import crea i record in background; ripeterlo è sicuro (idempotente).",
    confirmDescription:
      "Questa operazione crea i record in qnet dalla sorgente esterna, in background. I record già importati vengono saltati: ripetere l'import è sicuro.",
    start: 'Avvia import',
    starting: 'Avvio…',
    close: 'Chiudi',
    summary: {
      total: 'Righe totali',
      created: 'Creati',
      skipped: 'Saltati',
      failed: 'Falliti',
    },
    reportTitle: 'Avvisi ed errori',
    reportLevel: {
      warning: 'Avviso',
      error: 'Errore',
    },
  },
  status: {
    pending: 'In attesa',
    processing: 'Importazione in corso',
    completed: 'Completato',
    failed: 'Fallito',
  },
  errors: {
    forbidden: 'Non hai i permessi per accedere alle migrazioni.',
    notFound: 'Questa sorgente di migrazione non esiste.',
    validation: 'Parametri di paginazione non validi.',
    externalUnavailable: 'Il sistema esterno non è al momento disponibile. Riprova.',
    generic: 'Si è verificato un errore. Riprova.',
    jobFailed: "L'import è fallito. Riprova.",
  },
}
