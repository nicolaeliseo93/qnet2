/**
 * Dominio Fonti (spec 0018). Estratto in un file affiancato per mantenere
 * `it.ts` entro i limiti dimensionali (vedi `.claude/rules/engineering.md`
 * §6); rispecchia `referentTypes` (spec 0016) 1:1.
 */

export const sources = {
  title: 'Fonti',
  subtitle: 'Sfoglia, filtra e gestisci le fonti della tua organizzazione.',
  forbidden: 'Non hai i permessi per visualizzare le fonti.',
  columns: {
    name: 'Nome',
    created_at: 'Creato il',
  },
  detail: {
    title: 'Dettaglio fonte',
    subtitle: 'Visualizzazione in sola lettura della fonte selezionata.',
    loadError: 'Impossibile caricare la fonte. Riprova.',
    created_at: 'Creato il',
  },
  form: {
    newSource: 'Nuova fonte',
    createTitle: 'Crea fonte',
    createSubtitle: 'Aggiungi una nuova fonte alla tua organizzazione.',
    editTitle: 'Modifica fonte',
    editSubtitle: 'Aggiorna la fonte selezionata.',
    name: 'Nome',
    save: 'Salva',
    saving: 'Salvataggio…',
    cancel: 'Annulla',
    created: 'Fonte creata con successo.',
    updated: 'Fonte aggiornata con successo.',
    deleted: 'Fonte eliminata con successo.',
    nameRequired: 'Il nome è obbligatorio.',
    nameMax: 'Il nome può contenere al massimo 191 caratteri.',
    genericError: 'Si è verificato un errore. Riprova.',
    deleteError: 'Impossibile eliminare la fonte. Riprova.',
    deleteForbidden: 'Non puoi eliminare questa fonte.',
    sections: {
      identity: {
        title: 'Dettagli',
        description: 'Nome della fonte.',
      },
    },
  },
}
