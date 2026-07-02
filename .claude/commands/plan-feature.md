---
description: Pianifica una feature — grounding nel repo, domande solo sul mancante (intent-driven), spec dal template contract-first, microtask con dipendenze e lane (parallel-execution). Si ferma per approvazione, niente codice.
argument-hint: docs/specs/000-brief.md
allowed-tools: Read, Grep, Glob, Write, Skill
---

Sei in **modalità pianificazione**. NON scrivere codice di produzione e non spawnare teammate.
Il file di brief da cui parti è: **$ARGUMENTS** (se vuoto, chiedi all'utente dove sta il brief).

Esegui questi passi nell'ordine, fermandoti dove indicato.

## 1. Grounding nel repo (anti-hallucination, anti-context-rot)
- Leggi `CLAUDE.md` e le `rules/` pertinenti al brief. Lo stack è già lì: non ripeterlo, non assumerlo.
- Leggi il brief `$ARGUMENTS`.
- Ispeziona il **codice reale** con glob/grep per i fatti tecnici che puoi dedurre da solo: nomi di model/tabelle/route/componenti esistenti, envelope di risposta, convenzioni, `TableDefinition` già presenti. **Non inventare**: ciò che esiste lo verifichi, non lo ipotizzi.

## 2. Domande solo sul mancante (intent-driven)
- Invoca la skill **`intent-driven-development`**.
- Distingui: **fatti tecnici** (li deduci dal repo → NON chiedere) vs **decisioni che cambiano lo scope** e che non puoi dedurre (→ chiedi).
- Fai all'utente **solo** le domande che cambiano davvero scope/contratto/comportamento. Niente domande la cui risposta è già nel codice. Poche, mirate, numerate.
- **FERMATI** e attendi le risposte prima di procedere.

## 3. Scrivi la spec (contract-first, tu proponi → l'utente valida)
- Copia la struttura di `docs/specs/templates/spec.template.xml` in `docs/specs/<NNN>-<nome-feature>.xml` (numerazione progressiva).
- Compila: `goal`, `context` (con i nomi reali verificati al passo 1), **`scope/out` esplicito** (anti-drift), **`data_contract` congelato** (endpoint, shape request/response, errori — la stessa shape che backend e frontend useranno senza comunicare), `acceptance_criteria` **osservabili e mappati 1:1 sui test** (AC-NNN), `constraints` (librerie consentite, authz server-side, nessuna nuova dipendenza).
- Dominio/naming in italiano; non mescolare lingue dentro un identificatore.

## 4. Piano a microtask (parallel-execution)
- Invoca la skill **`parallel-execution-optimizer`**.
- Decomponi in microtask. Per ciascuno: **owner** (teammate `backend`/`frontend`/`ui-design`), **write surface** (quali file/cartelle tocca), **dipendenze esplicite**, **lane** (parallelo / sequenziale / gated), e **come si verifica** (quali AC, quali test).
- Regola dura: due microtask in parallelo **non condividono write surface**. Ciò che collide va sequenziale o gated.
- Il contratto del passo 3 è il punto di sincronizzazione: una volta congelato, backend e frontend procedono in parallelo senza messaggi.

## 5. Stop per approvazione
- Presenta: spec creata (path) + tabella microtask (owner, dipendenze, lane, verifica).
- **FERMATI.** Non scrivere codice. Chiedi approvazione esplicita o correzioni.
- Solo dopo l'approvazione si passa a `/build-feature docs/specs/<NNN>-<nome-feature>.xml`.
