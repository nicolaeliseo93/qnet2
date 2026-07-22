/**
 * Dominio Gestione Richieste (spec 0049): vista operativa "Gestione
 * Richieste" sulle Opportunità per i commerciali (D-1, nessuna entità
 * nuova — il record E' un'Opportunità). File satellite per mantenere
 * `it.ts` entro i limiti dimensionali (vedi `.claude/rules/engineering.md`
 * §6).
 */

export const requestManagement = {
  title: 'Gestione Richieste',
  subtitle: "Lavora le opportunità: verifica i contatti, completa i campi dinamici e avanza lo stato di lavorazione.",
  forbidden: 'Non hai il permesso di visualizzare Gestione Richieste.',
  columns: {
    productCategory: 'Categoria prodotto',
    operator: 'Operatore (GA2)',
    workflowStatus: 'Stato di lavorazione',
    firstName: 'Nome',
    lastName: 'Cognome',
    taxCode: 'Codice fiscale',
    phone: 'Telefono',
    updatedAt: 'Aggiornato il',
    nextCallbackAt: 'Prossimo richiamo',
  },
  advancedFilters: {
    registry: 'Anagrafica',
    referent: 'Referente',
    workflowStatus: 'Stato di lavorazione',
    opportunityStatus: 'Stato commerciale',
    expectedCloseRange: 'Data chiusura prevista',
    nextCallbackRange: 'Prossimo richiamo',
  },
  detail: {
    title: 'Dettagli richiesta',
    subtitle: "Lavora l'opportunità selezionata: contatti, campi dinamici e stato di lavorazione.",
  },
  form: {
    notApplicable: 'Gestione Richieste non ha un form di creazione/modifica: lavora il record dal suo pannello di dettaglio.',
  },
  workPanel: {
    loadError: 'Impossibile caricare il record.',
    saving: 'Salvataggio…',
    save: 'Salva',
    saved: 'Dati di lavorazione salvati.',
    genericError: 'Si è verificato un errore. Riprova.',
    dynamicFields: {
      title: 'Informazioni aggiuntive',
      empty: 'Nessun campo aggiuntivo per questa opportunità.',
    },
    workflowStatus: {
      title: 'Stato di lavorazione',
      sectionDescription: 'Fai avanzare lo stato di lavorazione della richiesta.',
      label: 'Stato di lavorazione',
      placeholder: 'Seleziona uno stato',
      noteLabel: 'Nota',
      notePlaceholder: 'Spiega il motivo di questo cambio…',
    },
    callback: {
      title: 'Prossimo richiamo',
      description: 'Pianifica la prossima chiamata di follow-up con il cliente.',
      label: 'Data e ora del richiamo',
      placeholder: 'Seleziona data e ora',
    },
    client: {
      title: 'Anagrafica',
      description: 'Dati identificativi, contatti e indirizzo del cliente.',
      identityGroup: 'Dati identificativi',
      contactsGroup: 'Contatti',
      addressGroup: 'Indirizzo',
      addressEmpty: 'Non inserito',
    },
    header: {
      unsavedChanges: 'Modifiche non salvate',
      salesStatus: 'Commerciale',
      workingStatus: 'Lavorazione',
      nextCallback: 'Prossimo richiamo',
    },
    summary: {
      title: 'Riepilogo richiesta',
      description: 'Contesto commerciale in sola lettura.',
      registry: 'Cliente',
      referent: 'Referente',
      commercial: 'Commerciale',
      expectedCloseDate: 'Chiusura prevista',
      estimatedValue: 'Valore stimato',
      successProbability: 'Probabilità di successo',
      productLines: 'Linee di prodotto',
    },
    collaboration: {
      notesTab: 'Note',
      documentsTab: 'Documenti',
      activityTab: 'Storico',
    },
    validation: {
      enumInvalid: 'Seleziona un valore valido.',
      required: 'Questo campo è obbligatorio.',
      noteRequired: 'È obbligatoria una nota per passare a questo stato.',
    },
  },
}
