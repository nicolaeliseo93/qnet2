/**
 * Dominio Funzioni aziendali. Mirrors `en-business-functions.ts`.
 */
import { moduleStats } from './it-stats'

export const businessFunctions = {
  stats: moduleStats.businessFunctions,
  title: 'Funzioni aziendali',
  subtitle: 'Sfoglia, filtra e gestisci le funzioni aziendali della tua organizzazione.',
  forbidden: 'Non hai i permessi per visualizzare le funzioni aziendali.',
  columns: {
    name: 'Nome',
    is_business_unit: 'Business Unit',
    is_business_service: 'Business Service',
    manager: 'Responsabile',
    users: 'Utenti associati',
    created_at: 'Creato il',
  },
  detail: {
    title: 'Dettaglio funzione aziendale',
    subtitle: 'Visualizzazione in sola lettura della funzione aziendale selezionata.',
    loadError: 'Impossibile caricare la funzione aziendale. Riprova.',
    name: 'Nome',
    type: 'Tipo',
    manager: 'Responsabile',
    users: 'Utenti associati',
    created_at: 'Creato il',
  },
  form: {
    newBusinessFunction: 'Nuova funzione aziendale',
    createTitle: 'Crea funzione aziendale',
    createSubtitle: 'Aggiungi una nuova funzione aziendale alla tua organizzazione.',
    editTitle: 'Modifica funzione aziendale',
    editSubtitle: 'Aggiorna la funzione aziendale selezionata.',
    name: 'Nome',
    manager: 'Responsabile',
    managerPlaceholder: 'Seleziona un responsabile…',
    users: 'Utenti associati',
    usersPlaceholder: 'Seleziona utenti…',
    usersSearch: 'Cerca utenti…',
    usersEmpty: 'Nessun utente trovato.',
    usersError: 'Impossibile caricare gli utenti.',
    usersRemove: 'Rimuovi utente',
    type: {
      label: 'Tipo',
      businessUnit: 'Business Unit',
      businessService: 'Business Service',
      none: 'Nessuno',
    },
    save: 'Salva',
    saving: 'Salvataggio…',
    cancel: 'Annulla',
    created: 'Funzione aziendale creata con successo.',
    updated: 'Funzione aziendale aggiornata con successo.',
    deleted: 'Funzione aziendale eliminata con successo.',
    nameRequired: 'Il nome è obbligatorio.',
    nameMax: 'Il nome può contenere al massimo 191 caratteri.',
    genericError: 'Si è verificato un errore. Riprova.',
    deleteError: 'Impossibile eliminare la funzione aziendale. Riprova.',
    deleteForbidden: 'Non puoi eliminare questa funzione aziendale.',
    sections: {
      identity: {
        title: 'Dettagli',
        description: 'Nome e tipo della funzione aziendale.',
      },
      assignment: {
        title: 'Assegnazione',
        description: 'Responsabile e utenti associati.',
      },
    },
  },
}
