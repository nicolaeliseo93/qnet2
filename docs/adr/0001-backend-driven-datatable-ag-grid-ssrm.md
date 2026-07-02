# Architecture Decision Record

> ADR generato dal template `templates/architecture-decision.md`.
> Owner: Architect Agent.

---

## ADR ID

0001

## Title

Backend-driven DataTable (AG Grid Enterprise + Server-Side Row Model)

## Status

SUPERSEDED BY ADR-0002

> Superseded by `docs/adr/0002-generic-domain-driven-table-registry.md`, which
> generalizes this users-specific DataTable into a domain-driven Table Registry
> (one pair of endpoints `GET/POST /api/tables/{domain}/columns|rows` for any
> domain). The SSRM/envelope/actions design below is **reused** by ADR-0002; the
> users-specific endpoints (`/api/users/table/*`) and classes are **replaced**.
> The companion contract `docs/api/0001-users-datatable.md` is superseded by
> `docs/api/0002-generic-tables.md`.

## Date

2026-06-12

---

## Context

Serve un pattern riutilizzabile per le tabelle dati dell'applicazione, a partire
dalla pagina **Users**. I requisiti:

- Colonne disponibili, tipo di campo, visibilità di default, filtri disponibili,
  default sort e default pagination devono essere **decisi dal backend**, non
  hardcoded nel frontend.
- Paginazione, filtri e ordinamenti devono essere eseguiti **server-side** e
  coerenti con i permessi dell'utente.
- Le azioni riga devono essere calcolate **per singola riga** e autorizzate
  server-side (un utente può poter aggiornare una riga e non un'altra).
- Il frontend deve restare il più **agnostico** possibile: riceve uno schema e lo
  rende, mappando al più alcuni `columnId` a rendering custom (badge, link,
  formattazioni).
- Riuso obbligatorio dei pattern esistenti: navigation backend-driven
  (`NavigationController` + `NavigationService` + config + Resource), envelope di
  `BaseApiController` (`paginatedResponse()` / `ok()` / `fail()`), e
  autorizzazione via `BasePolicy` / `UserPolicy` (permessi Spatie).

Vincoli decisi dall'utente (non rinegoziabili):

1. **AG Grid Enterprise** con **Server-Side Row Model (SSRM)**.
2. Scope full-stack end-to-end sulla pagina **Users**.
3. Azioni riga come **array di chiavi consentite** per riga (es.
   `["view","edit","delete"]`), con un **catalogo azioni** separato nel config che
   descrive label / icona / tipo / conferma; le 3 standard (view/edit/delete) devono
   essere estendibili con chiavi custom.

Stack confermato (`standards/architecture.md`): Laravel + Spatie Permission +
Policies lato backend; React + TS + Vite + TanStack Query + Axios + AG Grid lato
frontend. AG Grid **non è ancora installato** nel frontend.

---

## Decision

Adottare un pattern **Backend-driven DataTable** in due endpoint, modellato sul
pattern navigation esistente:

1. **Config endpoint** — `GET /api/users/table/config`
   Restituisce lo schema della tabella (colonne, tipi, visibilità default,
   catalogo filtri, default sort, default pagination, catalogo azioni). Gate di
   accesso: `UserPolicy::viewAny` (`users.viewAny`). Lo schema è dichiarato in un
   file di config (`config/tables/users.php`) e filtrato/risolto da un
   `UserTableService` in funzione dei permessi dell'utente, esattamente come
   `NavigationService` filtra `config/navigation.php`. Il frontend usa questo
   schema per costruire le `ColDef` di AG Grid.

2. **Data endpoint (SSRM)** — `POST /api/users/table/rows`
   Riceve il payload SSRM di AG Grid (`startRow`, `endRow`, `sortModel`,
   `filterModel`), lo **traduce** in `offset`/`limit`/sort/filtri server-side,
   applica i permessi, e restituisce le righe più il conteggio totale **riusando
   l'envelope esistente `paginatedResponse()`**. È un `POST` (non `GET`) perché
   `sortModel`/`filterModel` sono strutture annidate: si evita di serializzarle
   in query string. Gate di accesso: `users.viewAny`.

**Mapping SSRM ↔ envelope esistente** (riuso, nessun envelope nuovo):

| AG Grid SSRM | Backend (riuso `paginatedResponse`) |
|---|---|
| `startRow` | `offset = startRow` |
| `endRow` | `limit = endRow - startRow` (cap `MAX_LIMIT = 100`) |
| `sortModel[]` | tradotto in `ORDER BY` whitelisted |
| `filterModel{}` | tradotto in `WHERE` whitelisted |
| risposta `rowData` | `items` |
| risposta `rowCount` / `lastRow` | derivato da `pagination.total` |

Il **datasource SSRM lato frontend** è un adapter sottile che legge
`items` + `pagination.total` dall'envelope e chiama
`params.success({ rowData, rowCount })`. Non si introduce un secondo formato di
risposta.

**Azioni riga**: il data endpoint include per ogni riga un campo `actions: string[]`
(chiavi consentite, calcolate via Policy riga-per-riga: `view`→`UserPolicy::view`,
`edit`→`update`, `delete`→`delete`). Il **catalogo azioni** vive nel config
ritornato dal config endpoint (`actions[]` con `key`, `label`, `icon`, `type`,
`confirm`). Il frontend incrocia `row.actions` (cosa è permesso) con il catalogo
(come renderizzare) — vedi contratto in `docs/api/0001-users-datatable.md`.

**Autorizzazione server-side obbligatoria** su entrambi gli endpoint: `viewAny`
per l'accesso alla tabella; `view`/`update`/`delete` per le azioni-riga. Il
frontend nasconde/disabilita azioni solo come UX gate (`Can`), ma non è la fonte
di verità.

Il **contratto API esatto** (method+path, payload SSRM, envelope, schema config,
forma actions, esempi JSON) è documentato in
`docs/api/0001-users-datatable.md` ed è parte integrante di questa decisione.

---

## Alternatives Considered

- **AG Grid Client-Side Row Model** — scartata: il dataset users può crescere e
  paginazione/filtri/ordinamenti devono restare server-side e coerenti con i
  permessi. Vincolo utente esplicito per SSRM.
- **Schema colonne hardcoded nel frontend** — scartata: viola il requisito
  "backend-driven" e duplica la conoscenza dello schema su due lati, allontanandosi
  dal pattern navigation già consolidato.
- **`GET` con `sortModel`/`filterModel` serializzati in query string** —
  scartata: strutture annidate fragili da serializzare/validare; `POST` con
  FormRequest è più robusto e validabile (read-only di natura, nessun side effect).
- **Nuovo envelope di risposta dedicato a SSRM** (`{rowData,lastRow}`) —
  scartata: `BaseApiController::paginatedResponse()` espone già
  `items` + `pagination.total`; basta un adapter frontend. Evita divergenza di
  envelope (`standards/decision-making.md` → Reuse Before Build).
- **Azioni riga come booleani (`can_edit`, `can_delete`)** — scartata: non
  estendibile a chiavi custom; l'array di chiavi + catalogo separato è il vincolo
  utente ed è più scalabile.

---

## Trade-offs

- **Vantaggi**
  - Schema e permessi centralizzati lato backend; frontend agnostico e
    riutilizzabile per future tabelle.
  - Riuso totale di envelope, Policy e pattern config/Service esistenti: minimal
    change, basso debito tecnico.
  - Autorizzazione riga-per-riga reale e server-side.
- **Svantaggi**
  - Una richiesta SSRM per blocco di righe (latenza di rete per scroll/paginazione).
  - Traduzione `filterModel`/`sortModel` → query è un punto di responsabilità da
    mantenere whitelisted (sicurezza).
- **Cosa rinunciamo a ottenere**
  - Filtri/ordinamenti istantanei lato client su tutto il dataset (consapevole:
    serve coerenza coi permessi e scalabilità).

---

## Consequences

- **Positivi**: un solo pattern per tutte le DataTable; aggiungere una tabella =
  un file config + un Service + un Controller sottile + un Resource, più un
  feature folder frontend che riusa il wrapper AG Grid.
- **Negativi**: introduzione della dipendenza **AG Grid Enterprise** (licenza) nel
  frontend; va installata e configurata (modulo SSRM + chiave di licenza via env).
- **Debito tecnico tracciabile**: il primo `UserTableService` definirà di fatto il
  contratto interno di traduzione filtri/sort; quando arriverà la seconda tabella,
  valutare l'estrazione di una base condivisa (`AbstractTableService`) — **non**
  anticiparla ora (`standards/decision-making.md` → Avoid Premature Abstraction).

---

## Affected Agents

- **Backend Agent** (owner implementazione endpoint, Service, config, Policy reuse,
  FormRequest, Resource).
- **Frontend Agent** (owner installazione AG Grid Enterprise, wrapper riutilizzabile,
  datasource SSRM, feature folder users, gating `Can`).
- **Security Agent** (validare whitelist filtri/sort e autorizzazione riga-per-riga).
- **Reviewer / QA** (coerenza envelope, permessi, regressioni).
- **DevOps** (env var chiave licenza AG Grid Enterprise).

---

## Risks

- **Mass assignment / injection sui filtri**: `filterModel`/`sortModel` devono
  essere validati contro la whitelist delle colonne `filterable`/`sortable` del
  config; mai passare nomi colonna grezzi alla query. Mitigazione: FormRequest +
  mapping esplicito nel Service.
- **N+1 sui ruoli**: la colonna `roles` richiede eager loading (`with('roles')`).
- **Licenza AG Grid Enterprise**: la chiave non deve finire nel repo
  (`standards/security-standards.md` → Secrets Management); va in env frontend.
- **Drift schema config vs colonne reali**: lo schema deve basarsi solo sui campi
  reali della tabella `users` (`id, name, email, locale, email_verified_at,
  created_at`; `roles` come campo derivato da Spatie). Niente colonne inventate.

---

## References

- `docs/api/0001-users-datatable.md` — contratto API esatto (config + SSRM + actions).
- Pattern navigation: `app/Http/Controllers/Navigation/NavigationController.php`,
  `app/Services/NavigationService.php`, `config/navigation.php`,
  `app/Http/Resources/NavigationItemResource.php`.
- Envelope: `app/Http/Controllers/Abstract/BaseApiController.php`.
- Permessi: `app/Policies/Abstracts/BasePolicy.php`, `app/Policies/UserPolicy.php`.
