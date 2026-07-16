/**
 * Dominio Gruppi di stato (spec 0039). Estratto in un file affiancato per
 * mantenere `it.ts` entro i limiti dimensionali (vedi
 * `.claude/rules/engineering.md` §6); rispecchia `lead-statuses` (spec
 * 0029) 1:1, con il messaggio del delete-guard adattato agli stati (D-6).
 */

export const statusGroups = {
  title: 'Gruppi di stato',
  subtitle: 'Sfoglia, filtra e gestisci i gruppi usati per classificare gli stati.',
  forbidden: 'Non hai i permessi per visualizzare i gruppi di stato.',
  columns: {
    name: 'Nome',
    color: 'Colore',
    sort_order: 'Ordine',
    created_at: 'Creato il',
  },
  advancedFilters: {
    name: 'Nome',
    sortOrderRange: 'Ordine',
    createdRange: 'Creato il',
  },
  detail: {
    title: 'Dettaglio gruppo di stato',
    subtitle: 'Visualizzazione in sola lettura del gruppo selezionato.',
    loadError: 'Impossibile caricare il gruppo di stato. Riprova.',
    color: 'Colore',
    sort_order: 'Ordine',
    created_at: 'Creato il',
  },
  form: {
    newStatusGroup: 'Nuovo gruppo',
    createTitle: 'Crea gruppo di stato',
    createSubtitle: 'Aggiungi un nuovo gruppo per classificare gli stati.',
    editTitle: 'Modifica gruppo di stato',
    editSubtitle: 'Aggiorna il gruppo di stato selezionato.',
    name: 'Nome',
    color: 'Colore',
    sortOrder: 'Ordine',
    save: 'Salva',
    saving: 'Salvataggio…',
    cancel: 'Annulla',
    created: 'Gruppo di stato creato con successo.',
    updated: 'Gruppo di stato aggiornato con successo.',
    deleted: 'Gruppo di stato eliminato con successo.',
    nameRequired: 'Il nome è obbligatorio.',
    nameMax: 'Il nome può contenere al massimo 191 caratteri.',
    colorMax: 'Il colore può contenere al massimo 32 caratteri.',
    sortOrderInvalid: "L'ordine deve essere un numero intero.",
    sortOrderMin: "L'ordine deve essere zero o maggiore.",
    genericError: 'Si è verificato un errore. Riprova.',
    deleteError: 'Impossibile eliminare il gruppo di stato. Riprova.',
    deleteForbidden: 'Non puoi eliminare questo gruppo di stato.',
    deleteInUseFallback: 'Questo gruppo di stato è usato da uno stato e non può essere eliminato.',
    sections: {
      identity: {
        title: 'Dettagli',
        description: 'Nome, colore e ordine del gruppo.',
      },
    },
  },
}
