# Architecture Decision Record

## ADR ID

0008

## Title

Public bootstrap endpoint `GET /api/config` aggregating form enums (replaces per-enum `GET /api/enums/{enum}`)

## Status

ACCEPTED

## Date

2026-06-15

---

## Context

The frontend needs the presentation metadata of the domain enums (the
`value/label/color/icon/is_default/hidden_on_form` contract produced by the
`HasMeta` reader, ADR 0007) to render selects and badges in its forms.

The current shape is a per-enum endpoint, `GET /api/enums/{enum}`
(`EnumController`, inside the `auth:sanctum` group, ADR 0007). It forces the
client to issue one request per enum (four enums = four round-trips) and offers
no place for other bootstrap configuration the app will need before the first
screen renders.

The user has decided (not up for re-discussion) to replace it with a single
**public** bootstrap endpoint, `GET /api/config`, that the frontend calls
**first**, before every other call (including login). It must return all the
form enums grouped together and be structured to grow with future config
sections.

Relevant constraints from the codebase, verified by reading:

- **Response envelope**: `BaseApiController::ok($data)` →
  `{ success: true, message: "OK", data: <...> }`. Reused as-is.
- **Existing, reusable** (ADR 0007): trait `App\Enums\Concerns\HasMeta`
  (`options(): array<int, EnumMeta>`, filtering of `hiddenOnForm` left to the
  caller), DTO `App\DataObjects\Enums\EnumMeta` (`toArray()` → stable snake_case
  contract), and four decorated enums: `PersonalDataTypeEnum`,
  `PersonalTitleEnum`, `ContactTypeEnum`, `NotificationLevelEnum`. These are the
  building blocks and are reused unchanged.
- **Server-side allowlist precedent**: the project already expresses
  "which things are exposed" as explicit `config/*.php` declarative catalogues
  consumed by a Service (`config/navigation.php` → `NavigationService`,
  `config/tables.php` → `TableService`/registry, `config/personal_data.php`,
  `config/attachments.php`). There is no user input in the enum case: the list
  is a fixed, server-side allowlist.
- **Serialization convention towards the client is snake_case** everywhere
  (`EnumMeta::toArray()`, all `*Resource`s). There is no camelCase serialization
  precedent on this backend.
- **Locale**: `config('app.locale')` default `en`, `fallback_locale` `en`,
  translations in `lang/en` + `lang/it(.json)`. `User implements
  HasLocalePreference` (`preferredLocale()` → `users.locale`). There is **no**
  `SetLocale` middleware: today the app locale is only the configured default
  (tests force it via `App::setLocale()`). `HasMeta::label()` resolves `__()`
  on **every** read (never cached), so labels follow the current request locale.
- **Routing**: public routes already exist outside `auth:sanctum` under the
  `auth` prefix (`login`, and the throttled `forgot-password`/`reset-password`).
  Public, abuse-prone routes are rate-limited with `throttle:6,1`.

---

## Decision

Introduce a single **public** (no `auth:sanctum`) endpoint **`GET /api/config`**
that returns, in the standard `ok()` envelope, an **extensible** config object
whose first and only current section is `enums` — the four decorated domain
enums, each as an array of `EnumMeta` (snake_case), with `hidden_on_form` cases
filtered out (these are form enums).

### 1. Exact JSON contract

```jsonc
// GET /api/config  → 200
{
  "success": true,
  "message": "OK",
  "data": {
    "enums": {
      "personal_data_type": [
        { "value": "...", "label": "...", "color": "...", "icon": "...", "is_default": false, "hidden_on_form": false }
      ],
      "personal_title":    [ /* EnumMeta[] */ ],
      "contact_type":      [ /* EnumMeta[] */ ],
      "notification_level":[ /* EnumMeta[] */ ]
    }
    // future sections live here as sibling keys, e.g.:
    // "locales":  { ... },
    // "features": { ... },
    // "app":      { "version": "...", "default_locale": "en" }
  }
}
```

- `data` is an **object** (not an array) so future config sections are added as
  sibling keys without breaking the contract. `enums` is itself an object keyed
  by enum name, so enums can be added/removed without touching the others.
- **Enum group keys are `snake_case`** (`personal_data_type`, not
  `personalDataType`): this matches every existing client-facing key on this
  backend (`EnumMeta::toArray()`, all Resources). One consistent casing across
  the whole API; the frontend maps to its own naming at its boundary. (Rejected
  camelCase — see Alternatives.)
- Each enum value is the existing `EnumMeta` snake_case array, **unchanged** from
  ADR 0007. Cases flagged `#[HiddenOnForm(true)]` are filtered out.

### 2. Where the logic lives — `ConfigService` + a config allowlist

- A new **`App\Services\ConfigService`** assembles the payload (thin controller,
  business logic in a Service — `architecture.md`). Controller
  `App\Http\Controllers\Config\ConfigController::index()` only calls the service
  and returns `ok()`.
- The **registry of exposed enums** is an explicit allowlist in a new
  **`config/config.php`** file (consistent with `config/navigation.php`,
  `config/tables.php`, etc.): a map `enum group key → enum FQCN`.

  ```php
  // config/config.php
  return [
      // PUBLIC, pre-login bootstrap. Only NON-SENSITIVE presentation metadata.
      // See ADR 0008. Do NOT add anything sensitive here without Security review.
      'form_enums' => [
          'personal_data_type' => \App\Enums\PersonalDataTypeEnum::class,
          'personal_title'     => \App\Enums\PersonalTitleEnum::class,
          'contact_type'       => \App\Enums\ContactTypeEnum::class,
          'notification_level' => \App\Enums\NotificationLevelEnum::class,
      ],
  ];
  ```

  `ConfigService` iterates this map, calls `$class::options()`, rejects
  `hiddenOnForm`, maps to `->toArray()`. Adding/removing an exposed enum is a
  one-line, reviewable config change — never derived from request input, never
  reflection over a client-supplied class-string.

- **No aggregate DTO is required.** The `data` object is the *final serialized
  payload to the client*, which `architecture.md` explicitly exempts from the
  "no magic arrays" rule ("Il payload finale serializzato verso il client … è il
  contratto di serializzazione, non un DTO interno"). The values inside it are
  already typed `EnumMeta` DTOs until the terminal `->toArray()`. The `enums`
  map produced by the service is declared with a precise
  `@return array<string, array<int, array{...}>>` PHPDoc. If a future section
  introduces cross-layer business data, that section gets its own DTO; the enum
  section does not need one.

### 3. Public-exposure safety

- The endpoint is public **by user decision**. It therefore exposes **only
  non-sensitive presentation metadata** of the four form enums
  (`value/label/color/icon/is_default/hidden_on_form`). These four enums are
  declared **safe for public exposure**: they reveal nothing about authorization,
  internal logic, data volumes, or user data — only UI vocabulary the login/form
  screens already need.
- **Hard rule for the future**: nothing sensitive may be added to
  `config/config.php` / `/api/config` without an explicit Security review. The
  config file carries this warning in a header comment; this ADR is the
  governing reference.
- **Rate limiting**: the route is wrapped in `throttle:30,1` (public,
  unauthenticated surface; consistent with the project rate-limiting all public
  routes — `auth` public routes use `throttle:6,1`, authenticated reads use
  `throttle:60,1`). 30/min/IP is generous for a once-per-session bootstrap call
  while bounding abuse.
- **Locale on a pre-login endpoint**: there is no authenticated user, so
  `preferredLocale()`/`users.locale` is unavailable. Resolve the locale for this
  request from the **`Accept-Language`** header, restricted to the app's
  **supported locales** (`en`, `it`), falling back to `config('app.locale')`
  (`en`) when the header is absent or unsupported. The service calls
  `app()->setLocale(<resolved>)` for the duration of the request before reading
  the enums, so `HasMeta::label()` (`__()` per-read) returns translated labels.
  This is a **local, request-scoped** resolution inside `ConfigService` — no new
  global middleware is introduced (minimal-change rule). A future global
  `SetLocale` middleware can supersede this without changing the contract.
- **Caching**: `EnumMeta` reflection is already cached per case
  (`EnumMetaCache`); labels intentionally are not (they depend on locale). To
  keep the locale-correct behaviour simple and avoid a stale-translation cache
  bug, **no additional application/HTTP cache is added now**. The payload is tiny
  and the call happens once per session; reflection is memoized. If load ever
  warrants it, add an application cache **keyed by resolved locale**
  (`config:<locale>`) — never a locale-agnostic key. Documented here so the
  constraint isn't lost.

### 4. Migration from the old endpoint

The per-enum endpoint is **removed**, not kept, to avoid two redundant APIs for
the same metadata (the user wants `/api/config`). Delete:

- the route `GET /api/enums/{enum}` (and its `throttle` wrapper) in
  `routes/api.php`,
- `app/Http/Controllers/Enums/EnumController.php`,
- `tests/Feature/Enums/EnumOptionsTest.php` (replaced by a new
  `tests/Feature/Config/ConfigTest.php`).

The `HasMeta` trait, `EnumMeta` DTO, the four enums and their attributes are
**untouched**.

---

## Alternatives Considered

- **Keep per-enum `GET /api/enums/{enum}`** (status quo, ADR 0007) — rejected:
  the user requires a single bootstrap call; N round-trips before first render
  and no home for future config. Keeping it *alongside* `/api/config` is also
  rejected (two redundant APIs, double maintenance/test surface).
- **Authenticated `/api/config` (inside `auth:sanctum`)** — rejected: the user
  decided the endpoint must be public/pre-login (it is the very first call,
  before login). Noted explicitly: *public is a user decision*. The safety
  envelope (only non-sensitive enum metadata, Security gate on future additions)
  is the mitigation.
- **camelCase enum group keys** (`personalDataType`) for the frontend —
  rejected: the backend serializes everything to the client in snake_case
  (`EnumMeta::toArray()`, all Resources). Introducing camelCase only here breaks
  one-API-one-casing consistency; the frontend maps names at its own boundary.
- **`data` as a flat enums array / `data` keyed only by enum** (no `enums`
  wrapper) — rejected: not extensible. Wrapping under `data.enums` reserves room
  for sibling sections (`locales`, `features`, …) without a breaking change.
- **A dedicated aggregate DTO (`ConfigData`)** for the payload — rejected as
  unnecessary now: the object is the terminal serialization payload (explicitly
  exempt from the DTO rule), and its leaves are already `EnumMeta` DTOs.
  Premature abstraction (`coding-standards.md` → Prefer Simplicity). Revisit if a
  future cross-layer section needs it.
- **Hardcoded enum allowlist as a controller/service constant** (like today's
  `EnumController::ALLOWED`) vs **`config/config.php`** — chose the config file
  to match the established server-side-registry pattern
  (`navigation`/`tables`/`personal_data`/`attachments`) and to make the
  public-exposure surface auditable in one obvious place.
- **HTTP/application caching of the payload** — deferred: locale-dependent labels
  make a naive cache a stale-translation hazard; the gain is negligible for a
  once-per-session tiny payload. Documented as a locale-keyed option for later.

---

## Trade-offs

- **Advantages**: one bootstrap round-trip; extensible contract; reuses ADR 0007
  building blocks unchanged; allowlist registry consistent with the rest of the
  codebase and auditable; consistent snake_case API; no new global middleware,
  no new dependency.
- **Disadvantages**: a public endpoint is a new unauthenticated attack surface
  (mitigated: only non-sensitive metadata, throttled, Security gate on future
  additions); request-scoped locale resolution inside the service is a small,
  localized special-case until a global `SetLocale` middleware exists.
- **What we give up**: response caching (intentionally, for locale correctness +
  simplicity); a typed aggregate DTO (intentionally, payload is the serialization
  boundary).

---

## Consequences

- The frontend gets a stable, single bootstrap call; future bootstrap config
  (locales, feature flags, app metadata) lands under `data` as sibling sections
  with no breaking change.
- `config/config.php` becomes the single, reviewable source of truth for what
  the public config exposes; the file header and this ADR bind any future
  addition to a Security review.
- Technical debt introduced: the locale-resolution helper lives in
  `ConfigService` rather than a shared middleware. Tracked here as the explicit
  follow-up: when a global `SetLocale` middleware is added, move the resolution
  there and keep `/api/config`'s contract unchanged.

---

## Affected Agents

- **Backend Agent** (next owner): implements route, `ConfigController`,
  `ConfigService`, `config/config.php`, locale resolution, tests; removes the
  old endpoint/controller/test.
- **Security Agent**: reviews the public exposure and the "no sensitive data
  without review" gate (endpoint touches public exposure of data → Security is a
  required reviewer per routing-matrix Security Review / Priority Rules).
- **Frontend Agent**: consumes `GET /api/config` as the first bootstrap call;
  drops any per-enum `/api/enums/{enum}` calls.
- **Reviewer Agent / QA Agent**: code review + functional/regression validation.
- **Documentation Agent**: API doc under `docs/api/` for the new endpoint;
  retire the per-enum doc if present.

---

## Risks

- **Public surface**: an unauthenticated endpoint must never grow to leak
  sensitive data — mitigated by the allowlist file + warning + Security gate, but
  the risk is organizational (a future careless addition). Throttling bounds
  scraping/DoS.
- **Locale resolution**: `Accept-Language` is client-controlled; restricting to
  the supported-locale allowlist (`en`/`it`) with a safe default prevents
  injecting an arbitrary locale. No user data is involved pre-login.
- **Breaking change for clients**: removing `GET /api/enums/{enum}` breaks any
  current caller; the frontend must switch to `/api/config` in the same release
  (coordinate Backend ↔ Frontend).

---

## References

- ADR 0007 — `docs/adr/0007-domain-enum-attribute-metadata-reader.md`
  (`HasMeta`, `EnumMeta`, the four decorated enums — reused).
- `standards/architecture.md` (thin controllers, business logic in Services,
  serialized-payload exemption from the DTO rule).
- `standards/security-standards.md` (Authorization First / least privilege /
  input validation — public exposure constraints).
- Current code: `routes/api.php`, `app/Http/Controllers/Enums/EnumController.php`
  (to remove), `app/Http/Controllers/Abstract/BaseApiController.php` (`ok()`),
  `config/navigation.php` + `app/Services/NavigationService.php` (registry
  pattern precedent).
