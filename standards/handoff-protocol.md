# Handoff Protocol

## Purpose

Definire il formato minimo obbligatorio per passare il lavoro da un agente all'altro.

Senza handoff strutturato, il sistema degrada in output scollegati.

---

# Mandatory Rule

Ogni agente che conclude una fase deve produrre un handoff esplicito.

L'agente successivo non deve ricostruire implicitamente il contesto.

---

# Required Handoff Format

Ogni handoff deve contenere queste sezioni, nell'ordine indicato:

## 1. Context

Descrizione breve della richiesta e del perimetro attuale.

## 2. Assumptions

Assunzioni usate per procedere.

Se non esistono assunzioni, scrivere "None".

## 3. Decision

Decisione presa nella fase corrente.

## 4. Scope

Cosa è incluso e cosa è escluso.

## 5. Files Impacted

File da creare, modificare o verificare.

Se non applicabile, scrivere "None".

## 6. Risks

Rischi noti, dubbi aperti, impatti possibili.

## 7. Validation Needed

Controlli ancora necessari.

Esempi:

- code review
- QA validation
- legal review
- migration review
- release verification

## 8. Next Owner

Agente che deve prendere in carico il passaggio successivo.

## 9. Blocking Questions

Domande che devono essere risolte prima di procedere.

Se non presenti, scrivere "None".

---

# Short Example

## Context

Aggiunta filtro stato nella lista ordini.

## Assumptions

- API ordini esistente modificabile

## Decision

Implementare filtro lato backend e integrazione UI lato frontend.

## Scope

Incluso filtro per stato.
Escluso salvataggio preferenze utente.

## Files Impacted

- orders controller
- orders service
- orders list page

## Risks

- Possibile regressione sui filtri esistenti

## Validation Needed

- reviewer
- QA regression

## Next Owner

Backend Agent

## Blocking Questions

None

---

# Handoff Rules By Agent Type

## Product / Founder

Devono chiarire:

- problema
- utente
- valore
- priorità
- scope minimo

---

## Architect

Deve chiarire:

- pattern scelto
- vincoli
- trade-off
- agenti coinvolti
- rischi tecnici

---

## Backend / Frontend / DevOps

Devono chiarire:

- soluzione implementata
- file toccati
- limiti
- test necessari
- rischi di regressione

---

## Reviewer

Deve chiarire:

- problemi trovati
- severità
- raccomandazione finale

---

## QA

Deve chiarire:

- scenari verificati
- bug trovati
- regressioni possibili
- esito finale

---

## Security

Deve chiarire:

- superfici di attacco valutate
- vulnerabilità trovate e severità
- mitigazioni richieste
- se il rischio blocca il rilascio

---

## Legal

Deve chiarire:

- dati trattati e finalità
- rischi normativi o di privacy
- obblighi documentali (policy, consensi)
- se il rischio blocca il rilascio

---

## Advisory Agents (UI Design / UX Research / Customer Success / Documentation)

Devono chiarire:

- insight o raccomandazione principale
- impatto su prodotto, UX o documentazione
- next owner che deve agire sull'output

---

# Invalid Handoff Examples

Sono handoff non validi:

- fatto
- sembra ok
- passo al prossimo
- output senza scope
- output senza next owner
- output senza rischi

---

# Final Principle

Ogni handoff deve ridurre incertezza, non trasferirla al prossimo agente.
