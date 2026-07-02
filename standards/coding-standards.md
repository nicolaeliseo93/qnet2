# Coding Standards

## Purpose

Questo documento definisce le convenzioni di sviluppo da rispettare in tutti i progetti.

L'obiettivo è garantire:

- Coerenza
- Leggibilità
- Manutenibilità
- Qualità del codice

---

# General Rules

## Prefer Simplicity

Preferire sempre la soluzione più semplice che risolve il problema.

Evitare:

- Overengineering
- Astrazioni premature
- Pattern inutili

---

## Readability First

Il codice viene letto più volte di quante venga scritto.

Favorire:

- Nomi chiari
- Funzioni brevi
- Responsabilità singole

---

## Avoid Duplication

Seguire il principio DRY.

Se una logica viene duplicata più volte, valutarne l'estrazione.

---

# Language (English Only)

Tutto il codice e tutto il testo devono essere **rigorosamente in inglese**. Questa
regola è non negoziabile e si applica a backend, frontend, database e documentazione.

## Codice — sempre inglese

Devono essere in inglese, senza eccezioni:

- Identificatori: variabili, funzioni, metodi, classi, interfacce, enum, costanti
- Nomi di tabelle, colonne, indici e migrazioni del database
- Nomi di file e cartelle
- Commenti nel codice
- Messaggi di log
- Messaggi di commit e branch
- Documentazione tecnica (ADR, spec, API, README, handoff)
- Chiavi di configurazione e variabili d'ambiente

## Testo rivolto all'utente — inglese + i18n

Anche il testo mostrato all'utente finale (UI, email, notifiche, messaggi di
validazione, messaggi di errore API) deve avere l'**inglese come lingua sorgente**.

- L'inglese è la lingua di default dell'applicazione.
- Le altre lingue (es. italiano) sono fornite tramite il sistema di internazionalizzazione (i18n):
    - **Backend**: Laravel localization (`__()` / file in `lang/`), default `en`, traduzioni in `lang/it.json`.
    - **Frontend**: libreria i18n (default `en`, risorse `it`).
- È vietato scrivere stringhe utente hardcoded in italiano: usare sempre le chiavi di traduzione.

## Regola pratica

Se uno sviluppatore di qualsiasi nazionalità apre il progetto, deve trovare codice e
testo sorgente interamente in inglese. La lingua dell'utente finale è una
configurazione, non una scelta hardcoded nel codice.

---

# Naming Conventions

## Classes

Utilizzare PascalCase.

Esempi:

text CreateTaskService UserController CompanyPolicy

---

## Variables

Utilizzare camelCase.

Esempi:

text userId companyName createdAt

---

## Database Tables

Utilizzare snake_case plurale.

Esempi:

text users task_comments company_contacts

---

## Database Columns

Utilizzare snake_case.

Esempi:

text created_at updated_at assigned_user_id

---

# Backend Standards

## Controllers

I Controller devono contenere solamente:

- Validazione
- Autorizzazione
- Chiamata ai Service
- Risposta

---

## Services

Tutta la business logic deve vivere nei Service.

I Service devono:

- Essere focalizzati
- Avere responsabilità chiare
- Essere facilmente testabili

---

## Data Transfer Objects

I dati che attraversano un confine di layer (Controller ↔ Service, FormRequest → Service) devono viaggiare in un **DTO dichiarato** (`final readonly`, proprietà `public readonly` tipizzate in `App\DataObjects`), non in array associativi "magici".

- Vietati gli **array magici volanti** come valore di ritorno o parametro di business (forma descritta solo da `array<string, mixed>`): nessun autocomplete, nessuna type-safety, si rompono in silenzio.
- Un metodo che restituisce più valori correlati → restituisce un DTO. Un metodo che riceve più campi correlati dall'input → li riceve come DTO costruito dalla FormRequest.
- Restano array per eccezione i cataloghi di configurazione dichiarativi e il payload JSON finale serializzato (vedi `standards/architecture.md` → Data Transfer Objects).

---

## Models

Ogni model di dominio deve:

- estendere `App\Models\Abstracts\BaseModel`;
- dichiarare sempre `$fillable` (mai `$guarded = []` né `$fillable` assente);
- usare il trait standard `App\Models\Concerns\LogsModelActivity` per il collegamento all'activity log;
- avere una **Policy** dedicata che estende `App\Policies\Abstracts\BasePolicy` con i permessi standard CRUD (registrati via `php artisan permissions:sync`);
- avere una **Factory** dedicata in `Database\Factories` (trait `HasFactory`) che produce uno stato di default valido — obbligatoria per testing e seeding.

Vedi `standards/architecture.md` → Models. I dati sensibili vanno tenuti tra gli `hidden`, così non vengono mai loggati.

---

## Validation

Utilizzare sempre FormRequest.

Non utilizzare validazioni inline nei Controller.

---

## Database

Evitare query duplicate.

Utilizzare eager loading quando necessario.

Evitare N+1 Query.

---

## Exceptions

Gestire gli errori tramite Exception dedicate quando opportuno.

Evitare try/catch inutili.

---

# Frontend Standards

## TypeScript

Evitare l'utilizzo di any.

Preferire tipi espliciti.

---

## Components

Un componente deve avere una responsabilità chiara.

Quando un componente diventa troppo grande, valutarne la suddivisione.

---

## Loading States (Skeleton First)

Ogni sviluppo che prevede una fase di caricamento (fetch dati, transizione async,
streaming di righe, lazy loading) **deve** mostrare uno **skeleton** come stato di
loading di default. Questa regola è obbligatoria, non opzionale.

Regole:

- Usare sempre il primitivo condiviso `Skeleton` (`@/components/ui/skeleton`),
  mai uno spinner generico o uno schermo vuoto come stato di caricamento primario.
- Lo skeleton deve **rispecchiare la forma del contenuto** che sostituisce:
  stesse aree, stesso numero di elementi/righe quando noto (es. skeleton a forma
  di colonna per le tabelle, righe pari alla dimensione pagina). Evitare un blocco
  unico indistinto.
- Lo skeleton fa parte della triade obbligatoria **loading / error / empty**: ogni
  vista che carica dati deve gestire tutti e tre gli stati (vedi anche
  `agents/frontend.md` → "Gestire loading, error e empty state").
- Per i dati server-side preferire la gestione di `isPending` di TanStack Query
  (o equivalente) per montare lo skeleton finché i dati non sono disponibili.
- Lo spinner è ammesso solo per micro-interazioni puntuali (es. bottone in
  submit), non come stato di caricamento di una pagina, sezione o tabella.

Esempio di riferimento: lo skeleton della DataTable AG Grid
(`frontend/src/components/data-table/data-table.tsx`,
`docs/adr/0003-ag-grid-table-loading-skeleton.md`).

---

## API Calls

Tutte le chiamate API devono passare dal layer dedicato.

Mai effettuare chiamate HTTP direttamente nei componenti UI.

---

## Forms

Utilizzare React Hook Form.

Le validazioni devono utilizzare Zod.

---

## Entity Detail Cards (Fresh On Open)

Ogni volta che si apre la **scheda di un'entità** (Sheet/dialog di *view* o *edit*
avviato da una riga di tabella o da una lista), la vista **deve rifare il fetch
del `show`** dal backend prima di mostrare i dati. La riga della griglia è solo uno
snapshot e i dati possono essere cambiati nel frattempo (anche per
ri-autorizzazione lato server). Questa regola è obbligatoria, non opzionale.

Regole:

- Usare sempre l'hook condiviso `useEntityDetail`
  (`frontend/src/hooks/use-entity-detail.ts`) per caricare il dettaglio di una
  scheda. Esso forza il refetch all'apertura (`refetchOnMount: 'always'`,
  `staleTime: 0`).
- Lo stato `isLoading` dell'hook resta `true` **finché il fetch di apertura non si
  è concluso**, anche se in cache esiste già uno snapshot *stale* di un'apertura
  precedente. Mostrare lo **skeleton** (vedi `## Loading States (Skeleton First)`)
  finché `isLoading` è `true`.
- Il form di edit deve essere montato **solo dopo** che i dati freschi sono
  disponibili, così che i suoi `defaultValues` (React Hook Form li cattura una
  sola volta al mount) partano da valori autorevoli e non da uno snapshot
  obsoleto della cache.
- Gestire sempre la triade **loading / error / empty**: in errore mostrare il
  messaggio con un'azione di retry (`refetch`), non i dati stale.

Esempio di riferimento: `EditUserLoader` in
`frontend/src/features/users/users-table.tsx` e `RoleDetailView` in
`frontend/src/features/roles/role-detail.tsx`.

---

# Comments

Scrivere commenti solo quando il codice non è sufficientemente esplicativo.

Evitare commenti inutili.

Preferire codice autoesplicativo.

---

# Design Principles (SOLID)

Tutto il codice deve rispettare i principi **SOLID**. Questa regola è obbligatoria e non negoziabile.

- **S — Single Responsibility**: ogni classe, componente o service ha una sola responsabilità e una sola ragione per cambiare (vedi anche `# File Size Guidelines` → Single Responsibility Principle).
- **O — Open/Closed**: le entità sono aperte all'estensione ma chiuse alla modifica; preferire l'estensione tramite astrazioni invece di modificare codice esistente stabile.
- **L — Liskov Substitution**: un'implementazione deve poter sostituire la propria astrazione senza alterare la correttezza del programma.
- **I — Interface Segregation**: preferire interfacce piccole e focalizzate; non forzare un client a dipendere da metodi che non usa.
- **D — Dependency Inversion**: i moduli di alto livello dipendono da astrazioni, non da implementazioni concrete; le dipendenze vanno iniettate, non istanziate internamente.

I principi SOLID non giustificano overengineering o astrazioni premature (vedi `## Prefer Simplicity`): si applicano per ottenere codice manutenibile, non per aggiungere complessità inutile.

---

# Development Methodology (TDD + SDD)

Lo sviluppo deve seguire **SDD** (Specification-Driven Development) e **TDD** (Test-Driven Development), in quest'ordine.

## SDD — Specification First

Prima di scrivere codice deve esistere una **specifica** approvata che definisca comportamento atteso, input/output, edge case e criteri di accettazione (vedi `docs/specs/` e i template in `templates/`). Non si implementa contro requisiti impliciti.

## TDD — Test First

L'implementazione segue il ciclo **Red → Green → Refactor**:

1. **Red**: scrivere prima il test che descrive il comportamento atteso (derivato dalla specifica) e verificarne il fallimento.
2. **Green**: scrivere il codice minimo necessario per far passare il test.
3. **Refactor**: migliorare il codice mantenendo i test verdi e rispettando SOLID.

Non è ammesso scrivere codice di produzione prima del relativo test.

---

# Testing

Ogni funzionalità critica deve essere testata.

I test devono coprire:

- Happy Path
- Error Handling
- Edge Cases

## Coverage

La **code coverage dei test deve essere SEMPRE minimo 85%**. Questa soglia è obbligatoria e non negoziabile.

- Si applica sia al backend sia al frontend.
- Una modifica che porta la coverage sotto l'85% non può essere considerata completata né mergiata (vedi `standards/quality-gates.md`).
- La copertura va misurata su codice significativo: non è ammesso gonfiare la metrica con test privi di asserzioni reali.

---

# Pull Request Rules

Prima del merge verificare:

- Codice leggibile
- Nessuna duplicazione evidente
- Nessun warning
- Nessun errore TypeScript
- Test superati
- Coverage ≥ 85%
- Conformità ai principi SOLID
- Test scritti in TDD a partire dalla specifica (SDD)
- Rispetto degli standard architetturali

---

# Golden Rule

Scrivere sempre codice che un altro sviluppatore possa comprendere rapidamente anche dopo anni.

# File Size Guidelines

## General Principle

La dimensione di un file non è il problema principale.

Il vero problema è la quantità di responsabilità che contiene.

---

## Preferred Size

Idealmente:

- Classi: 100 - 300 righe
- Componenti React: 100 - 300 righe
- Service: 50 - 300 righe

---

## Attention Threshold

Quando un file supera:

- 500 righe → valutare una possibile suddivisione
- 1000 righe → effettuare una revisione obbligatoria

---

## Mandatory Review

Se un file supera le 1000 righe, l'Architect Agent e il Reviewer Agent devono verificare:

- Se contiene più responsabilità
- Se può essere suddiviso in moduli più piccoli
- Se esistono duplicazioni
- Se la leggibilità è compromessa

---

## Refactoring Rule

Un file superiore a 1000 righe non deve essere automaticamente riscritto.

Deve però essere considerato un candidato prioritario per attività di refactoring.

---

## Single Responsibility Principle

Una classe, componente o service dovrebbe avere una responsabilità principale.

Se un file gestisce più domini o più processi differenti, deve essere suddiviso indipendentemente dal numero di righe.

---

## Exceptions

Sono ammesse eccezioni per:

- Tabelle di configurazione
- Mapping statici
- File generati automaticamente
- Componenti particolarmente complessi ma ben organizzati

Ogni eccezione deve essere giustificata durante la review.
