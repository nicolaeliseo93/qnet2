# Quality Gates

## Purpose

Definire quando una funzionalità può essere considerata completata.

---

# Definition Of Done

Una feature è completata solamente se:

- È specificata prima dello sviluppo (SDD)
- È sviluppata in TDD (test scritti prima del codice)
- È testata con coverage minima dell'85%
- È conforme ai principi SOLID
- È documentata
- È revisionata
- È conforme agli standard

---

# Backend Requirements

- Nessun errore
- Nessun warning critico
- Validazioni implementate
- Permessi verificati

---

# Frontend Requirements

- Nessun errore console
- Nessun warning React critico
- Responsive verificato
- Gestione errori implementata

---

# Testing Requirements

Lo sviluppo segue **TDD**: i test vengono scritti prima del codice di produzione (Red → Green → Refactor).

Devono essere coperti:

- Happy Path
- Error Path
- Edge Cases

La **code coverage deve essere SEMPRE minimo 85%** (backend e frontend). Una modifica che porta la coverage sotto l'85% non supera il quality gate e non può essere mergiata. Vedi `standards/coding-standards.md` → Testing → Coverage.

---

# Review Requirements

Ogni modifica deve essere verificata dal Reviewer Agent.

---

# Security Requirements

Quando la modifica tocca autenticazione, autorizzazioni, dati sensibili o segreti, devono essere verificati:

- Autorizzazione lato server su ogni endpoint
- Input validati e sanitizzati
- Nessun secret nel repository
- Nessun dato sensibile nei log

In questi casi è richiesta la verifica del Security Agent.

---

# Technical Debt

Se viene introdotto debito tecnico:

- Deve essere documentato
- Deve essere tracciabile
- Deve essere motivato
