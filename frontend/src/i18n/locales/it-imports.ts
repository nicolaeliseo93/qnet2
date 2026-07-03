/**
 * Localized strings for the generic per-table CSV import feature (spec 0012).
 * Mirrors `en-imports.ts` 1:1.
 */
export const imports = {
  /** Etichetta dell'azione in toolbar (apre il dialog di import, gated da `{resource}.import`). */
  action: 'Importa',
  title: 'Importa dati',
  subtitle: 'Carica un file CSV per creare nuovi record dal template predefinito.',
  fields: {
    file: 'File CSV',
  },
  buttons: {
    downloadTemplate: 'Scarica il template',
    upload: 'Carica',
    uploading: 'Caricamento…',
    confirm: 'Conferma',
    confirming: 'Conferma in corso…',
    downloadErrorReport: 'Scarica il report errori',
    close: 'Chiudi',
  },
  status: {
    validating: 'Validazione in corso',
    awaiting_confirmation: 'In attesa di conferma',
    processing: 'Importazione in corso',
    completed: 'Completato',
    failed: 'Fallito',
  },
  summary: {
    total: 'Righe totali',
    valid: 'Righe valide',
    invalid: 'Righe scartate',
    imported: 'Righe importate',
  },
  preview: {
    validSampleTitle: 'Righe valide (campione)',
    invalidSampleTitle: 'Righe scartate',
    rowNumber: 'Riga',
    values: 'Valori',
    reason: 'Motivo',
  },
  errors: {
    fileRequired: 'Seleziona un file CSV.',
    forbidden: 'Non hai i permessi per importare questi dati.',
    validation: 'Il file caricato non è valido. Controlla il formato e riprova.',
    invalidState: 'Questo import non può più essere confermato.',
    generic: 'Si è verificato un errore. Riprova.',
    jobFailed: "L'importazione non è riuscita. Riprova.",
    templateDownloadError: 'Impossibile scaricare il template. Riprova.',
    reportDownloadError: 'Impossibile scaricare il report errori. Riprova.',
  },
}
