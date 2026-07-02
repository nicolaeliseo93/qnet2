# Architecture Decision Record

## ADR ID

0006

## Title

PersonalData, Contact and Address as agnostic, reusable polymorphic modules

## Status

ACCEPTED

## Date

2026-06-15

---

## Context

The product needs a `PersonalData` module (an identity / registry card) that can be
attached, in an agnostic way, to a `User` or to any other owning model (e.g. a future
`Company`, `Supplier`, `Location`). Each `PersonalData` must own:

- one or more **contacts** (phone, email, PEC, website, ...);
- one or more **addresses** (residence, domicile, operational site, registered office, ...).

Constraints and facts verified in the repository:

- The `addresses` table already exists and is **already polymorphic**:
  `backend/database/migrations/2026_06_12_090000_create_addresses_table.php` uses
  `$table->nullableMorphs('addressable')` and `App\Models\Address` exposes
  `addressable(): MorphTo`. There is **no** `personal_data_id` column on `addresses`,
  and there must not be one.
- There is **no** `Customer`, `PersonalData`, `Contact` model, nor
  `PersonalDataTypeEnum` / `PersonalTitleEnum` / `ContactTypeEnum` in this repo. They
  must be created.
- The migrations pasted by the user come from a **different** project (they reference
  `customers`, `personal_data_id`, `address_id` direct FKs) and even show a migration
  *away* from direct FKs *towards* a polymorphic relation. They are used as **field
  inspiration only**, not copied verbatim.
- The repository already has an established **"reusable starter-kit" pattern** for
  cross-cutting, owner-agnostic data: a polymorphic table + a drop-in trait on the
  owner. See `Attachment` + `App\Models\Concerns\HasAttachments` (`morphMany` on
  `attachable`) and `Address` (`morphTo` on `addressable`). This is the pattern to
  follow.
- `Address` currently has **no Factory and no Policy** — both are required by
  `standards/architecture.md` and must be added as part of this work.
- Mandatory standards apply: `BaseModel`, explicit `$fillable`, `LogsModelActivity`,
  a CRUD `Policy` extending `BasePolicy`, and a `Factory` for every domain model.
- The repo already has reusable Eloquent **attribute classes** under
  `app/Enums/Attributes/` (`IsDefault`, `Label`, ...), and string-backed enums under
  `app/Enums/` with a `fromValue()` safe-resolver convention
  (`NotificationLevelEnum`).

The decision needed: how `PersonalData` is attached to owners, how it owns contacts and
addresses, and whether `Contact` should be polymorphic (like `Address`) or coupled to
`PersonalData` via a direct FK.

---

## Decision

Adopt **three coordinated decisions**, consistent with the existing
polymorphic + drop-in-trait pattern.

### D1 — Address stays polymorphic; PersonalData consumes it via the existing morph

No change to the `addresses` schema. `PersonalData` owns addresses through
`morphMany(Address::class, 'addressable')`, exactly like any other owner. To keep this
reusable and DRY, introduce a drop-in trait `HasAddresses` (mirroring `HasAttachments`)
that any owner (`PersonalData`, `User`, future models) can `use` to get the
`addresses()` relation and owner-side cleanup. **No `personal_data_id` on `addresses`.**

### D2 — Contact is also polymorphic (`contactable_type` / `contactable_id`)

`Contact` is created as a **polymorphic** reusable module — same shape as `Address`,
not coupled to `PersonalData` via a `personal_data_id` FK. `PersonalData` owns contacts
through `morphMany(Contact::class, 'contactable')`, exposed by a drop-in `HasContacts`
trait. A `User` (or any future model) can own contacts directly without a schema change.

Rationale (per the architect Decision Rules — correctness first, then maintainability,
then simplicity): the user explicitly frames this as a "reusable starter kit"; contacts
are conceptually as reusable as addresses; coupling them to `personal_data_id` would
make the two sibling concepts (contacts/addresses) **structurally inconsistent** and
would block direct reuse on other owners, forcing a future schema migration (exactly the
migration the user's pasted SQL was trying to undo). The polymorphic cost (no DB-level
FK on the morph, composite index instead) is already accepted in this repo for
`Address` and `Attachment`, so we are not introducing a *new* trade-off — we are staying
consistent with the codebase.

### D3 — PersonalData is attached to its owner polymorphically (`personable`)

`PersonalData` is itself an owner-agnostic module: it carries a nullable polymorphic
owner `nullableMorphs('personable')` and exposes `personable(): MorphTo`. Owners
(`User`, future models) attach it via a drop-in `HasPersonalData` trait exposing
`personalData(): MorphOne` (a model owns **one** registry card). **No
`personal_data_id` column is added to `users`.**

Rationale: this keeps the attachment direction identical to `Address`/`Attachment` (the
owner-agnostic side lives on the satellite table, not as an FK sprinkled across every
owner table), so adding a new owner type is a zero-migration, trait-only change.
A `MorphOne` (not `MorphMany`) is chosen because a registry card is a 1:1 profile of its
owner; if a future requirement needs multiple cards per owner, the relation can be
widened without a schema change.

---

## Alternatives Considered

- **Contact coupled to `PersonalData` via `personal_data_id` FK** — rejected. It is
  simpler in isolation and gives a real DB FK, but it makes contacts non-reusable,
  breaks symmetry with the existing polymorphic `Address`, and would require a future
  migration to generalize (the very migration the user's other project was performing).
  Correctness/long-term consistency outranks the marginal simplicity gain.
- **PersonalData attached via `personal_data_id` FK on `users`** — rejected. It forces
  every future owner table to grow an FK column and a migration, and spreads the
  "agnostic" wiring across owners instead of centralizing it on the satellite. It also
  contradicts the established `addressable`/`attachable` direction.
- **MySQL `virtualAs` generated columns for `full_name` / `ceo`** (as in the pasted
  migrations) — rejected as the default. Generated columns push display logic into the
  DB, are harder to test, are coupled to MySQL, and conflict with "business logic stays
  in PHP / models thin but expressive". We use **PHP accessors** (`fullName()` via
  `Attribute`) instead. (See Trade-offs for the narrow exception.)
- **`Address`/`Contact` as a single generic `contact_points` table** — rejected as
  premature abstraction (`standards/decision-making.md` → Avoid Premature Abstraction).
  Addresses and contacts have materially different fields; merging them harms clarity.

---

## Trade-offs

- **Advantages**: full owner-agnostic reuse with **zero schema change** to onboard a new
  owner; perfect consistency with the existing `Address`/`Attachment` pattern; thin
  models, business logic in services; testable accessors; symmetric, predictable API for
  the Backend Agent.
- **Disadvantages / what we give up**: polymorphic relations have **no database-level
  foreign key** on the morph columns (referential integrity for the owner link is
  enforced in application code + cleanup hooks, not by the DB). We mitigate with a
  composite index on `(*_type, *_id)` (provided by `nullableMorphs`) and trait-level
  cascade cleanup on owner delete (mirroring `HasAttachments::bootHasAttachments`).
- **Generated-columns exception**: `virtualAs` is acceptable **only** if a column must be
  filtered/sorted at the DB level for performance on large datasets; not needed for the
  MVP, so deferred. Documented here so a future need is a conscious decision, not a
  silent drift.
- **Technical debt introduced**: none structural. The only traceable item is that the
  app-level integrity of the morph links must be covered by tests (cleanup on owner
  delete, no orphan rows).

---

## Consequences

- Positive: `PersonalData`, `Contact`, `Address` form a coherent family of reusable
  modules. Future entities get registry cards, contacts and addresses for free by adding
  three traits. The pasted-migration FK approach is intentionally not reproduced.
- Positive: closes a pre-existing gap — `Address` gains its mandatory Factory and Policy.
- Negative: three new morph relations to keep indexed and cleaned up; the Backend Agent
  must implement owner-delete cascade and its tests (coverage ≥ 85%).
- Negative: PEC/website/email contacts are personal data → privacy surface grows
  (see Risks / Required Agents).

---

## Affected Agents

- **Backend Agent** (owner of implementation: models, migrations, enums, traits,
  factories, policies, services, tests).
- **Reviewer Agent** (code quality, consistency with the `HasAttachments` pattern).
- **QA Agent** (functional + cascade-delete + edge cases, coverage gate).
- **Security Agent** (authorization on the new CRUD permissions; morph link integrity).
- **Legal Agent** (PersonalData/Contact hold personal data → GDPR purpose, retention,
  consent — to be assessed when an API/UI exposes them).

---

## Risks

- **Orphan rows / broken morph links** if owner-delete cascade is not implemented and
  tested. Mitigation: `boot*` cleanup hooks in the traits + tests.
- **Validation of contact values** (email vs phone vs URL vs PEC) must be enforced
  server-side in a `FormRequest` per `ContactType`; weak validation would corrupt data.
- **`is_primary` / `is_default` invariants** (at most one primary contact / address per
  owner per type) are application-enforced, not DB-enforced — needs explicit logic + tests.
- **Privacy**: personal data exposure — defer API/UI exposure decisions to Legal.
- **Enum drift**: contact/personal-data types must use the repo's string-backed enum +
  safe `fromValue()` convention to avoid out-of-contract values.

---

## References

- `backend/database/migrations/2026_06_12_090000_create_addresses_table.php`
- `backend/app/Models/Address.php`
- `backend/app/Models/Attachment.php`, `backend/app/Models/Concerns/HasAttachments.php`
- `backend/app/Enums/NotificationLevelEnum.php`, `backend/app/Enums/Attributes/IsDefault.php`
- `standards/architecture.md` (Models: BaseModel, fillable, activity log, Policy, Factory)
- `standards/decision-making.md` (Decision Hierarchy, Avoid Premature Abstraction)
- Handoff to Backend Agent (this document's delivery message)
