/**
 * Stringhe del dominio Settori EA (spec 0018), in un file separato per i
 * limiti di dimensione (vedi `.claude/rules/engineering.md` §6). Rispecchia
 * la forma di `productCategories`/`referentTypes`.
 */
export const eaSectors = {
  title: 'Settori EA',
  subtitle: 'Sfoglia, filtra e gestisci i settori EA.',
  forbidden: 'Non hai i permessi per visualizzare i settori EA.',
  columns: {
    name: 'Nome',
    parent: 'Padre',
    created_at: 'Creato il',
  },
  detail: {
    title: 'Dettagli settore',
    subtitle: 'Vista di sola lettura del settore selezionato.',
    loadError: 'Impossibile caricare il settore. Riprova.',
  },
  form: {
    newEaSector: 'Nuovo settore',
    createTitle: 'Crea settore',
    createSubtitle: 'Aggiungi un nuovo settore EA.',
    editTitle: 'Modifica settore',
    editSubtitle: 'Aggiorna il settore selezionato.',
    name: 'Nome',
    nameRequired: 'Il nome è obbligatorio.',
    nameMax: 'Il nome deve avere al massimo 191 caratteri.',
    parent: 'Settore padre',
    parentPlaceholder: 'Seleziona un settore padre…',
    parentSearch: 'Cerca settori…',
    parentEmpty: 'Nessun settore trovato.',
    parentNoMatch: 'Nessun risultato.',
    parentError: 'Impossibile caricare i settori.',
    noParent: 'Nessun padre (settore radice)',
    tags: 'Tag',
    tagsPlaceholder: 'Seleziona i tag…',
    tagsSearch: 'Cerca tag…',
    tagsEmpty: 'Nessun tag trovato.',
    tagsError: 'Impossibile caricare i tag.',
    tagsRemove: 'Rimuovi tag',
    save: 'Salva',
    saving: 'Salvataggio…',
    cancel: 'Annulla',
    created: 'Settore creato con successo.',
    updated: 'Settore aggiornato con successo.',
    deleted: 'Settore eliminato con successo.',
    genericError: 'Qualcosa è andato storto. Riprova.',
    deleteError: 'Impossibile eliminare il settore. Riprova.',
    deleteForbidden: 'Non puoi eliminare questo settore.',
    deleteInUse: 'Questo settore ha sotto-settori e non può essere eliminato.',
    sections: {
      identity: {
        title: 'Dettagli',
        description: 'Nome e padre del settore.',
      },
    },
  },
}
