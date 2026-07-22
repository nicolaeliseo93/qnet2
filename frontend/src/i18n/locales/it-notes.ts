/**
 * Dominio Note (spec 0052): feature agnostica di note collaborative con
 * menzioni, montata da qualunque modulo host tramite `NotesSection`. File
 * satellite per mantenere `it.ts` entro i limiti dimensionali (vedi
 * `.claude/rules/engineering.md` §6).
 */

export const notes = {
  section: {
    title: 'Note',
    description: 'Discuti il record con i colleghi: usa @ per menzionarli.',
    loadError: 'Impossibile caricare le note.',
    empty: 'Nessuna nota. Scrivi la prima per iniziare la discussione.',
  },
  list: {
    loadMore: 'Carica altre',
  },
  item: {
    edited: '(modificato)',
    replyAction: 'Rispondi',
    editAction: 'Modifica nota',
    deleteAction: 'Elimina nota',
    deleteConfirm: "La nota sparirà dall'elenco. Le eventuali risposte restano nascoste insieme ad essa.",
  },
  composer: {
    placeholder: 'Scrivi una nota, usa @ per menzionare un collega…',
    bodyRequired: 'Scrivi qualcosa prima di inviare.',
    bodyTooLong: 'La nota può contenere al massimo {{count}} caratteri.',
    genericError: 'Invio non riuscito. Riprova.',
    send: 'Invia',
    save: 'Salva',
  },
  mentionPicker: {
    label: 'Utenti selezionabili',
    empty: 'Nessun utente corrispondente',
  },
}
