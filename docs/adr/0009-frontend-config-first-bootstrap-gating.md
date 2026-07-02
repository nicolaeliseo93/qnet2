# Architecture Decision Record

## ADR ID

0009

## Title

Config-first frontend bootstrap: `GET /api/config` come prima chiamata, gating ibrido (prefetch + ConfigGate)

## Status

ACCEPTED

## Date

2026-06-15

---

## Context

`GET /api/config` (ADR 0008) espone la config pubblica di bootstrap (enum options con label
localizzate) consumata trasversalmente dalla UI (select, badge). Requisito utente non
negoziabile: **la chiamata a `/api/config` deve essere la PRIMA chiamata in assoluto del
frontend, prima di ogni altra API — incluso il `me` dell'autenticazione — e nessuna parte
dell'app che esegua altre chiamate deve montare finché il config non è caricato.**

Stato attuale accertato (file reali in `frontend/`):

- `src/main.tsx`: `createRoot → <App/>`, importa `@/i18n`.
- `src/App.tsx`: `QueryClientProvider > AuthProvider > TooltipProvider > Suspense > RouterProvider`.
  **`AuthProvider` è sopra il router.**
- `src/features/auth/auth-provider.tsx`: al mount lancia `useQuery(authKeys.me)` con
  `enabled: Boolean(token)`. **Questa è la chiamata che il config deve precedere.**
- `src/features/config/`: feature **già esistente** — `api.ts` (`fetchConfig` → `GET /config`,
  unwrap envelope), `use-config.ts` (`useConfig`, `staleTime: Infinity`; `useEnumOptions`),
  `types.ts` (`AppConfig`), `query-keys.ts` (`configKeys.all = ['config']`). Va **riusata**.
- `src/app/query-client.ts`: `QueryClient` con `retry: 1`, `refetchOnWindowFocus: false`.
- `src/components/full-screen-loader.tsx`: loader full-screen `role="status"`.
- `src/api/client.ts`: axios; **nessun header `Accept-Language` esplicito** (usa il default browser).

Vincoli rilevanti:

- Il config è pubblico (pre-login): può e deve essere caricato prima di qualsiasi auth.
- `coding-standards.md → Loading States (Skeleton First)` impone skeleton come stato di loading
  di default, vietando lo spinner come loading primario di pagina/sezione.
- `AuthProvider` deve restare sopra al router perché `me` non parta prima del config; un gate
  deve quindi collocarsi **sopra `AuthProvider`**.
- Un gate basato su hook query deve stare **sotto `QueryClientProvider`**.

---

## Decision

Adottare un **pattern ibrido (prefetch + ConfigGate)**:

1. **Prefetch in `main.tsx`, prima di `createRoot`.** Avviare la richiesta del config con
   `queryClient.ensureQueryData({ queryKey: configKeys.all, queryFn: fetchConfig, staleTime: Infinity })`
   **prima** di montare React. Questo garantisce, a livello di sequenza di rete, che `/api/config`
   parta come prima richiesta in assoluto: nessun componente React (quindi nessun `AuthProvider`,
   quindi nessun `me`) è ancora montato quando la richiesta viene emessa. Il `queryClient` è il
   medesimo singleton importato da `@/app/query-client`, condiviso con `QueryClientProvider`, così
   il risultato del prefetch popola la cache che il gate poi legge (nessuna doppia fetch).

   Il prefetch **non blocca** `createRoot` (no `await` che ritarda il primo paint): si renderizza
   subito, e la gestione di loading/errore è demandata al gate (punto 2). La promise può essere
   lasciata fluire (`void`), perché lo stato autorevole vive nella query cache letta dal gate.

2. **`<ConfigGate>` come guscio di gating del render**, collocato in `App.tsx` **sopra
   `AuthProvider`** e **sotto `QueryClientProvider`**. Usa `useConfig()` (hook già esistente) e:
   - mentre `isPending` → mostra lo **splash di bootstrap full-screen** (`FullScreenLoader`),
     non monta i figli;
   - su `isError` → mostra una **schermata di errore full-screen** con bottone **Riprova**
     (`refetch`), non monta i figli;
   - su `isSuccess` → renderizza `children` (`AuthProvider` + resto dell'app).

   Finché `ConfigGate` non è in stato success, `AuthProvider` non è montato → `me` non parte →
   nessuna altra API parte.

Struttura risultante di `App.tsx`:

```
QueryClientProvider
  └─ ConfigGate                 // gate: legge useConfig, blocca i figli finché non pronto
       └─ AuthProvider          // monta solo a config caricato → me parte dopo config
            └─ TooltipProvider
                 └─ Suspense
                      └─ RouterProvider
```

### Error handling della dipendenza obbligatoria

Il config è una dipendenza **hard**: se fallisce, l'app non può partire. `ConfigGate` su `isError`
mostra una schermata di errore full-screen dedicata (titolo + messaggio + bottone **Riprova** che
chiama `refetch()`), **senza montare nulla** del resto dell'app. La query del config **sovrascrive**
il `retry: 1` globale con un retry più tollerante verso i fallimenti transitori
(`retry: 3` con backoff esponenziale) direttamente nell'hook `useConfig`; esaurito il retry
automatico, il bottone **Riprova** dà all'utente il controllo esplicito. Testo localizzato via i18n.

### Loading state: splash di bootstrap come eccezione legittima a Skeleton First

`coding-standards.md → Loading States (Skeleton First)` impone skeleton "per ogni vista che
**carica dati**", con skeleton che **rispecchia la forma del contenuto** che sostituisce. Durante
il bootstrap iniziale **non esiste ancora alcun layout** (header, sidebar, tabella, form) di cui
riprodurre la forma: si sta decidendo se l'app può montare. Lo skeleton presuppone una shape nota;
qui la shape non esiste. Lo splash full-screen di boot è quindi un'**eccezione legittima e
circoscritta** alla regola, non una sua violazione: vale solo per il gate di bootstrap, una sola
volta per sessione, prima di qualsiasi layout. La regola Skeleton First **resta pienamente
vincolante** per tutte le viste applicative successive (liste, dettagli, form). Si riusa
`FullScreenLoader` esistente.

### Garanzia "prima di ogni API"

- **Ordine di emissione**: il prefetch in `main.tsx` emette `/api/config` prima che React monti,
  quindi prima che esista qualsiasi componente capace di emettere un'altra chiamata.
- **Ordine di mount**: `ConfigGate` non monta `AuthProvider` finché il config non è in success;
  `me` ha `enabled: Boolean(token)` ma il suo `useQuery` non viene nemmeno registrato finché
  `AuthProvider` non monta. Stessa garanzia per ogni rotta/feature (montano sotto il router, sotto
  `AuthProvider`).
- **Dopo il bootstrap**: config in cache con `staleTime: Infinity` → le chiamate successive
  (`me`, liste, ecc.) procedono normalmente senza re-fetch del config.

### Locale (config vs Accept-Language)

Le label degli enum sono localizzate dal backend in base alla richiesta. L'`apiClient` attuale
**non invia un header `Accept-Language` esplicito**: il config viene quindi richiesto con il locale
di default del browser/backend, prima che `AuthProvider` applichi `applyLocale(user.locale)`
post-login. Conseguenza: dopo il login, se il locale dell'utente differisce da quello con cui il
config è stato caricato, le label restano nel locale iniziale (config in cache con
`staleTime: Infinity`, non rifetchato).

Decisione: **mitigazione minima in scope, soluzione completa fuori scope per ora.**
- In scope ora: il pattern di bootstrap, gate ed error UX (questo ADR). Il config viene caricato
  una volta con il locale di default — accettabile per l'MVP perché le label sono presentazione,
  non dati critici.
- Fuori scope (debito tracciato, vedi Consequences): allineare il locale del config a quello
  dell'utente — strategia raccomandata quando affrontata: invalidare/rifetchare `configKeys.all`
  in `applyLocale`/al cambio locale, e/o far inviare all'`apiClient` un header `Accept-Language`
  coerente con `i18n.language`. Richiede un ADR/handoff dedicato.

---

## Alternatives Considered

- **(a) Solo prefetch in `main.tsx` (`await ensureQueryData` prima di `createRoot`)** — garantisce
  "literally first" ma sposta loading ed errore **fuori da React**: l'error UX andrebbe gestita con
  DOM imperativo (o un secondo render), il retry diventa codice fuori dall'albero dei componenti,
  l'i18n del messaggio d'errore è scomoda fuori dal provider React. Scartata come soluzione unica:
  meno manutenibile e duplica la gestione di stato già nativa in TanStack Query. Ne **riusiamo però
  il prefetch** come avvio anticipato della chiamata.

- **(b) Solo `<ConfigGate>` con `useConfig`, senza prefetch** — semplice e idiomatico, ma la
  richiesta parte solo **dopo** il primo render/mount del gate. Garantisce comunque l'ordine
  rispetto al `me` (il gate è sopra `AuthProvider`), ma non l'invariante forte "prima del mount
  React". Margine teorico più stretto e meno difendibile rispetto al requisito "prima di ogni API".
  Scartata come soluzione unica: meno robusta sulla garanzia d'ordine.

- **(c) Ibrido prefetch + gate (SCELTA)** — il prefetch avvia la chiamata prima del mount React
  ("literally first"); il gate gestisce loading/error/success **dentro** React con gli strumenti
  nativi (TanStack Query, i18n, retry, refetch). Massima robustezza sull'ordine + massima
  manutenibilità dell'error/loading UX, senza doppia fetch (cache condivisa via singleton
  `queryClient`).

---

## Trade-offs

- **Vantaggi**: garanzia d'ordine forte (prefetch pre-mount); error/loading UX idiomatica in React
  (retry, refetch, i18n); riuso totale della feature `config` e di `FullScreenLoader` esistenti;
  separazione netta delle responsabilità (avvio chiamata vs UI di gate).
- **Svantaggi**: la logica di bootstrap è distribuita su due punti (`main.tsx` + `ConfigGate`) —
  va resa esplicita e documentata (questo ADR) per non diventare logica nascosta; va mantenuta la
  coerenza di `queryKey`/`staleTime`/`retry` tra prefetch e `useConfig` (mitigato riusando
  `configKeys.all` e `fetchConfig`).
- **Cosa rinunciamo**: a uno splash "skeleton-shaped" durante il boot (accettato come eccezione
  motivata); a localizzare il config con il locale utente al primo caricamento (debito tracciato).

---

## Consequences

- **Positivi**: `/api/config` è garantito come prima chiamata; nessuna feature parte senza config;
  comportamento di bootstrap esplicito e testabile; nessuna doppia fetch.
- **Negativi**: punto di bootstrap leggermente più articolato (due luoghi).
- **Debito tecnico tracciato**: allineamento locale config ↔ locale utente (invalidate/refetch del
  config al cambio locale e/o `Accept-Language` coerente). Da affrontare con ADR/handoff dedicato
  quando il multi-locale post-login diventa requisito attivo.

---

## Affected Agents

- Frontend Agent (owner dell'implementazione)
- Reviewer Agent, QA Agent (validazione)

---

## Risks

- Disallineamento `queryKey`/opzioni tra prefetch e `useConfig` → doppia fetch. Mitigazione: riuso
  di `configKeys.all`, `fetchConfig`, `staleTime: Infinity`.
- StrictMode in dev può montare due volte `ConfigGate` (doppio render dei suoi effetti): non emette
  doppia fetch perché lo stato vive nella query cache (dedup TanStack Query).
- Label config nel locale di default fino a refetch (debito locale sopra).

---

## References

- `docs/adr/0008-public-bootstrap-config-endpoint.md` (endpoint `/api/config`)
- `docs/adr/0003-ag-grid-table-loading-skeleton.md` (Skeleton First di riferimento)
- `standards/coding-standards.md` → Loading States (Skeleton First)
- `standards/architecture.md` → Frontend Architecture, API Layer, State Management
- `frontend/src/features/config/*`, `frontend/src/App.tsx`, `frontend/src/main.tsx`
