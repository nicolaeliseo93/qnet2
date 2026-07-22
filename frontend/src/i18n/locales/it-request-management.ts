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
    serviceCategory: 'Categoria servizi di riferimento',
    operator: 'Operatore (GA2)',
    workflowStatus: 'Stato di lavorazione',
    firstName: 'Nome',
    lastName: 'Cognome',
    taxCode: 'Codice fiscale',
    phone: 'Telefono',
    updatedAt: 'Aggiornato il',
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
      label: 'Stato di lavorazione',
      placeholder: 'Seleziona uno stato',
    },
    contacts: {
      registryTitle: "Contatti dell'anagrafica",
      referentTitle: 'Contatti del referente',
    },
    context: {
      subtitle: "Contesto dell'opportunità (sola lettura).",
      registry: 'Anagrafica',
      opportunityStatus: 'Stato commerciale',
      expectedCloseDate: 'Data chiusura prevista',
      estimatedValue: 'Valore stimato',
      productLines: 'Righe prodotto',
    },
    validation: {
      enumInvalid: 'Seleziona un valore valido.',
      required: 'Questo campo è obbligatorio.',
    },
  },
}
