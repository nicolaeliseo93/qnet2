# Agent Operating System

## Scopo

Questo repository definisce un sistema operativo multi-agente.

Se sei un assistente AI che lavora in questo repository, devi usare gli agenti e gli standard locali prima di rispondere o apportare modifiche.

Questo file è il punto di ingresso primario (bootstrap) per gli strumenti agentici.

---

# Bootstrap Obbligatorio

Prima di qualsiasi lavoro, leggi questi file nell'ordine indicato:

1. `standards/ai-rules.md`
2. `standards/orchestration.md`
3. `standards/routing-matrix.md`
4. `standards/handoff-protocol.md`

Poi leggi solo i file degli agenti rilevanti per la richiesta corrente.

Non caricare tutti i file degli agenti a meno che la richiesta non coinvolga realmente molti ruoli.

---

# Standard Secondari

I quattro documenti del bootstrap definiscono *come orchestrare* il lavoro.

I documenti seguenti definiscono *come svolgere bene il lavoro* e sono richiamati come obbligatori dai singoli agenti. Leggi quelli pertinenti alla richiesta quando un agente lo impone:

- `standards/architecture.md` — stack, layering backend/frontend, regole strutturali
- `standards/coding-standards.md` — convenzioni di codice, naming, dimensione file
- `standards/security-standards.md` — requisiti minimi di sicurezza
- `standards/quality-gates.md` — Definition of Done e criteri di completamento
- `standards/decision-making.md` — gerarchia decisionale tecnica e di prodotto
- `standards/product-rules.md` — principi di prodotto e MVP
- `standards/company-values.md` — principi guida trasversali

Nessun agente deve ignorare gli standard secondari elencati nel proprio file.

---

# Comportamento Runtime Richiesto

Per ogni richiesta devi:

1. Classificare la richiesta usando `standards/routing-matrix.md`
2. Identificare:
   - tipo di richiesta
   - agenti richiesti
   - agenti opzionali
   - condizione di stop
3. Seguire l'ordine di esecuzione in `standards/orchestration.md`
4. Rispettare i confini di ogni agente
5. Terminare ogni fase con un handoff strutturato secondo `standards/handoff-protocol.md`

Se scope, ownership, comportamento atteso o autorità architetturale non sono chiari, fermati e chiedi.

---

# Regole di Selezione degli Agenti

Usa questi file come fonte di verità:

- `agents/product-manager.md`
- `agents/founder.md`
- `agents/architect.md`
- `agents/backend.md`
- `agents/frontend.md`
- `agents/ui-design.md`
- `agents/ux-research.md`
- `agents/customer-success.md`
- `agents/reviewer.md`
- `agents/qa.md`
- `agents/security.md`
- `agents/devops.md`
- `agents/documentation.md`
- `agents/legal.md`

Invoca solo gli agenti il cui scope è richiesto dalla routing matrix.

---

# Formato Minimo di Output

All'inizio del lavoro, dichiara:

- Request Type
- Required Agents
- Optional Agents
- Next Owner
- Stop Condition

Al termine di ogni fase, produci un handoff strutturato con:

- Context
- Assumptions
- Decision
- Scope
- Files Impacted
- Risks
- Validation Needed
- Next Owner
- Blocking Questions

---

# Guida Rapida al Routing

Usa questa mappatura veloce prima di leggere la matrice dettagliata:

- prodotto, MVP, scope, prioritizzazione -> Product Manager
- viabilità di business, mercato, investimento -> Founder
- architettura, modifiche trasversali, decisioni strutturali -> Architect
- API, database, servizi, permessi, migrazioni -> Backend
- pagine, componenti, form, flussi UX, stato client -> Frontend
- coerenza visiva, accessibilità, revisione responsive -> UI Design
- comprensione utenti, frizioni, flussi, utilità -> UX Research
- onboarding, adozione, frizioni ricorrenti -> Customer Success
- qualità del codice e manutenibilità -> Reviewer
- validazione funzionale e controlli di regressione -> QA
- minacce, superfici di attacco, hardening, gestione segreti -> Security
- deploy, CI/CD, ambienti, rollback, osservabilità -> DevOps
- documentazione, ADR, setup, procedure -> Documentation
- privacy, compliance, rischio legale -> Legal

---

# Regole Non Negoziabili

- Non inventare requisiti, API, tabelle o comportamenti
- Non saltare il routing
- Non saltare l'handoff
- Non lasciare che un agente assorba silenziosamente il ruolo di un altro
- Non modificare l'architettura senza l'autorità definita in `standards/orchestration.md`

---

# Regola Pratica

Se hai a disposizione un solo prompt utente, tratta questo file come il bootstrap di sistema su come usare il repository.
