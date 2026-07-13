/**
 * Dominio Lead (spec 0024). File satellite per mantenere `it.ts` entro i
 * limiti dimensionali (vedi `.claude/rules/engineering.md` §6).
 */

export const leads = {
  title: 'Lead',
  subtitle: "Sfoglia, filtra e gestisci i lead generati dalle campagne dell'organizzazione.",
  forbidden: 'Non hai il permesso di visualizzare i lead.',
  columns: {
    referent: 'Contatto',
    campaign: 'Campagna',
    operational_site: 'Sede',
    source: 'Fonte',
    operator: 'Operatore',
    notes: 'Note',
    created_at: 'Creato il',
  },
  detail: {
    loadError: 'Impossibile caricare il lead. Riprova.',
    unknownReferent: 'Contatto sconosciuto',
  },
  form: {
    newLead: 'Nuovo lead',
    createTitle: 'Crea lead',
    createSubtitle: "Aggiungi un nuovo lead all'organizzazione.",
    editTitle: 'Modifica lead',
    editSubtitle: 'Aggiorna il lead selezionato.',
    sections: {
      contact: {
        title: 'Contatto e campagna',
        description: 'Il contatto e la campagna che lo hanno generato, con sede, fonte e operatore.',
      },
      details: {
        title: 'Dettagli',
        description: 'Sede, fonte e operatore associati al lead.',
      },
      notes: {
        title: 'Note',
        description: 'Annotazioni libere sul lead.',
      },
    },
    referent: 'Contatto',
    referentSearch: 'Cerca contatti…',
    referentRequired: 'Il contatto è obbligatorio.',
    campaign: 'Campagna',
    campaignSearch: 'Cerca campagne…',
    campaignRequired: 'La campagna è obbligatoria.',
    operationalSite: 'Sede',
    operationalSiteSearch: 'Cerca sedi…',
    source: 'Fonte',
    sourceSearch: 'Cerca fonti…',
    operator: 'Operatore',
    operatorSearch: 'Cerca operatori…',
    notes: 'Note',
    notesMax: 'Le note devono avere al massimo 5000 caratteri.',
    selectPlaceholder: 'Seleziona…',
    selectEmpty: 'Nessun risultato trovato.',
    selectError: 'Impossibile caricare le opzioni.',
    save: 'Salva',
    saving: 'Salvataggio…',
    cancel: 'Annulla',
    created: 'Lead creato con successo.',
    updated: 'Lead aggiornato con successo.',
    deleted: 'Lead eliminato con successo.',
    genericError: 'Qualcosa è andato storto. Riprova.',
    deleteError: 'Impossibile eliminare il lead. Riprova.',
    deleteForbidden: 'Non puoi eliminare questo lead.',
  },
}
