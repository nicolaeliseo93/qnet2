/**
 * Dominio Stati lead (spec 0029). Estratto in un file affiancato per
 * mantenere `it.ts` entro i limiti dimensionali (vedi
 * `.claude/rules/engineering.md` §6); rispecchia `pipeline-statuses` (spec
 * 0023) 1:1, con il messaggio del delete-guard adattato ai Lead (BR-3). La
 * spec 0039 aggiunge gli stati di sistema ("Nuovo"/"Chiuso con
 * successo"/"Scartato") e un enum `group` fisso a 3 valori
 * (open/pending/closed), oltre allo sheet di riordino drag & drop per le
 * righe personalizzate.
 */

export const leadStatuses = {
  title: 'Stati lead',
  subtitle: 'Sfoglia, filtra e gestisci gli stati usati dai lead.',
  forbidden: 'Non hai i permessi per visualizzare gli stati lead.',
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
    title: 'Dettaglio stato lead',
    subtitle: 'Visualizzazione in sola lettura dello stato selezionato.',
    loadError: 'Impossibile caricare lo stato lead. Riprova.',
    color: 'Colore',
    sort_order: 'Ordine',
    group: 'Gruppo',
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
    group: {
      label: 'Gruppo',
      open: 'Aperto',
      pending: 'In pending',
      closed: 'Chiuso',
    },
    save: 'Salva',
    saving: 'Salvataggio…',
    cancel: 'Annulla',
    created: 'Stato lead creato con successo.',
    updated: 'Stato lead aggiornato con successo.',
    deleted: 'Stato lead eliminato con successo.',
    nameRequired: 'Il nome è obbligatorio.',
    nameMax: 'Il nome può contenere al massimo 191 caratteri.',
    colorMax: 'Il colore può contenere al massimo 32 caratteri.',
    genericError: 'Si è verificato un errore. Riprova.',
    deleteError: 'Impossibile eliminare lo stato lead. Riprova.',
    deleteForbidden: 'Non puoi eliminare questo stato lead.',
    deleteInUseFallback: 'Questo stato lead è usato da un lead e non può essere eliminato.',
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
    subtitle: 'Trascina gli stati personalizzati per riordinarli. "Nuovo" resta primo e "Chiuso" resta ultimo.',
    dragHandleLabel: 'Trascina per riordinare',
    loadError: 'Impossibile caricare gli stati. Riprova.',
    saved: 'Ordine aggiornato con successo.',
    forbidden: 'Non puoi riordinare questi stati.',
    genericError: "Impossibile aggiornare l'ordine. Riprova.",
  },
}
