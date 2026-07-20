/**
 * Dominio Stati opportunità (spec 0043). Estratto in un file affiancato per
 * mantenere `it.ts` entro i limiti dimensionali (vedi
 * `.claude/rules/engineering.md` §6); rispecchia `lead-statuses` (spec 0029 +
 * 0039) 1:1, con il messaggio del delete-guard adattato alle Opportunità
 * (BR-4). Gli stati di sistema sono "Nuova"/"Chiusa con successo"/"Persa" e un
 * enum `group` fisso a 3 valori (open/pending/closed), oltre allo sheet di
 * riordino drag & drop per le righe personalizzate.
 */

export const opportunityStatuses = {
  title: 'Stati opportunità',
  subtitle: 'Sfoglia, filtra e gestisci gli stati usati dalle opportunità.',
  forbidden: 'Non hai i permessi per visualizzare gli stati opportunità.',
  columns: {
    name: 'Nome',
    color: 'Colore',
    sort_order: 'Ordine',
    group: 'Gruppo',
    created_at: 'Creato il',
  },
  advancedFilters: {
    name: 'Nome',
    sortOrderRange: 'Ordine',
    createdRange: 'Creato il',
  },
  detail: {
    title: 'Dettaglio stato opportunità',
    subtitle: 'Visualizzazione in sola lettura dello stato selezionato.',
    loadError: 'Impossibile caricare lo stato opportunità. Riprova.',
    color: 'Colore',
    sort_order: 'Ordine',
    group: 'Gruppo',
    created_at: 'Creato il',
  },
  form: {
    newOpportunityStatus: 'Nuovo stato',
    createTitle: 'Crea stato opportunità',
    createSubtitle: 'Aggiungi un nuovo stato per le opportunità.',
    editTitle: 'Modifica stato opportunità',
    editSubtitle: 'Aggiorna lo stato opportunità selezionato.',
    name: 'Nome',
    color: 'Colore',
    group: {
      label: 'Gruppo',
      open: 'Aperto',
      pending: 'In pending',
      closed: 'Chiuso',
    },
    save: 'Salva',
    saving: 'Salvataggio…',
    cancel: 'Annulla',
    created: 'Stato opportunità creato con successo.',
    updated: 'Stato opportunità aggiornato con successo.',
    deleted: 'Stato opportunità eliminato con successo.',
    nameRequired: 'Il nome è obbligatorio.',
    nameMax: 'Il nome può contenere al massimo 191 caratteri.',
    colorMax: 'Il colore può contenere al massimo 32 caratteri.',
    genericError: 'Si è verificato un errore. Riprova.',
    deleteError: 'Impossibile eliminare lo stato opportunità. Riprova.',
    deleteForbidden: 'Non puoi eliminare questo stato opportunità.',
    deleteInUseFallback: 'Questo stato opportunità è usato da un\'opportunità e non può essere eliminato.',
    sections: {
      identity: {
        title: 'Dettagli',
        description: 'Nome, colore e gruppo dello stato.',
      },
    },
    hints: {
      systemStatusGroup: 'Gli stati di sistema hanno un gruppo fisso e non possono essere riclassificati.',
    },
  },
  reorder: {
    openButton: 'Riordina',
    title: 'Riordina stati',
    subtitle: 'Trascina gli stati personalizzati per riordinarli. "Nuova" resta prima e "Persa" resta ultima.',
    dragHandleLabel: 'Trascina per riordinare',
    loadError: 'Impossibile caricare gli stati. Riprova.',
    saved: 'Ordine aggiornato con successo.',
    forbidden: 'Non puoi riordinare questi stati.',
    genericError: "Impossibile aggiornare l'ordine. Riprova.",
  },
}
