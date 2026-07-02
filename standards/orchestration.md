# Orchestration Rules

## Purpose

Definire come gli agenti collaborano tra loro, in quale ordine devono essere coinvolti e chi prende le decisioni finali.

Questo documento trasforma i ruoli in un workflow operativo ripetibile.

---

# Core Principle

Gli agenti non lavorano in parallelo in modo arbitrario.

Ogni richiesta deve seguire un flusso di orchestrazione esplicito.

Se il flusso non è chiaro, l'agente deve fermarsi e chiedere chiarimenti.

---

# Default Workflow

## 1. Intake

L'agente che riceve la richiesta deve:

- classificare il tipo di richiesta
- identificare gli standard applicabili
- identificare gli agenti necessari
- dichiarare assunzioni, vincoli e rischi iniziali

---

## 2. Scope Definition

Se la richiesta riguarda prodotto, priorità, requisiti o MVP, deve intervenire il Product Manager Agent.

Se la richiesta è strategica o di investimento, deve intervenire il Founder Agent.

Se la richiesta richiede comprensione utenti, flussi o frizioni operative, deve intervenire il UX Research Agent.

Se la richiesta nasce da problemi di onboarding, adozione o feedback ricorrenti, deve intervenire il Customer Success Agent.

Se i requisiti non sono chiari, nessun agente implementativo deve iniziare il lavoro.

---

## 3. Architecture Review

L'Architect Agent deve essere coinvolto quando la richiesta:

- introduce nuove feature cross-cutting
- modifica strutture dati o API
- impatta più layer del sistema
- introduce nuove dipendenze
- modifica pattern architetturali
- genera dubbi su scalabilità, sicurezza o separazione delle responsabilità

Per modifiche piccole e locali, l'Architect Agent può essere saltato.

---

## 4. Implementation

Dopo definizione dello scope e verifica architetturale:

- il Backend Agent implementa tutto ciò che riguarda backend
- il Frontend Agent implementa tutto ciò che riguarda frontend
- il UI Design Agent supporta decisioni di interfaccia, coerenza visiva e accessibilità
- il DevOps Agent interviene su deploy, CI/CD, configurazione, osservabilità e rilascio
- il Documentation Agent aggiorna la documentazione quando necessario

Gli agenti implementativi non devono cambiare il proprio scope.

---

## 5. Review

Il Reviewer Agent verifica:

- leggibilità
- manutenibilità
- coerenza architetturale
- duplicazioni
- debito tecnico

Il Reviewer Agent non sostituisce il QA Agent.

---

## 6. Validation

Il QA Agent verifica:

- comportamento funzionale
- regressioni
- edge case
- permessi
- error handling

Se la modifica è ad alto rischio su dati, permessi o privacy, coinvolgere anche il Legal Agent quando applicabile.

---

## 6b. Security Review

Il Security Agent deve essere coinvolto quando la modifica tocca:

- autenticazione o autorizzazioni
- gestione di dati sensibili o segreti
- input provenienti dall'esterno
- nuove dipendenze o superfici di attacco

Il Security Agent è l'owner della sicurezza trasversale e affianca QA e Architect senza sostituirli.

---

## 7. Release Readiness

Il DevOps Agent deve essere coinvolto prima del rilascio quando la modifica impatta:

- build
- deploy
- environment variables
- migrazioni
- job schedulati
- osservabilità
- rollback

---

# Authority Model

## Product Authority

Su priorità, MVP e scope decide il Product Manager Agent.

Su scelte strategiche di business decide il Founder Agent.

Su dubbi relativi a utilità per l'utente e semplificazione dei flussi, il UX Research Agent fornisce input specialistico.

Su dubbi relativi a onboarding, adozione e frizioni ricorrenti, il Customer Success Agent fornisce input specialistico.

---

## Technical Authority

Su architettura, pattern e impatti strutturali decide l'Architect Agent.

Su dettagli implementativi nel proprio ambito decide l'agente specialista competente.

---

## Quality Authority

Il Reviewer Agent può richiedere modifiche per problemi tecnici.

Il QA Agent può bloccare l'approvazione per problemi funzionali o regressioni.

Il Security Agent può bloccare il rilascio se rileva vulnerabilità sfruttabili.

Il Legal Agent può bloccare il rilascio se rileva rischi normativi evidenti.

---

# Conflict Resolution

Se due agenti sono in conflitto, usare questo ordine:

1. Requisiti espliciti del progetto
2. standards/decision-making.md
3. Product Manager Agent per conflitti di scope o valore
4. Architect Agent per conflitti tecnici
5. User decision come autorità finale

---

# Stop Conditions

Ogni agente deve fermarsi e non procedere se manca almeno uno di questi elementi:

- requisiti minimi comprensibili
- confini dello scope
- documentazione minima necessaria
- autorizzazione a modificare architettura quando richiesta

---

# Required Sequence

Ordine minimo raccomandato per richieste di delivery:

1. Intake
2. Product/Founder se necessario
3. UX Research/Customer Success se necessario
4. Architect se necessario
5. Specialist implementation
6. Reviewer
7. QA
8. Security se la modifica tocca dati, permessi o segreti
9. Legal se applicabile
10. DevOps/Documentation se necessario

Il workflow può essere ridotto solo se la richiesta è piccola, locale e a basso rischio.

---

# Final Principle

Velocità senza orchestrazione produce risultati incoerenti.

Ogni richiesta deve avere un percorso esplicito, un responsabile per fase e un criterio chiaro di handoff.
