# Architecture Standards

## Purpose

Questo documento definisce gli standard architetturali da seguire in tutti i progetti dell'organizzazione.

L'obiettivo è garantire:

- Manutenibilità
- Scalabilità
- Coerenza
- Sicurezza
- Facilità di onboarding per sviluppatori e agenti AI

---

# Technology Stack

## Frontend

- React
- TypeScript
- Vite
- React Router
- TanStack Query
- React Hook Form
- Zod
- Tailwind CSS
- shadcn/ui
- Animate UI
- shadcn/ui Chart
- Recharts
- AG Grid
- Axios

## Backend

- Laravel
- PHP
- MySQL
- Laravel Sanctum
- Spatie Activitylog
- Spatie Permission
- Queue Jobs
- Events & Listeners
- Notifications
- Policies

## Infrastructure

- Docker (opzionale)
- Nginx
- Ploi
- GitHub
- GitHub Actions

---

# Repository Layout

Il progetto è organizzato come monorepo con tre rami separati e non sovrapposti:

```
repo/
├── backend/    → applicazione Laravel (PHP)
├── frontend/   → applicazione React (TypeScript + Vite)
└── docs/       → output di documentazione (ADR, feature spec, API docs, runbook)
```

Regole per gli agenti:

- Tutto il codice server-side vive esclusivamente in `backend/`.
- Tutto il codice client-side vive esclusivamente in `frontend/`.
- Backend e frontend restano isolati: ciascuno ha il proprio tooling, le proprie dipendenze e i propri test. Nessuna dipendenza diretta tra i due al di fuori del contratto API.
- Il confine tra backend e frontend è il contratto API; la sua documentazione vive in `docs/`.
- Nessun agente deve mischiare codice backend e frontend nella stessa cartella o nello stesso file.

I file di bootstrap (`AGENTS.md`, `CLAUDE.md`, `CODEX.md`) e l'Agent OS (`standards/`, `agents/`, `templates/`) restano alla root del repository.

---

# High Level Architecture

Frontend (React)
API Layer
Backend (Laravel)
Database (MySQL)

---

# Backend Architecture

Pattern: **Laravel Layered Service Architecture**

Request
FormRequest
Controller
Service
Model
Database

## Controllers

I Controller devono essere il più possibile sottili.

Consentito:

- Validazione richiesta
- Autorizzazione
- Chiamata ai Service
- Restituzione risposta

Non consentito:

- Business Logic
- Query complesse
- Elaborazioni dati

---

## Services

Tutta la business logic deve vivere nei Service.

Esempi:

- Creazione entità
- Aggiornamento entità
- Workflow applicativi
- Notifiche
- Elaborazioni

Il pattern architetturale backend di riferimento per lo starter e per le nuove
feature Laravel e quindi una **Layered Service Architecture**:

- `FormRequest` come boundary di validazione dell'input
- `Controller` sottile come boundary HTTP/autorizzazione
- `Service` come owner della business logic e dei workflow applicativi
- `Model` come rappresentazione del dominio e delle relazioni
- `DTO` espliciti per i dati che attraversano i layer

---

## Data Transfer Objects (obbligatorio)

I dati che **attraversano un confine di layer** (Controller ↔ Service, Service ↔ Service, FormRequest → Controller → Service) devono viaggiare dentro un **DTO dichiarato**, non dentro array associativi "magici".

Sono **vietati gli array magici volanti**: array associativi a chiave-stringa usati come valore di ritorno o come parametro di business, la cui forma è descritta solo da un `@param`/`@return array<string, mixed>` o `array{...}` invece che da un tipo reale. Costringono chi legge a indovinare le chiavi, non danno autocompletamento né type-safety e si rompono in silenzio quando la forma cambia.

```php
// ❌ Vietato: forma implicita, chiavi indovinate, nessuna garanzia
public function rows(...): array
{
    return ['items' => $items, 'total' => $total, 'offset' => $offset, 'limit' => $limit];
}
$result = $service->rows(...);
$total = $result['total']; // typo silenzioso, nessun autocomplete

// ✅ Obbligatorio: contratto esplicito e leggibile
public function rows(...): RowsResult
{
    return new RowsResult(items: $items, total: $total, offset: $offset, limit: $limit);
}
$result = $service->rows(...);
$total = $result->total; // tipizzato, autocompletato, refactor-safe
```

Regole:

- I DTO vivono in `backend/app/DataObjects/` (namespace `App\DataObjects`), raggruppati per dominio (es. `App\DataObjects\Table\RowsResult`).
- Sono classi `final readonly` con proprietà `public readonly` tipizzate e promosse nel costruttore. Sono **immutabili**: nessun setter, nessuna logica di business (solo, al più, semplici `named constructor`/`fromArray()` per costruirli ai confini del sistema).
- Un metodo che restituisce **più di un valore** correlato deve restituire un DTO, non un array.
- Un metodo che riceve **più campi correlati** provenienti dall'input deve riceverli come DTO: la `FormRequest` costruisce il DTO (es. `columnsState(): array<int, ColumnState>`) e il Service consuma oggetti tipizzati, mai `$input['campo'] ?? null`.
- **Nessuna nuova dipendenza** per questo: PHP nativo (`readonly` properties + constructor promotion) è sufficiente. Non introdurre librerie DTO senza una decisione architetturale esplicita (vedi `standards/decision-making.md`).

Eccezioni — restano array (coerenti con "Tabelle di configurazione / Mapping statici" in `standards/coding-standards.md`):

- I **cataloghi dichiarativi** statici delle definizioni (`columns()`, `filters()`, `actions()`): sono configurazione, non dati che fluiscono tra layer.
- Il **payload finale serializzato** verso il client (corpo JSON di una API Resource / config inviata al frontend): è il contratto di serializzazione, non un DTO interno.
- Strutture intrinsecamente **dinamiche e di forma arbitraria** provenienti da terze parti (es. il `filterModel`/`sortModel` grezzo di AG Grid prima della normalizzazione).
- Array di **input passati verbatim a un'API del framework** che richiede un array (mass assignment `Model::update()`, query builder, password broker): qui l'array È il formato del framework, non un valore di business che attraversa un confine. Vale solo se l'array viene inoltrato così com'è, senza essere letto campo per campo nella business logic.

Quando l'array è ammesso per eccezione, deve comunque avere un `@param`/`@return` con `array{...}` o `@phpstan-type` che ne dichiari la forma.

---

## Models

I Model rappresentano il dominio.

Consentito:

- Relazioni
- Scope
- Accessor
- Mutator

Non consentito:

- Business Logic complessa

### Base Model (obbligatorio)

Ogni model di dominio deve estendere `App\Models\Abstracts\BaseModel` invece di `Illuminate\Database\Eloquent\Model`.

```php
use App\Models\Abstracts\BaseModel;

class Example extends BaseModel
{
    use LogsModelActivity;
}
```

`BaseModel` è il punto unico dove centralizzare comportamenti trasversali a tutti i model.

Eccezione: i model che devono estendere una classe base del framework (es. `User extends Authenticatable`) non passano da `BaseModel`, ma restano soggetti alle stesse regole (trait standard, activity log).

### Mass Assignment / Fillable (obbligatorio)

Ogni model deve dichiarare **esplicitamente** la whitelist degli attributi assegnabili in massa, tramite la proprietà `protected $fillable` (o l'attributo `#[Fillable([...])]`).

```php
class Example extends BaseModel
{
    protected $fillable = ['name', 'email'];
}
```

Regole:

- È **vietato** lasciare il model senza `$fillable`, così come usare `$guarded = []` (esporrebbe ogni colonna al mass assignment).
- `$fillable` è la fonte di verità del mass assignment ed è usata anche dal trait `LogsModelActivity` (`logFillable()`): senza, l'activity log non registra nulla.
- I dati sensibili (es. password) vanno comunque tenuti tra gli `hidden`, anche se presenti in `$fillable`.

### Activity Log (obbligatorio)

Ogni nuovo model deve essere collegato all'**activity log** (Spatie Activitylog) usando il trait standard `App\Models\Concerns\LogsModelActivity`.

```php
use App\Models\Concerns\LogsModelActivity;

class Example extends Model
{
    use LogsModelActivity;
}
```

Il trait applica i default dell'organizzazione:

- logga gli attributi `fillable`,
- esclude sempre gli attributi `hidden` (password, token, secret),
- registra solo i campi effettivamente modificati (`logOnlyDirty`),
- non crea log vuoti,
- usa il nome della tabella come `log_name`.

Regole:

- Per comportamenti diversi, sovrascrivere `getActivitylogOptions()` nel singolo model (non duplicare la logica del trait).
- I dati sensibili non devono mai finire nel log: tenerli tra gli `hidden` del model.
- I model che rappresentano un **attore** (es. `User`) aggiungono anche il trait `Spatie\Activitylog\Traits\CausesActivity` per esporre le attività generate (`$user->actions`).

Eccezione — **dati di riferimento / seed**: i model che rappresentano tabelle di lookup statiche, popolate via seed e non modificate dagli utenti (es. `Country`, `State`, `City`), sono **esentati** dall'activity log. Loggarne le modifiche produrrebbe solo rumore. Questi model estendono comunque `BaseModel` ma non usano `LogsModelActivity`.

### Policies & Permissions (obbligatorio)

Quando si crea un model di dominio si deve creare anche la sua **Policy**, che estende `App\Policies\Abstracts\BasePolicy` e dichiara il set di **permessi standard** CRUD.

```php
namespace App\Policies;

use App\Policies\Abstracts\BasePolicy;

class ExamplePolicy extends BasePolicy
{
    protected function resource(): string
    {
        return 'examples'; // prefisso permessi, di norma il nome tabella
    }
}
```

`BasePolicy` mappa ogni abilità sul permesso `{resource}.{ability}`:

| Metodo Policy | Permesso |
|---|---|
| `viewAny` | `examples.viewAny` |
| `view` | `examples.view` |
| `create` | `examples.create` |
| `update` | `examples.update` |
| `delete` | `examples.delete` |

Regole:

- Naming permessi: `{resource}.{ability}`, con `{resource}` = nome tabella (snake plurale), coerente con la navigation.
- I permessi standard vengono creati/registrati dal command **`php artisan permissions:sync`** (che li raccoglie sia dalla navigation sia dalle Policy).
- L'**autorizzazione lato server è obbligatoria** su ogni endpoint (`$this->authorize(...)`, `Gate`, o middleware `can:`), indipendentemente da ciò che il frontend mostra o nasconde.
- Per regole non-standard (es. ownership), sovrascrivere il singolo metodo nella Policy concreta.

Eccezione — **dati di riferimento / seed** (`Country`, `State`, `City`): essendo read-only e non gestiti da UI, non richiedono una Policy CRUD completa.

### Factories (obbligatorio)

Quando si crea un model di dominio si deve creare **sempre** anche la sua **Factory** (`Database\Factories`). Questa regola è obbligatoria e non negoziabile.

```php
namespace Database\Factories;

use App\Models\Example;
use Illuminate\Database\Eloquent\Factories\Factory;

class ExampleFactory extends Factory
{
    protected $model = Example::class;

    public function definition(): array
    {
        return [
            // valori di default realistici per ogni campo $fillable
        ];
    }
}
```

Regole:

- Ogni model usa il trait `Illuminate\Database\Eloquent\Factories\HasFactory` e dispone di una Factory collegata (convenzione `App\Models\Example` → `Database\Factories\ExampleFactory`).
- La Factory deve produrre uno **stato di default valido**: ogni attributo richiesto è popolato con dati realistici (via Faker), così che `Example::factory()->create()` superi le validazioni senze override manuali.
- Le varianti di stato (es. record disabilitato, ruolo specifico) si esprimono con **factory states** dedicati, non duplicando setup nei test.
- Le relazioni si modellano con factory di relazione (`for()`, `has()`), non con id hardcoded.
- La Factory è il presupposto del testing: i test (TDD) e i seeder devono costruire i dati di dominio tramite Factory, mai inserendo righe a mano. Senza Factory non è possibile raggiungere la coverage minima richiesta (vedi `standards/coding-standards.md` → Testing → Coverage).

Eccezione — **dati di riferimento / seed** (`Country`, `State`, `City`): popolati da seed deterministici, possono non avere una Factory completa se non vengono mai costruiti nei test; se un test ne ha bisogno, la Factory va aggiunta.

---

## Database

Regole:

- Migrazioni obbligatorie
- Foreign Key ove possibile
- Soft Delete solo quando necessario
- Indici sui campi frequentemente utilizzati nei filtri e nelle ricerche
- Nessuna modifica manuale dello schema in produzione

---

# Frontend Architecture

Page
↓
Feature
↓
Component
↓
UI Component

## Pages

Responsabili della composizione della schermata.

Non devono contenere business logic.

---

## Features

Contengono:

- Query
- Mutation
- Form
- Gestione stato della feature
- Orchestrazione di chart e motion component quando richiesti dalla schermata

### Entity Detail Cards (Fresh On Open)

L'apertura della scheda di un'entità (view/edit) deve sempre rifare il fetch del
`show` prima di mostrare i dati: la riga di griglia è solo uno snapshot. Usare
l'hook condiviso `useEntityDetail` e montare il form di edit solo dopo l'arrivo
dei dati freschi. Vedi `standards/coding-standards.md` → "Entity Detail Cards
(Fresh On Open)".

---

## Components

Devono essere:

- Riutilizzabili
- Piccoli
- Testabili
- Isolati

Per animazioni e visualizzazioni dati nello starter frontend:

- usare **Animate UI** per motion primitives e componenti animati coerenti con
  `shadcn/ui`
- usare **shadcn/ui Chart** come layer di composizione dei grafici
- usare **Recharts** come motore sottostante dei chart, evitando wrapper custom
  aggiuntivi se non strettamente necessari

---

## API Layer

Tutte le chiamate HTTP devono passare da un layer dedicato.

Non effettuare chiamate API direttamente all'interno dei componenti UI.

---

# State Management

## Server State

Utilizzare:

- TanStack Query

## Client State

Utilizzare:

- useState
- useReducer
- Context

Evitare store globali non necessari.

---

# Security

- Validazione frontend e backend
- Authorization tramite Policies
- Nessun dato proveniente dal frontend deve essere considerato attendibile
- Sanitizzazione degli input
- Controlli di autorizzazione lato server obbligatori

---

# Testing

## Backend

- Pest
- Feature Test
- Unit Test

## Frontend

- Vitest
- React Testing Library

Ogni funzionalità critica deve essere coperta da test.

---

# Architectural Principles

- Preferire semplicità a complessità
- Preferire leggibilità a ottimizzazioni premature
- Evitare duplicazioni
- Separare le responsabilità
- Mantenere basso il debito tecnico
- Favorire componenti riutilizzabili
- Documentare le decisioni architetturali rilevanti
- Scrivere codice pensato per essere mantenuto negli anni
