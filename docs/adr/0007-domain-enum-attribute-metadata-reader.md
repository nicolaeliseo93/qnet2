# Architecture Decision Record

## ADR ID

0007

## Title

Domain enum metadata via the PHP Attribute suite, a reflection reader trait, and an API options contract

## Status

ACCEPTED

## Date

2026-06-15

---

## Context

The codebase already ships an inert suite of PHP attributes targeting enum
constants in `backend/app/Enums/Attributes/` (`Label`, `Color`, `Icon`,
`IsDefault`, `HiddenOnForm`, `Path`, `Parser`, `Percentage`). All are
`#[Attribute(Attribute::TARGET_CLASS_CONSTANT)]` (except `Percentage`, declared
without a target), each with a single promoted public property. A grep across
`app/` and `tests/` confirms **zero usage**: no enum applies them and no reader
(trait/reflection) consumes them. They are infrastructure with no behaviour.

Four string-backed domain enums need user-facing presentation metadata so the
React frontend can render labels, icons and colours and populate form selects
without hardcoding the value→label mapping client-side:

- `PersonalDataTypeEnum` (Individual, Company) — cast on `PersonalData.type`.
- `PersonalTitleEnum` (Mr, Mrs, Ms, Dr, Prof) — nullable cast on
  `PersonalData.title`.
- `ContactTypeEnum` (Phone, Mobile, Fax, Email, Pec, Website) — cast on
  `Contact.type`; already owns a domain method `valueRules()`.
- `NotificationLevelEnum` (Info, Success, Warning, Error) — serialized today by
  `NotificationData` / `NotificationResource`.

`HttpStatusEnum` is technical and explicitly out of scope.

Constraints:

- Every enum exposes a safe `fromValue()`; `PersonalTitleEnum::fromValue()`
  returns `null` (optional). The default returned by `fromValue()` must stay
  coherent with whichever case carries `#[IsDefault]`.
- User-facing strings must be English-source and localized via Laravel i18n. The
  established pattern in this repo is **JSON-key translation**: `lang/it.json`
  maps the English source string to Italian, and call sites use
  `__('English string')`. There are no per-domain PHP translation files for
  these strings.
- The DTO standard (`standards/architecture.md` → Data Transfer Objects) forbids
  "flying magic arrays" crossing layer boundaries, but explicitly allows arrays
  for the final JSON payload serialized to the client.
- The 227 green tests, `fromValue()`, and `ContactTypeEnum::valueRules()` must
  not break. Backward compatibility of `NotificationResource` is mandatory.

The decision on scope is fixed by the user — "Decorate + reader + API" — and is
not reopened here. This ADR records *how*.

---

## Decision

Adopt a three-part design.

### 1. Reflection reader trait `App\Enums\Concerns\HasMeta`

A new trait placed in a new `backend/app/Enums/Concerns/` directory (mirroring
the existing `app/Models/Concerns/` convention). Enums `use` it to gain typed
accessors that read the attribute suite off each case via
`ReflectionEnumUnitCase`/`ReflectionClassConstant::getAttributes()`.

Public instance API (per case):

```php
public function label(): string;          // __() of the #[Label] source string; falls back to the case name (Str::headline) when #[Label] is absent
public function color(): ?string;          // #[Color]->color or null
public function icon(): ?string;           // #[Icon]->icon or null
public function isDefault(): bool;         // #[IsDefault]->isDefault or false
public function hiddenOnForm(): bool;      // #[HiddenOnForm]->hiddenOnForm or false
public function meta(): EnumMeta;          // aggregated DTO of the above + value
```

Public static API (per enum):

```php
public static function options(): array;   // array<int, EnumMeta>, ALL cases in declaration order
public static function default(): ?static;  // the case carrying #[IsDefault], or null
```

Semantics decided:

- **Missing attribute → safe default**, never an exception: `null` for
  string-valued attributes, `false` for boolean ones. `label()` is the one
  exception to "null on missing": it falls back to a humanized case name so a
  case is never label-less.
- **`Label` holds the English source string**, not a translation key. The reader
  resolves it with `__()` at read time. This matches the repo's existing
  JSON-key i18n (`lang/it.json`) and keeps the attribute self-documenting in
  English (coding-standards: English-only source).
- **Reflection is cached** in a static `array<class-string, array<string,
  array<string, mixed>>>` keyed by enum FQCN + case name, populated lazily on
  first read. Reflection over a fixed set of cases is cheap, but caching removes
  repeated reflection when `options()` is called per request. Translation is
  resolved on read (not cached) so locale switches are honoured.
- `Path`, `Parser`, `Percentage` are **not** read by this trait: they belong to
  unrelated subsystems (routing/parsing/weighting) and none of the four target
  enums use them. The trait stays focused (ISP).

### 2. Aggregated DTO `App\DataObjects\Enums\EnumMeta`

A `final readonly` DTO (PHP-native, no new dependency) carrying one case's
metadata so it travels typed across the layer boundary (Service/Resource), per
the DTO standard:

```php
final readonly class EnumMeta
{
    public function __construct(
        public string $value,
        public string $label,
        public ?string $color,
        public ?string $icon,
        public bool $isDefault,
        public bool $hiddenOnForm,
    ) {}

    /** @return array{value:string,label:string,color:string|null,icon:string|null,is_default:bool,hidden_on_form:bool} */
    public function toArray(): array; // final serialized client contract (array allowed by exception)
}
```

`meta()`/`options()` return `EnumMeta`, not raw arrays, satisfying "no flying
magic arrays". The `toArray()` boundary to JSON is the sanctioned array
exception.

### 3. API exposure

- **Form options endpoint.** Expose the cases of a form-relevant enum as
  selectable options through a thin, read-only endpoint that returns
  `EnumMeta::toArray()` per case (filtered by `hiddenOnForm() === false`).
  Backend Agent decides the concrete route shape consistent with the existing
  `routes/api.php` conventions (e.g. a `GET /api/enums/{enum}` resolved through
  an allowlist map, or per-domain option endpoints). The JSON contract per
  option is fixed here:

```json
{ "value": "individual", "label": "Individual", "color": null, "icon": "user", "is_default": true, "hidden_on_form": false }
```

- **Inline enrichment where an enum is already serialized.** Where a Resource
  already emits an enum value (e.g. `PersonalDataResource.type`), the Backend
  Agent may add a sibling object (e.g. `type_meta`) carrying the same
  `EnumMeta::toArray()` shape — **additive only**, never replacing the existing
  scalar value.

- **NotificationLevelEnum backward compatibility is absolute.** `NotificationData`
  keeps serializing `level` as the bare string (`$this->level->value`) and
  `NotificationResource` is unchanged. Level metadata (colour/icon for the
  toast) is exposed *only* through the additive options endpoint or a future
  additive `level_meta`, never by altering the current `data.level` contract.

---

## Alternatives Considered

- **Static `match()` methods on each enum** (`label()`, `color()` implemented as
  `match($this)`) — rejected: duplicates a presentation-mapping pattern across
  four enums, ignores the already-built attribute suite, and scatters i18n
  source strings. The attributes give a single declarative home per case.

- **`Label` holds a translation key** (e.g. `'enums.personal_data_type.company'`)
  resolved via `__()` against per-domain PHP files — rejected: the repo's
  established i18n is JSON-key (`lang/it.json`, English source as key). Inventing
  a parallel keyed namespace contradicts the existing pattern and the
  "Do Not Invent" rule. English-source-string-as-Label is self-documenting and
  consistent.

- **Return raw `array` from `meta()`/`options()`** — rejected: violates the DTO
  standard's "no flying magic arrays" for values crossing into the
  Service/Resource layer. The array is only sanctioned at the final JSON
  boundary, which `EnumMeta::toArray()` provides.

- **A generic `enum_meta` Eloquent-cast or global resource** auto-enriching every
  serialized enum — rejected as premature/overengineered for four enums and
  risks silently changing existing contracts (notably `NotificationResource`).
  Additive, opt-in enrichment is safer.

- **Read `Path`/`Parser`/`Percentage` in the same trait** — rejected (ISP):
  unrelated concerns, unused by the targets, would bloat the reader.

---

## Trade-offs

- **Advantages**: single declarative source of presentation metadata per case;
  the dormant attribute suite finally has a consumer; type-safe `EnumMeta`
  across layers; frontend stops hardcoding value→label/icon/colour; additive API
  keeps every existing contract intact; no new dependency (native reflection +
  readonly DTO).
- **Disadvantages**: reflection (mitigated by static caching); a new convention
  (`app/Enums/Concerns/`) developers must learn; metadata now lives in
  attributes rather than inline code, a small indirection.
- **What we give up**: a fully automatic, zero-touch enrichment of every
  serialized enum — traded for explicit, backward-safe, opt-in exposure.

---

## Consequences

- Positive: consistent enum presentation contract across API and frontend;
  reusable trait for any future enum; clear separation (attributes = data,
  trait = reader, DTO = transport, Resource/endpoint = exposure).
- Positive: i18n stays centralized in `lang/*.json`; adding a locale needs no
  code change.
- Negative / tech debt (tracked): the English fallback label for a case missing
  `#[Label]` is a humanized case name — acceptable, but every target case should
  carry an explicit `#[Label]` to avoid relying on it. No other debt introduced.

---

## Affected Agents

- **Backend Agent** — implements the trait, the `EnumMeta` DTO, the attribute
  decoration on the four enums, the options endpoint/inline enrichment, tests,
  and `lang/it.json` translation entries.
- **Frontend Agent** — consumes the options contract for selects and the meta
  for badges/toasts (separate handoff, out of this ADR's delivery).
- **Reviewer / QA** — verify backward compatibility and coverage ≥ 85%.
- **Architect Agent** — owner of this decision.

---

## Risks

- Breaking `NotificationResource` / `NotificationData` if level exposure is done
  in-place. Mitigation: enrichment is strictly additive; `data.level` stays a
  bare string.
- `fromValue()` defaults diverging from `#[IsDefault]`. Mitigation: a test must
  assert, per enum, that the `#[IsDefault]` case equals `fromValue(null)` (or
  that `PersonalTitleEnum` carries no `#[IsDefault]`, since its `fromValue()`
  returns null).
- Reflection performance on hot paths. Mitigation: static per-class cache;
  `options()` reflects once per request.
- Label fallback masking a forgotten `#[Label]`. Mitigation: decorate every
  in-scope case explicitly; optional test asserting each case resolves a
  non-empty label.

---

## References

- `standards/architecture.md` → Data Transfer Objects, Models
- `standards/coding-standards.md` → Language (English Only) / i18n, SOLID
- `backend/app/Enums/Attributes/*` (attribute suite)
- `backend/app/DataObjects/Notifications/NotificationData.php`,
  `backend/app/Http/Resources/NotificationResource.php` (compat baseline)
- `backend/app/Models/Concerns/LogsModelActivity.php` (Concerns convention)
- ADR 0005 (database notifications), ADR 0006 (personal data / contacts)
