/**
 * Storico import lead reso dalla tabella generica (dominio `lead-imports`).
 * Estratto in un file affiancato per mantenere `it.ts` entro i limiti
 * dimensionali (vedi `.claude/rules/engineering.md` §6). Le etichette delle
 * colonne sono risolte dal motore tabellare generico via `t(column.label)`.
 */

export const leadImports = {
  title: 'Storico import',
  subtitle: 'I tuoi import di lead passati.',
  forbidden: 'Non hai i permessi per importare i lead.',
  columns: {
    date: 'Data',
    file: 'File',
    records: 'Record',
    imported: 'Importati',
    errors: 'Errori',
    status: 'Stato',
  },
  actions: {
    view: 'Apri',
    delete: 'Elimina',
  },
  deleted: 'Import eliminato con successo.',
  deleteError: "Impossibile eliminare l'import. Riprova.",
  deleteForbidden: 'Non puoi eliminare questo import.',
}
