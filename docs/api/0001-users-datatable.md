# API Contract — Users DataTable (AG Grid SSRM)

> **SUPERSEDED by `docs/api/0002-generic-tables.md`.** This users-specific
> contract is replaced by the generic domain-driven table contract
> (`GET/POST /api/tables/{domain}/columns|rows`). Kept as historical record; do
> not implement against it. See ADR-0002.
>
> Contratto API del pattern Backend-driven DataTable. Collegato ad
> `docs/adr/0001-backend-driven-datatable-ag-grid-ssrm.md`.
> Owner: Backend (implementazione) / Architect (contratto).
> Confine backend ↔ frontend: questo documento è la fonte di verità condivisa.

---

## Overview

Due endpoint, modellati sul pattern navigation backend-driven:

| Scopo | Method + Path | Permesso | Body | Risposta |
|---|---|---|---|---|
| Config tabella | `GET /api/users/table/config` | `users.viewAny` | — | `ok()` envelope, `data` = config |
| Dati righe (SSRM) | `POST /api/users/table/rows` | `users.viewAny` | payload SSRM | `paginatedResponse()` envelope |

Entrambi dietro `auth:sanctum`. Autorizzazione server-side obbligatoria:
`$this->authorize('viewAny', User::class)` su entrambi.

---

## 1. Config endpoint — `GET /api/users/table/config`

Restituisce lo schema della tabella per l'utente corrente. Lo schema è dichiarato
in `config/tables/users.php` e risolto da `UserTableService` (filtra colonne/azioni
non permesse in funzione dei permessi, come `NavigationService` per la navigation).

Wrappato in `ok()` → `{ success, message, data }`. `data`:

```json
{
  "resource": "users",
  "columns": [
    { "id": "id",         "label": "users.columns.id",         "type": "number",   "visible": false, "sortable": true,  "filterable": false },
    { "id": "name",       "label": "users.columns.name",       "type": "text",     "visible": true,  "sortable": true,  "filterable": true  },
    { "id": "email",      "label": "users.columns.email",      "type": "text",     "visible": true,  "sortable": true,  "filterable": true  },
    { "id": "roles",      "label": "users.columns.roles",      "type": "tags",     "visible": true,  "sortable": false, "filterable": true  },
    { "id": "locale",     "label": "users.columns.locale",     "type": "enum",     "visible": false, "sortable": true,  "filterable": true,  "options": ["en","it"] },
    { "id": "created_at", "label": "users.columns.created_at", "type": "datetime", "visible": true,  "sortable": true,  "filterable": true  }
  ],
  "filters": [
    { "columnId": "name",       "type": "text" },
    { "columnId": "email",      "type": "text" },
    { "columnId": "roles",      "type": "set",  "optionsSource": "roles" },
    { "columnId": "locale",     "type": "set",  "options": ["en","it"] },
    { "columnId": "created_at", "type": "date" }
  ],
  "actions": [
    { "key": "view",   "label": "actions.view",   "icon": "eye",    "type": "link",    "confirm": false },
    { "key": "edit",   "label": "actions.edit",   "icon": "pencil", "type": "link",    "confirm": false },
    { "key": "delete", "label": "actions.delete", "icon": "trash",  "type": "danger",  "confirm": true  }
  ],
  "defaultSort":       [ { "columnId": "created_at", "direction": "desc" } ],
  "defaultPagination": { "limit": 25 }
}
```

### Field semantics

- **`columns[].id`** — chiave stabile = nome colonna DB reale (o campo derivato
  `roles`). Il frontend può mappare `id` → renderer custom (badge, link).
- **`columns[].type`** — `text | number | datetime | enum | tags`. Guida il
  rendering/formatter di default lato frontend.
- **`columns[].visible`** — visibilità di default (la colonna esiste ma può essere
  nascosta inizialmente).
- **`columns[].sortable` / `filterable`** — **whitelist server-side**: il data
  endpoint accetta sort/filtri SOLO su colonne marcate `true` qui.
- **`filters[]`** — catalogo filtri disponibili e loro tipo AG Grid
  (`text | number | date | set`).
- **`actions[]`** — **catalogo azioni** (come renderizzare). `type`:
  `link | action | danger`. `confirm`: richiede conferma UI. Le 3 standard
  (`view/edit/delete`) sono estendibili con chiavi custom aggiunte al config.
- **`defaultSort` / `defaultPagination`** — stato iniziale del grid.

> Colonne basate solo sui campi reali di `users`: `id, name, email, locale,
> email_verified_at, created_at` + `roles` (derivato Spatie). Nessun campo
> inventato. `password`/`remember_token` mai esposti (sono `hidden`).

---

## 2. Data endpoint (SSRM) — `POST /api/users/table/rows`

### Request (payload AG Grid SSRM)

AG Grid SSRM, tramite il datasource, invia un `IServerSideGetRowsRequest`. Il body
accettato (validato da `UserTableRowsRequest`):

```json
{
  "startRow": 0,
  "endRow": 25,
  "sortModel": [
    { "colId": "created_at", "sort": "desc" }
  ],
  "filterModel": {
    "name":  { "filterType": "text", "type": "contains", "filter": "ann" },
    "roles": { "filterType": "set",  "values": ["admin"] }
  }
}
```

Validazione (FormRequest):
- `startRow`: `integer|min:0`.
- `endRow`: `integer|gt:startRow`; `endRow - startRow` ≤ `MAX_LIMIT` (100).
- `sortModel[].colId`: `in:` colonne `sortable` del config. `sort`: `in:asc,desc`.
- `filterModel` keys: `in:` colonne `filterable` del config. Valori validati per tipo.
- Qualsiasi `colId`/chiave non whitelisted → `422` (mai passata grezza alla query).

### Mapping → envelope esistente (riuso `paginatedResponse`)

| SSRM request | Server |
|---|---|
| `startRow` | `offset` |
| `endRow - startRow` | `limit` (cap a 100) |
| `sortModel` | `ORDER BY` whitelisted |
| `filterModel` | `WHERE` whitelisted |

Il `UserTableService` esegue `with('roles')` (no N+1), applica filtri/sort,
pagina, e restituisce `items` (via `UserTableRowResource`) + `total`.

### Response (envelope `paginatedResponse()`)

```json
{
  "items": [
    {
      "id": 12,
      "name": "Anna Rossi",
      "email": "anna@example.com",
      "roles": ["admin"],
      "locale": "it",
      "created_at": "2026-05-30T09:12:00+00:00",
      "actions": ["view", "edit", "delete"]
    },
    {
      "id": 13,
      "name": "Marco Bianchi",
      "email": "marco@example.com",
      "roles": ["editor"],
      "locale": "en",
      "created_at": "2026-05-28T14:03:00+00:00",
      "actions": ["view"]
    }
  ],
  "export_link": null,
  "pagination": { "total": 137, "offset": 0, "limit": 25, "total_pages": 6 }
}
```

### Mapping risposta → AG Grid (datasource frontend)

```text
rowData  = response.items
rowCount = response.pagination.total
// lastRow derivabile: se startRow + items.length >= total → lastRow = total
params.success({ rowData, rowCount })
```

---

## 3. Row actions

- Ogni riga espone **`actions: string[]`** = chiavi azione **consentite per QUELLA
  riga**, calcolate server-side via Policy:
  - `view`   ⇐ `UserPolicy::view($user, $row)`   (`users.view`)
  - `edit`   ⇐ `UserPolicy::update($user, $row)`  (`users.update`)
  - `delete` ⇐ `UserPolicy::delete($user, $row)`  (`users.delete`)
- Il **catalogo** (`config.actions[]`) descrive come renderizzare ogni chiave
  (label/icon/type/confirm). Il frontend renderizza, per riga, solo le azioni la
  cui `key` è in `row.actions`, usando i metadati del catalogo.
- Estendibilità: una chiave custom (es. `"impersonate"`) si aggiunge al catalogo
  config + alla logica server-side che la include in `row.actions` quando permessa.
- **Le azioni invocate da UI colpiscono endpoint propri** (es.
  `DELETE /api/users/{user}`), che **ri-autorizzano** server-side. `row.actions` è
  UX/affordance, non autorizzazione finale.

---

## 4. Authorization model

| Livello | Controllo server-side |
|---|---|
| Accesso tabella (config + rows) | `users.viewAny` (`UserPolicy::viewAny`) |
| Azione riga `view` | `users.view` (`UserPolicy::view`) |
| Azione riga `edit` | `users.update` (`UserPolicy::update`) |
| Azione riga `delete` | `users.delete` (`UserPolicy::delete`) |

Frontend: `Can permission="users.viewAny"` gate alla pagina; il gating delle azioni
è già implicito in `row.actions`. Nessun dato dal frontend è attendibile: filtri,
sort e azioni sono sempre ri-validati/ri-autorizzati lato server.

---

## 5. Routes (da aggiungere a `routes/api.php`)

```php
Route::middleware('auth:sanctum')->group(function () {
    Route::get('users/table/config', [UserTableController::class, 'config']);
    Route::post('users/table/rows',  [UserTableController::class, 'rows']);
});
```
