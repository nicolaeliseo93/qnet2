# Launch Checklist

> Checklist di rilascio. Owner: DevOps Agent.
> Allineata a `agents/devops.md`, `standards/quality-gates.md` e alla fase "Release Readiness" di `standards/orchestration.md`.

---

## Release Info

- Feature / Change: ...
- Versione: ...
- Data prevista: AAAA-MM-GG
- Responsabile rilascio: ...

---

## Quality Gates (Definition of Done)

- [ ] Sviluppata
- [ ] Testata
- [ ] Documentata
- [ ] Revisionata (Reviewer Agent)
- [ ] Conforme agli standard

---

## Backend

- [ ] Nessun errore
- [ ] Nessun warning critico
- [ ] Validazioni implementate
- [ ] Permessi verificati

## Frontend

- [ ] Nessun errore console
- [ ] Nessun warning React critico
- [ ] Responsive verificato
- [ ] Gestione errori implementata

---

## Testing

- [ ] Happy path
- [ ] Error path
- [ ] Edge case
- [ ] Regressione verificata (QA Agent)

---

## Security

- [ ] Autenticazione e autorizzazioni verificate
- [ ] Input validati lato server
- [ ] Nessun secret nel repository
- [ ] Nessun dato sensibile nei log
- [ ] Dipendenze prive di vulnerabilità note

---

## Legal / Privacy (se applicabile)

- [ ] Trattamento dati valutato
- [ ] Policy/consensi aggiornati se necessari

---

## Deployment

- [ ] Applicazione compilabile
- [ ] Variabili ambiente corrette
- [ ] Migrazioni sicure e reversibili
- [ ] Rollback possibile
- [ ] Backup disponibili
- [ ] Monitoraggio/osservabilità attivi

---

## Rollback Strategy

Procedura di ripristino in caso di problemi.

---

## Sign-off

- [ ] Reviewer
- [ ] QA
- [ ] Security (se applicabile)
- [ ] Legal (se applicabile)
- [ ] DevOps
