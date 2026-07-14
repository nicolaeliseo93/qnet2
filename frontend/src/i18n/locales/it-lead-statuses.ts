/**
 * Dominio Stati lead (spec 0029). Estratto in un file affiancato per
 * mantenere `it.ts` entro i limiti dimensionali (vedi
 * `.claude/rules/engineering.md` §6); rispecchia `project-statuses` (spec
 * 0023) 1:1, con il messaggio del delete-guard adattato ai Lead (BR-3).
 */

export const leadStatuses = {
  title: 'Stati lead',
  subtitle: 'Sfoglia, filtra e gestisci gli stati usati dai lead.',
  forbidden: 'Non hai i permessi per visualizzare gli stati lead.',
  columns: {
    name: 'Nome',
    color: 'Colore',
    sort_order: 'Ordine',
    created_at: 'Creato il',
  },
  detail: {
    title: 'Dettaglio stato lead',
    subtitle: 'Visualizzazione in sola lettura dello stato selezionato.',
    loadError: 'Impossibile caricare lo stato lead. Riprova.',
    color: 'Colore',
    sort_order: 'Ordine',
    created_at: 'Creato il',
  },
  form: {
    newLeadStatus: 'Nuovo stato',
    createTitle: 'Crea stato lead',
    createSubtitle: 'Aggiungi un nuovo stato per i lead.',
    editTitle: 'Modifica stato lead',
    editSubtitle: 'Aggiorna lo stato lead selezionato.',
    name: 'Nome',
    color: 'Colore',
    sortOrder: 'Ordine',
    save: 'Salva',
    saving: 'Salvataggio…',
    cancel: 'Annulla',
    created: 'Stato lead creato con successo.',
    updated: 'Stato lead aggiornato con successo.',
    deleted: 'Stato lead eliminato con successo.',
    nameRequired: 'Il nome è obbligatorio.',
    nameMax: 'Il nome può contenere al massimo 191 caratteri.',
    colorMax: 'Il colore può contenere al massimo 32 caratteri.',
    sortOrderInvalid: "L'ordine deve essere un numero intero.",
    sortOrderMin: "L'ordine deve essere zero o maggiore.",
    genericError: 'Si è verificato un errore. Riprova.',
    deleteError: 'Impossibile eliminare lo stato lead. Riprova.',
    deleteForbidden: 'Non puoi eliminare questo stato lead.',
    deleteInUseFallback: 'Questo stato lead è usato da un lead e non può essere eliminato.',
    sections: {
      identity: {
        title: 'Dettagli',
        description: 'Nome, colore e ordine dello stato.',
      },
    },
  },
}
