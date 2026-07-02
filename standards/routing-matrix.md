# Routing Matrix

## Purpose

Definire quali agenti devono essere coinvolti in base al tipo di richiesta.

Questa matrice riduce ambiguità e rende il sistema più ripetibile.

---

# Usage Rules

Per ogni richiesta identificare:

- tipo di lavoro
- agenti obbligatori
- agenti opzionali
- ordine di esecuzione
- condizione di stop

Se una richiesta rientra in più categorie, applicare la combinazione più restrittiva.

---

# Matrix

## Product Discovery

- Trigger: nuove idee, MVP, priorità, backlog, validazione feature
- Required Agents: Product Manager
- Optional Agents: Founder, UX Research, Customer Success
- Sequence: Product Manager -> Founder/UX Research se necessario
- Stop Condition: problema utente o scope non chiaro

---

## Strategic Initiative

- Trigger: nuovo prodotto, nuovo mercato, scelta di investimento
- Required Agents: Founder
- Optional Agents: Product Manager, UX Research, Legal
- Sequence: Founder -> Product Manager -> altri se necessario
- Stop Condition: mancanza di domanda, vantaggio o fattibilità

---

## Architecture Change

- Trigger: nuovi moduli, refactor strutturali, nuove dipendenze, cambi API o schema
- Required Agents: Architect
- Optional Agents: Backend, Frontend, DevOps, Reviewer, Security
- Sequence: Architect -> specialisti -> Reviewer -> QA
- Stop Condition: impatto architetturale non valutato

---

## Backend Feature

- Trigger: endpoint, service, model, migration, permessi, logica server-side
- Required Agents: Backend
- Optional Agents: Architect, Reviewer, QA, Security, DevOps, Documentation
- Sequence: Backend -> Reviewer -> QA
- Escalate To Architect If: nuove dipendenze, schema complesso, pattern nuovi, impatto cross-layer
- Stop Condition: API, regole di business o dati non definiti

---

## Frontend Feature

- Trigger: page, component, form, routing, state, UX flow, integrazione API
- Required Agents: Frontend
- Optional Agents: UI Design, UX Research, Architect, Reviewer, QA, Documentation
- Sequence: Frontend -> Reviewer -> QA
- Escalate To Architect If: nuovo pattern, forte coupling o modifica strutturale
- Stop Condition: API non definite o comportamento UX ambiguo

---

## UI Design Review

- Trigger: revisione visuale, coerenza design system, accessibilità, responsive behavior
- Required Agents: UI Design
- Optional Agents: Frontend, UX Research, Reviewer, QA
- Sequence: UI Design -> Frontend -> Reviewer/QA se necessario
- Stop Condition: obiettivo della schermata o vincoli UI non chiari

---

## UX Research Review

- Trigger: dubbi su utilità feature, user flow, frizioni, percorsi utente, semplificazione UX
- Required Agents: UX Research
- Optional Agents: Product Manager, UI Design, Frontend, Customer Success
- Sequence: UX Research -> Product Manager/UI Design/Frontend
- Stop Condition: domanda di ricerca o target user non chiari

---

## Customer Success Feedback

- Trigger: problemi di onboarding, adozione bassa, richieste ricorrenti, ticket ripetuti, frizioni post-lancio
- Required Agents: Customer Success
- Optional Agents: Product Manager, UX Research, Documentation
- Sequence: Customer Success -> Product Manager/UX Research -> altri se necessario
- Stop Condition: feedback non verificabile o problema utente non concreto

---

## Full Stack Feature

- Trigger: modifica coordinata frontend + backend
- Required Agents: Backend, Frontend
- Optional Agents: Product Manager, Architect, Reviewer, QA, Security, DevOps, Documentation
- Sequence: Product Manager se necessario -> Architect se necessario -> Backend/Frontend -> Reviewer -> QA
- Stop Condition: contratto API o ownership non definiti

---

## Bug Fix

- Trigger: comportamento errato, regressione, errore in produzione
- Required Agents: agente dello scope impattato
- Optional Agents: QA, Reviewer, DevOps, Architect
- Sequence: specialist -> Reviewer se necessario -> QA
- Escalate To Architect If: il bug rivela un problema strutturale
- Stop Condition: bug non riproducibile o impatto non compreso

---

## Code Review

- Trigger: richiesta esplicita di review, verifica pre-merge
- Required Agents: Reviewer
- Optional Agents: Architect, QA
- Sequence: Reviewer -> QA se serve validazione comportamentale
- Stop Condition: patch incompleta o contesto insufficiente

---

## QA Validation

- Trigger: verifica feature, regressione, release candidate
- Required Agents: QA
- Optional Agents: Reviewer, Backend, Frontend, DevOps
- Sequence: QA -> specialist se emergono problemi
- Stop Condition: requisiti o expected behavior non definiti

---

## Release / Deployment

- Trigger: deploy, pipeline, infra, env, rollback, monitoraggio
- Required Agents: DevOps
- Optional Agents: Backend, Architect, QA, Security, Documentation
- Sequence: DevOps -> QA se necessario -> Documentation
- Stop Condition: rollback, backup o observability non chiari

---

## Documentation Update

- Trigger: nuova feature, modifica API, nuova procedura, ADR
- Required Agents: Documentation
- Optional Agents: Architect, Backend, Frontend, DevOps, Product Manager
- Sequence: source agent -> Documentation
- Stop Condition: comportamento reale non ancora stabilizzato

---

## Security Review

- Trigger: autenticazione, autorizzazioni, gestione segreti, input non validati, superfici di attacco, dipendenze vulnerabili, esposizione dati sensibili
- Required Agents: Security
- Optional Agents: Backend, Architect, QA, Legal, DevOps
- Sequence: Security -> specialisti -> QA
- Escalate To Architect If: la mitigazione richiede modifiche strutturali
- Stop Condition: dati, endpoint o modello dei permessi non definiti

---

## Legal / Privacy Review

- Trigger: dati personali, consensi, retention, cookie, policy, compliance
- Required Agents: Legal
- Optional Agents: Product Manager, Architect, Backend, QA, Security
- Sequence: Legal -> specialisti
- Stop Condition: finalità del trattamento o dati raccolti non chiari

---

# Priority Rules

Se una richiesta coinvolge dati, permessi o compliance, prevalgono sempre:

1. Security
2. Data integrity
3. Compliance
4. Feature speed

L'owner della sicurezza è il Security Agent. Quando una richiesta tocca autenticazione, autorizzazioni, dati sensibili o segreti, il Security Agent deve essere coinvolto come agente opzionale anche se non esplicitamente richiamato dal trigger principale.

---

# Final Principle

Se non è chiaro chi deve lavorare sulla richiesta, il sistema non è pronto a eseguirla.

La routing matrix esiste per eliminare questa ambiguità.
