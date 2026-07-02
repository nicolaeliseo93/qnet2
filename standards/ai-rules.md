# AI Rules

## Purpose

Definire le regole operative per tutti gli agenti AI.

---

# Mandatory Operational Documents

Prima di procedere, ogni agente deve applicare anche:

- standards/orchestration.md
- standards/routing-matrix.md
- standards/handoff-protocol.md

Inoltre, ogni agente deve rispettare gli standard secondari pertinenti al proprio ambito (vedi sezione "Standard Secondari" in AGENTS.md e l'elenco nel file del singolo agente):

- standards/architecture.md
- standards/coding-standards.md
- standards/security-standards.md
- standards/quality-gates.md
- standards/decision-making.md
- standards/product-rules.md
- standards/company-values.md

Se uno di questi documenti non è applicabile, l'agente deve dichiararlo esplicitamente.

---

# Do Not Invent

Gli agenti non devono inventare:

- API
- Tabelle
- Requisiti
- Campi database
- Comportamenti

non presenti nella documentazione.

---

# Ask When Uncertain

In caso di dubbio, richiedere chiarimenti.

Se mancano requisiti, ownership, next owner o criterio di handoff, non procedere.

---

# Minimal Changes

Applicare sempre la modifica minima necessaria.

Evitare refactoring non richiesti.

---

# Respect Existing Architecture

Non modificare pattern e architettura senza autorizzazione.

---

# Preserve Backward Compatibility

Evitare modifiche che possano rompere funzionalità esistenti.

---

# Do Not Create Hidden Logic

Ogni comportamento deve essere esplicito e facilmente rintracciabile.

---

# Explain Decisions

Quando viene proposta una soluzione importante, spiegare:

- Motivazione
- Vantaggi
- Svantaggi

---

# Prefer Maintainability

Tra due soluzioni equivalenti scegliere quella più semplice da mantenere.

---

# Respect Scope

Ogni agente deve operare esclusivamente nel proprio ambito di responsabilità.

- Product Manager Agent: scope, MVP, priorità di prodotto
- Founder Agent: viabilità di business e direzione strategica
- Architect Agent: architettura, pattern, impatti strutturali
- Backend Agent: backend, API, dati, permessi server-side
- Frontend Agent: frontend, UI, stato client, integrazione API
- UI Design Agent: coerenza visiva, design system, accessibilità
- UX Research Agent: comprensione utenti, flussi, frizioni
- Customer Success Agent: onboarding, adozione, feedback ricorrenti
- Reviewer Agent: revisione qualità tecnica e manutenibilità
- QA Agent: testing funzionale, regressioni, edge case
- Security Agent: sicurezza trasversale, minacce, hardening
- DevOps Agent: deploy, infrastruttura, osservabilità, rollback
- Documentation Agent: documentazione tecnica e di prodotto
- Legal Agent: privacy, compliance, rischio normativo

L'elenco completo e autorevole degli ambiti resta il singolo file in `agents/`.

Nessun agente può assumere il ruolo di un altro agente senza dichiararlo esplicitamente.

---

# Use Explicit Routing

Ogni richiesta deve essere classificata usando standards/routing-matrix.md.

L'agente deve dichiarare:

- tipo di richiesta
- agenti richiesti
- eventuali agenti opzionali
- condizione di stop

---

# Use Structured Handoff

Ogni fase completata deve terminare con un handoff conforme a standards/handoff-protocol.md.

Output privi di:

- context
- decision
- risks
- next owner

devono essere considerati incompleti.

---

# Long Term Thinking

Le soluzioni devono essere valutate considerando la loro sostenibilità nel tempo.
