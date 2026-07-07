# HANDOFF — living project memory

> Injected at session start. Update at every green state.

## Change — Demo product catalogue reworked from physical goods to SERVICES — GREEN (2026-07-07)

`ProductCatalogTaxonomy` (data for `DemoProductCatalogSeeder`) rewritten: was Elettronica/Arredamento
physical goods, now a two-root SERVICE catalogue — `Consulenza` (children IT → Sviluppo Software /
Cybersecurity, plus Business) and `Formazione` (Corsi, Workshop). Same structural invariants preserved:
all 5 attribute data types, an ENUM reused across both roots (now `delivery_mode` [Onsite/Remoto/Ibrido]
in place of `color`), 3-level tree, required attrs, and a demo service seeded directly in the intermediate
`IT` category. New attribute codes: provider(str), sla_hours(dec), delivery_mode(enum), seniority_level(enum),
duration_hours(int), technology(str), on_call(bool), audit_type(enum), is_remote(bool), certificate_included(bool),
max_participants(int), session_length_hours(dec). Products already seeded as `ProductType::Service` (unchanged);
`ProductFactory`/`ProductCategoryFactory` were already generic+Service (untouched). Requirement change →
`DemoProductCatalogSeederTest` updated to the new names (Consulenza/Formazione/IT/Sviluppo Software,
delivery_mode). Verified: Pest 6/6 (196 assertions) + Pint clean.

## Change — Product categories: opt-out of attribute inheritance (barrier) — GREEN (2026-07-07)

New per-category `inherits_attributes` boolean (migration `110700`, default true → backward compatible)
on `product_categories`. When false a category becomes an inheritance ROOT: a BARRIER that cuts it AND
its descendants off from every attribute above it. User-confirmed semantics (AskUserQuestion): barrier on
the chain (not just skip the direct parent) + editable in create AND edit.

Backend: `CategoryHierarchy` gained a private `inheritedAncestors()` (barrier-aware walk) now used by
`effectiveAttributes()` + `ancestorAttributes()`; the public `ancestors()` stays the FULL structural walk
and is used ONLY by the anti-cycle guard — the barrier must NOT weaken cycle detection (kept separate on
purpose). Walk rule: if the category itself opts out → inherits nothing; else climb while each node keeps
inheriting, and the first opted-out ANCESTOR still contributes its own attributes but stops the climb.
Wired through: Model (`#[Fillable]` + `casts` bool), Create/UpdateProductCategoryData (Update uses the
`*Submitted` flag pattern), ProductCategoryService::create, Store/UpdateRequest (`sometimes|boolean`),
ProductCategoryResource (`inherits_attributes`), ProductCategoriesAuthorization (field
`inherits_attributes` type `boolean` + ceiling — REQUIRED or EnforcesFieldPermissions rejects it),
Factory (+`notInheriting()` state).

Frontend (features/product-categories): `inherits_attributes` added to schema (z.boolean), types
(ProductCategoryDetail + CreateProductCategoryPayload), form defaults (edit=category value, create=true),
payload builders (create always sends it; update diffs it), and SERVER_ERROR_FIELDS. Form body renders a
`Switch` MetaField (mirrors users `is_active`) ONLY when a parent is selected (meaningless at root), and
the read-only inherited list is gated on the toggle so opting out empties it immediately (before save).
i18n en/it: `inheritsAttributes` + `inheritsAttributesHint`.

Spec 0017 updated (overview, data model, effective-attributes description, contract shapes, Authorization
fields, new AC-008b). Verified GREEN: backend `tests/Feature/ProductCategories`+`Products` 73/73 (419
assert) incl. 4 new barrier/flag tests; Pint clean. Frontend vitest product-categories 18/18 (payload
test extended for the new field + a toggle case), `tsc --noEmit` clean, ESLint clean.

## Change — Demo referents seeder (full contacts + addresses) — GREEN (2026-07-07)

New `DemoReferentSeeder` (registered in `DemoDataSeeder` right after `DemoReferentTypeSeeder`, its
dependency). Seeds 30 referents, each reusing the users' anagraphic stack via `HasPersonalData`: one
personal-data card (individual/company round-robin, index%3), a COMPLETE contact form (email+mobile
primary, phone; companies also switchboard+pec+website) and 1–2 addresses tied to REAL seeded cities
(`Address::factory()->forCity()`, full geo ancestry) — mirrors `DemoUserContactSeeder`/`DemoUserAddressSeeder`.
Classified round-robin by a seeded `ReferentType`; `contact_scope` alternates internal/external; `name`
re-derived from the card's `full_name` (mirrors `ReferentProfileWriter`). Idempotent via per-model delete
(HasPersonalData deleting hook cascades card→contacts/addresses), mirroring `DemoOperationalSiteSeeder`.
Degrades gracefully with empty cities/types. Deterministic faker seed 20260707.
Verified: Pest `tests/Feature/Referents/DemoReferentSeederTest.php` 3/3 (246 assertions) + Pint clean;
real run against dev DB (156k cities) → 30 referents, sample company 5 contacts/2 addresses w/ real geo,
re-run stays 30 / 0 orphans (idempotent).

## Feature — Products module (configurable EAV: attributes + category tree + products) — GREEN (2026-07-07)

Spec `docs/specs/0017-products-module.xml` (contract-first, user-approved via AskUserQuestion). Built by
an agent team (backend + frontend teammates in parallel, disjoint ownership, independent verifier gate).
Three new resources, each a thin adapter on the generic framework — ZERO changes to generic engines.

User-approved decisions: EAV value storage = TYPED COLUMNS (not JSON); products grid shows ONLY generic
fields (dynamic attrs live in the product form/detail, no dynamic grid columns); attributes are reusable
entities assigned to categories via a pivot with recursive inheritance; three separate menu entries.

Data model (6 migrations, dev MySQL, up+down verified):
- `attributes` (code unique, name, data_type) + `attribute_options` (value/label/sort_order, unique[attribute_id,value]).
- `product_categories` (parent_id nullable self-FK restrictOnDelete — adjacency-list tree) + pivot
  `attribute_category` (is_required, sort_order, unique[attribute_id,category_id]).
- `products` (name, description, cost/price decimal(15,2), category_id FK restrictOnDelete) +
  `product_attribute_values` (typed cols value_string/value_integer/value_decimal/value_boolean + option_id
  nullOnDelete, unique[product_id,attribute_id]).
- Enum `App\Enums\AttributeType` (STRING/INTEGER/DECIMAL/BOOLEAN/ENUM) — extension point; registered in
  config/config.php form_enums as `attribute_type`.

Inheritance: `App\Services\ProductCategories\CategoryHierarchy::effectiveAttributes()` = own attrs UNION all
ancestors', ancestors-first + sort_order. IMPLEMENTED as an iterative PHP `parent_id` walk (NOT a
`WITH RECURSIVE` CTE) — portable SQLite/MySQL, zero raw SQL (exceeds the anti-SQLi constraint). The lead
authorized this at dispatch; spec 0017 scope/AC-008/constraints were updated to match the implementation.

Backend layers (mirror business-functions/referent-types): Policies (auto-discovered), *Authorization
(config/authorization.php), TableDefinitions for `attributes`, `products` AND `product-categories`
(REV 2026-07-07 — see below) with derived `category`/`parent` filter/sort/distinct,
Requests/DTO/Services/Resources/thin Controllers.
Key endpoints beyond CRUD: `GET /product-categories/tree` (nested + counts) and
`GET /product-categories/{id}/effective-attributes` (feeds the dynamic product form) — both declared ABOVE
the `{productCategory}` wildcard so literals win. ProductService validates dynamic attrs (⊆ effective set,
type-coherent, ENUM ∈ options, required enforced) and upserts into the correct value_* column.
Restrictive deletes → 409 (attribute in use / category with children or products). Morph map + nav nodes
(icons package/list-tree/sliders-horizontal, matched to frontend icon-map) + DemoProductCatalogSeeder added.
`permissions:sync` → 21 new permissions (attributes.*/product-categories.*/products.*, 7 abilities each).

Frontend (features/attributes, features/product-categories, features/products): TableView adapters for
attributes, products AND product-categories grids; attribute form shows the ENUM options editor only when
data_type===ENUM; the category CRUD form (attribute-assignment editor with is_required/sort_order +
read-only inherited list) is hosted in the grid's Sheet; product form's category picker calls
effective-attributes and generates typed dynamic fields (STRING→text, INTEGER/DECIMAL→number,
BOOLEAN→checkbox, ENUM→select), regenerating on category change. Category/parent pickers flatten the tree
endpoint client-side; the attribute-assignment picker reuses POST /tables/attributes/rows — NO new for-select
endpoints (per spec). Routes + breadcrumbs + icon-map + split i18n en/it-products.ts wired.

REV 2026-07-07 (user requests, both GREEN, independently re-verified): (1) product-categories LIST is no
longer a tree — it is a standard AG Grid SSRM table (`ProductCategoriesTableDefinition` +
`ProductCategoryColumnCatalog` registered in config/tables.php; columns name/parent/description/
attributes_count/products_count/created_at). `parent` is a derived self-relation column (filter/sort/distinct
like BusinessFunctions `manager`); the two `*_count` columns are withCount AGGREGATES (mirror
RolesTableDefinition `users_count`: generic sort on the alias, filter+distinct via a `has($rel,op,n)` count
condition — `ProductCategoryCountColumn`). `deleteModel()` was overridden to route the now-reachable generic
bulk-delete through ProductCategoryService::delete() so the restrictive 409 guard (children/products in use)
holds there too. The old tree LIST components (product-category-tree*.tsx + test) were DELETED; the form
stack + use-product-category-tree.ts + flatten-tree.ts were KEPT (the form's parent-picker still flattens
GET /product-categories/tree, which remains). (2) The attributes_count/products_count grid cells show the
count PLUS a hover/focus TOOLTIP listing the names: mapRow now carries `attributes:[{id,name}]` (own
assignments, uncapped) and `products:[{id,name}]` (capped at PRODUCT_TOOLTIP_LIST_LIMIT=100; counts stay the
real withCount totals; frontend shows "+N more" from products_count - products.length). Backend gotcha left
as a code comment: a restricted HasMany eager-select (`products:id,name`) silently drops rows unless the FK
`category_id` is included in the select. Frontend uses a shared `CountWithNamesCell` mirroring the generic
count-badge+tooltip pattern. Verified: backend tests/Feature/ProductCategories 34/34 + pint clean; frontend
product-categories vitest 12/12 + tsc clean. spec 0017 updated (scope/AC-008/AC-022/constraints).

REV 2026-07-07 (user request, GREEN): products now carry a `product_type` classification. New enum
`App\Enums\ProductType` (SERVICE only for now, `#[IsDefault]`, sole extension point — add a case to surface
it everywhere). Migration `2026_07_07_110600_add_product_type_to_products_table` adds NOT NULL string(32)
default 'SERVICE', indexed (up/down/re-apply verified on dev). Model cast `product_type => ProductType` +
Fillable; ProductFactory defaults Service; ProductResource exposes it; config form_enums key `product_type`.
Grid: it is a REAL badge/set column (NOT derived) — ProductColumnCatalog adds column+filter; ProductsTableDefinition
mirrors AttributesTableDefinition's `data_type` (badgesFor/enumKeyFor + raw-column distinctValues bypassing the
enum cast; the generic set filter handles the real column via the filters() allow-list). Products grid is now 7
columns (name/description/cost/price/category/product_type/created_at).
Frontend: `ProductType` type + product_type on ProductDetail; badge renders via the generic BadgeCell fallback
(no domain renderer, like `data_type`); product-detail shows a Type badge via enumLabelOf('product_type', …);
i18n product_type enum + `products.columns.product_type` (en/it).

REV 2026-07-07 (follow-up, GREEN): product_type is now SELECTABLE + REQUIRED in the product form, and cost/price
are now REQUIRED too (user request). ProductsAuthorization registers product_type as a mandatory `select` field
(+ cost/price flipped to mandatory) in both fields() and the ceiling. Store/UpdateProductRequest: cost/price
`required` (update `sometimes|required`), product_type `required, Rule::enum(ProductType::class)`. CreateProductData
now carries non-null cost/price/`ProductType $productType`; UpdateProductData adds productType + submitted flag →
submittedAttributes; ProductService::create persists product_type; DemoProductCatalogSeeder passes
productType: Service. GOTCHA (spec 0008): MANDATORY fields bypass the DB field-permission matrix intersect
(AbstractResourceAuthorization::fieldPermissions), so making cost/price mandatory means the matrix can no longer
lock them — ProductSecurityTest's matrix-lock case was retargeted from `price` to `description` (now the ONLY
non-mandatory generic field). DB columns cost/price stay NULLABLE (no migration): "required" is enforced at the
validation/form layer only, so pre-existing null rows are not broken. Frontend: schema requires cost/price
(superRefine) + product_type (`z.enum(['SERVICE'])`, form default 'SERVICE'); form-body adds a MetaField-wrapped
product_type Select fed by useEnumOptions('product_type'); payload builders thread product_type + non-null
cost/price; i18n costRequired/priceRequired/productType (en/it). Verified: backend Pest Products+Authorization+
Config+Attributes 154/154, pint clean; frontend tsc clean, vitest products+i18n 13/13, eslint clean.

Verification (independent verifier, all re-run): 25/25 acceptance criteria PASS with file:line evidence;
integration seams A-F PASS (icon tokens, enum values, effective-attributes/tree/rows shapes, permission/route
alignment). Backend Pest 1321/1323 pass (1 skip; 1 pre-existing unrelated failure
`AbstractMigrationSourcePreviewTest` — RolesSource emits extra `description:null`, untouched by this work).
`pint --test` exit 0 (lead re-confirmed after backend fix-mode reformatted 6 test files). Frontend tsc
--noEmit clean, ESLint clean on changed files, Vitest 531 pass / 3 pre-existing unrelated failures
(`features/table/cell-renderers.test.tsx` ContactsCell — i18n locale not set to 'en' in that suite,
confirmed pre-existing). Coverage above targets (Policy/Authorization/Service ≥90%, controller/table ≥85%).

NOTE for the field-permission matrix: dynamic product attributes are authorized at RESOURCE level
(`products.update`) only — the per-role field matrix (spec 0006) covers just the generic product fields
(name/description/cost/price/category_id). Products table is minimal by design ("poi la completeremo").
Two known PRE-EXISTING failures above are NOT from this work. Changes NOT yet committed (82 files) — awaiting
user go for the git checkpoint.

## Change — Referents `primary_contact` column made IDENTICAL to Users — GREEN (2026-07-06)

Ad-hoc request: the referents `primary_contact` table column must be identical to the users one,
reusing existing code (no reinvention). User chose FULL parity (render + sort/filter) via
AskUserQuestion. Before: referents showed a single `{type,value}` inline badge, not sortable/filterable;
users showed the array of ALL primary contacts (count badge + tooltip), sortable + filterable.

Key leverage: the contact mechanics were domain-agnostic but lived privately/hardcoded-to-`users` in
`UserPersonalDataColumns`. Extracted them ONCE into a shared collaborator so the two columns can never
drift; the frontend is already generic (`type:'tags'` + `filterType:'text'` auto-yields the same
`agMultiColumnFilter`), so only the renderer swap was needed there.

Backend:
- NEW `App\Tables\Shared\PrimaryContactColumn` — the whole column contract: `format()` (payload = all
  primary contacts `{type,icon,label,value}`), `applyTextFilter`/`applySetFilter` (whereHas on
  `personalData.contacts`, is_primary, bound LIKE / whereIn value, capped 200), `sortSubquery(ownerTable,
  ownerMorph)` (correlated MIN(value)), `distinctValues(query, ownerTable, ownerMorph, search, limit)`.
  The owner table + morph alias are the ONLY per-domain params.
- `UserPersonalDataColumns`: now injects `PrimaryContactColumn` and DELEGATES its 5 contact methods to it
  (behavior IDENTICAL — `UsersTableDefinition` unchanged, zero edits there). Removed the dead
  `formatContacts`/`formatContact` + the inlined sort/distinct bodies; dropped unused Contact/DB/
  QueryBuilder/Collection imports.
- `ReferentsTableDefinition`: `use UnwrapsMultiFilter`, injects `PrimaryContactColumn`, mapRow →
  `format($row->personalData?->contacts)`, `applyDerivedFilter` handles `primary_contact` via the multi
  unwrap (Set→applySetFilter + condition→applyTextFilter, in AND), `applyDerivedSort`/`distinctValues`
  add the `primary_contact` arm bound to `('referents', (new Referent)->getMorphClass())`. Removed the
  bespoke `primaryContact()` single-contact method.
- `ReferentColumnCatalog`: `primary_contact` now `type:'tags'`, sortable+filterable, `filterType:'text'`
  (dropped `hasFilterValues:false`); added `['columnId'=>'primary_contact','type'=>'text']` to filters().

Frontend: `referents/column-renderers.tsx` → `primary_contact` now uses the shared `ContactsCell`
(removed the bespoke `PrimaryContactCell` + `ReferentPrimaryContact` interface). Nothing else changed.

Tests: `ReferentTableTest` — columns assertion updated to tags/sortable/filterable; row payload now
asserts the array `{type,icon,label,value}`; empty referent → `primary_contact === []` (was null); the
old "422 primary_contact not filterable" test replaced with distinct-values + text-filter + sort tests
(parity with Users). Frontend renderer test now asserts the shared count badge. Backend: Table +
Referents + ReferentTypes suites GREEN (224 pass); Pint clean. Frontend: tsc clean, ESLint clean on
changed files, referents suite 29 pass. The KNOWN PRE-EXISTING `ContactsCell` 3-test failure (shared
`cell-renderers.test.tsx`, i18n not set to `en` in its setup) still stands — confirmed pre-existing via
`git stash`, untouched by this change.

## Change — Gender field on the anagraphic card + referents migration (pec/fax/gender) — GREEN (2026-07-06)

Ad-hoc request: (1) add `gender` (enum male/female, DEFAULT male, rendered as a select) to the shared
personal-data card ("anagrafica"), extended to EVERY anagraphic form; (2) the `referents` migration
source must also map `pec`, `fax` and `gender`. Decisions taken with the user (AskUserQuestion):
field name `gender` (not `sex`); INDIVIDUAL-ONLY (nullable column, null for a company — mirrors
`birth_date`).

Key leverage: all forms (user, referent, `/settings` profile) render the SAME `PersonalDataCardForm`
and submit via the SAME `drafts.ts` helpers, so the field was added in ONE place and propagated
everywhere.

Backend:
- NEW `App\Enums\GenderEnum` (Male `#[IsDefault]` / Female, HasMeta, `fromValue`).
- NEW additive migration `2026_07_07_100400_add_gender_to_personal_data_table` — `gender` string
  nullable after `birth_date`, reversible. APPLIED to dev DB.
- `PersonalData`: `gender` in `$fillable` + cast `GenderEnum::class` (NOT hidden — the select needs to
  read it; it is not a fiscal identifier). `CreatePersonalData` DTO param + `toAttributes`.
  `PersonalDataResource` exposes `gender`. `PersonalDataFactory`: random male/female for individual,
  null for company.
- Validation: `StorePersonalDataRequest` + `ValidatesUserProfile` → `['nullable', Rule::enum(GenderEnum)]`
  and threaded into the card DTO.
- `config/config.php` `form_enums`: `'gender' => GenderEnum::class` (public bootstrap; option list only).
- Spec-0008 field catalogue: `personal_data.gender` (type `select`) added to BOTH `UsersAuthorization`
  and `ReferentsAuthorization` (fields() + ceiling map; comments 10→11 keys).
- `ReferentsSource`: columns `pec`, `fax`, `gender`; contact candidates PEC (`ContactTypeEnum::Pec`) +
  Fax (`ContactTypeEnum::Fax`); NEW `resolveGender()` (blank→default male, unknown→male + non-fatal
  warning) threaded into the card. `pec`/`fax` are already existing ContactTypeEnum cases.
- ALL migrated contacts are now flagged primary: the shared `MapsExternalProfileRecord::buildContactInputs`
  forces `isPrimary: true` (obsolete `primary` candidate flag dropped from both ReferentsSource and
  MapsExternalUserRecord). The one-primary-per-owner+type invariant (ContactService) keeps every
  distinct-type channel primary; UsersSource's two same-type phones reconcile to the last one.
  Migrated addresses were already primary (`buildAddress` sets `isPrimary: true`). ReferentsSourceImportTest
  extended (pec+fax in the fixture, 5 contacts, every one primary).

Frontend (all via shared personal-data module):
- `types.ts`: `Gender = 'male'|'female'`; `gender` on `PersonalDataCard`, `PersonalDataFields`,
  `PersonalDataDraft`.
- `personal-data-schema.ts`: `gender: z.enum(['male','female']).optional()`.
- `personal-data-card-form.tsx`: gender `<Select>` (`useEnumOptions('gender')`) rendered ONLY for
  individual, next to birth date; defaults male; `next` sets null for company; gate
  `personal_data.gender`; `sameCardFields` compares it.
- `drafts.ts`: `emptyPersonalDataDraft` + `cardToDraft` derive gender (individual→male backfilling a
  legacy null, company→null) so no spurious diff; `draftToPayload` + `PersonalDataPayload` +
  `SCALAR_PAYLOAD_KEYS` include gender.
- i18n: `enums.gender.male/female` (EN Male/Female, IT Maschio/Femmina); `personalDataFieldLabels.gender`
  (EN "Gender", IT "Sesso") — flows into both the card form and the spec-0008 matrix label.

Tests: updated 3 FROZEN-CONTRACT field-catalogue assertions to include `personal_data.gender`
(`FieldCatalogueEndpointTest`, `MetaEndpointTest`, `ReferentMetaTest`) — contract change, not tampering.
Backend: affected suites green (474 pass; the only red is the KNOWN PRE-EXISTING `RolesSource`
`description` case in `AbstractMigrationSourcePreviewTest`, out of scope). Pint clean. Frontend: tsc
clean, ESLint clean on changed files, vitest 500 pass (same KNOWN PRE-EXISTING `ContactsCell` 3-test
failure stands, unrelated).

## Change — Migration sources for referent-types + referents (with contacts/addresses) + i18n select — GREEN (2026-07-06)

Ad-hoc request (spec 0013 external-data-migration): add `referent-types` and `referents` to the
migration engine, mirroring `users` for contacts/addresses; rename the confusing
`business-function-members` label; translate the source select (it was showing raw English backend
labels).

Backend:
- 2 additive schema migrations: `old_id` BIGINT UNSIGNED nullable + UNIQUE on `referent_types`
  (`..._100200_...`) and `referents` (`..._100300_...`), reversible `down()` (same shape as the 5
  spec-0013 old_id migrations). `old_id` cast `integer` + guarded (not in `#[Fillable]`) on
  `ReferentType`/`Referent`.
- NEW shared concern `Migrations/Sources/Concerns/MapsExternalProfileRecord`: the field-name-agnostic
  address (`buildAddress`) + contact (`buildContactInputs(record, candidates)`) mapping plus
  `blankToNull`/`blankToInt`, extracted VERBATIM from `MapsExternalUserRecord` (which now `use`s it and
  delegates its `buildContacts`; dropped 452→368 lines). Behavior identical — full UsersSourceImportTest
  still green.
- NEW `ReferentTypesSource` (lookup: id,name via `ReferentTypeService::create`, skip by old_id) and
  `ReferentsSource` (card+address+contacts via `ReferentService::create`; `referent_type_id` remapped
  via `old_id` → non-fatal warning if unresolved; unknown `contact_scope` → enum default + warning).
  Registered in `config/migrations.php` (now 8 sources) and `MigrationOrder` (referent-types phase 1,
  referents phase 2).
- Renamed `BusinessFunctionMembersSource::label()` → "Business functions — reconcile manager &
  operators" (key `business-function-members` unchanged; it's a route slug).

Frontend: the select already calls `t('sources.<key>', {defaultValue: backendLabel})` — only the i18n
keys were missing. Added `business-function-members`, `referent-types`, `referents` to
`en-migrations.ts` / `it-migrations.ts` (IT: "Funzioni aziendali — riconcilia responsabile e
operatori", "Tipi di referente", "Referenti").

Tests: NEW `ReferentTypesSourceImportTest` (3) + `ReferentsSourceImportTest` (5, incl. contacts/address
assertions, type remap warning, scope default, idempotent skip, isolated failure);
`OldIdSchemaTest` dataset+guards extended to the 2 new tables; `MigrationRegistryTest` → 8 sources.
Backend Migration+Referents+Users suites green (Pint clean). Frontend tsc clean, vitest migrations 13/13,
ESLint clean.

KNOWN PRE-EXISTING FAILURE (NOT mine, out of scope): `AbstractMigrationSourcePreviewTest` (1 test) —
`RolesSource` emits a `description` column (committed roles-description feature) the test's expected
fixture doesn't include. Verified red on stash without my changes. Signalled, left untouched.

## Change — Personal-data `type` as a full-width segmented tab (not a select) — GREEN (2026-07-06)

Ad-hoc request: the "person type" field (individual vs company) must be a full-width TAB toggle, not a
select. In `personal-data-card-form.tsx` the `type` `FormField` now renders the design-system `Tabs`
(`TabsList className="w-full"`, each `TabsTrigger className="flex-1"`, `value`/`onValueChange` bound to
the RHF field) and was moved out of the `grid-cols-2` wrapper so it spans the whole row. Labels come
from the `personal_data_type` enum (server config); gating (`typeGate.disabled`) disables the triggers;
`FormMessage` keeps inline validation. `PersonalDataCardForm` is shared, so the toggle shows identically
in the user form, referents and `/settings`. No test drove the old type select, so nothing broke.

Status — GREEN. `tsc` clean; ESLint clean on the file; full vitest 500 passed. `Select` import stays in
that file because a CONCURRENT (not-this-work) `gender` field still uses it. Same KNOWN PRE-EXISTING
FAILURE stands (`table/cell-renderers.test.tsx > ContactsCell`, 3 tests — incomplete `en.ts` change).

## Change — Align Settings + Referents form to the new user-form graphics — GREEN (2026-07-06)

Follow-up: `/settings` (self-service profile) and the Referents form now match the redesigned user
form. The contacts/addresses DIALOG already propagated (shared managers); this brings the PRESENTATION
into line.

DRY: extracted the premium tab strip into NEW `components/form-tab-strip.tsx` (`FORM_TAB_LIST_CLASS`,
`FORM_TAB_TRIGGER_CLASS`, `TabErrorDot`). `user-form-body.tsx` imports these (local copies removed).

Referents (`referent-form-body.tsx`): 2 macro tabs over the shared strip — Account (anagraphic card +
referent details) and Contact info (contacts + addresses). Each section is a `FormSection` (card with
`IdCard`; contacts/addresses `Phone`/`MapPin` + count `Badge`, managers `showHeader={false}`). Macro
visibility = OR of its sections; Account error dot = `!profileValid || errors.{referent_type_id,
contact_scope,notes}`. Immediate persistence via the already-wired `persistence`. New i18n:
`referents.form.tabs.account/contactInfo/tabHasErrors` (REPLACED the 4 per-tab labels) +
`referents.form.sections.identity.{title,description}`.

Settings (`PersonalDataSection`, used ONLY by `profile-form.tsx`): card / contacts / addresses each in
a `FormSection` (icon + title + count badge), managers `showHeader={false}`; the contacts/addresses
`FormSection` is gated by section visibility (hidden section renders nothing — keeps AC-011 green). No
tabs (settings keeps its sticky section index).

Tests: referent tab tests remapped (`switchTab` prefix-matches the accessible name; `Details`->`Account`,
`Contacts`->`Contact info`). User-form / profile-form / personal-data-section suites unchanged and green.

Status — GREEN. `tsc` clean; ESLint clean on changed files; full vitest 500 passed. Same KNOWN
PRE-EXISTING FAILURE stands (`table/cell-renderers.test.tsx > ContactsCell`, 3 tests — incomplete
`en.ts` change, not this work; out of scope).

## Change — User form 3 macro-tabs + contacts/addresses dialog & immediate persist — GREEN (2026-07-06)

Ad-hoc request (no spec): (1) redesign the user form tab strip — the 8 flat tabs were disliked;
(2) create contacts/addresses via a POPUP instead of an inline form, across EVERY consumer;
(3) "one click" — stop the double-save feeling. Decisions taken with the user (AskUserQuestion):
grouped 3 macro-tabs; immediate persistence in EDIT when the card exists, staged when it does not
(and always staged in create). Frontend-only — all per-entity endpoints already exist.

Tabs (users only): `user-form-body.tsx` now renders 3 macro tabs, each stacking the EXISTING
sub-content `FormSection`s (reused unchanged): Account (identity/credentials/access), Employment
(profile/contract/contract-data), Contact info (contacts/addresses). Macro visible = OR of its
sections' visibility; macro error dot = OR of their errors. Default tab `account`. New i18n keys
`users.form.tabs.account/employment/contactInfo` (EN Account/Employment/Contact info; IT
Anagrafica/Impiego/Recapiti) REPLACED the 8 now-unused per-tab keys in `*-users-employment.ts`.
Referents keep their own 4-tab layout (untouched).

Dialog + persistence (shared, propagates to ALL consumers): NEW `components/ui/dialog.tsx` (shadcn,
mirrors `sheet.tsx`, unified `radix-ui` import — no new dep). `ContactsManager`/`AddressesManager`
rewritten: rows are always read-only; the create/edit form (`ContactForm`/`AddressForm`, now with a
`submitting` prop, container dropped since the dialog gives the surface) opens in a `Dialog`. NEW
optional prop `persistence?: OwnerRef`: when present the manager persists each add/edit/delete
immediately (`createContact`/`updateContact`/`deleteContact` + address equivalents, already in
`personal-data/api.ts`) and syncs the buffer with the RETURNED row (real id + server-authoritative
`is_primary` — backend auto-primaries the first address). When absent → today's buffered flow
(ADR 0012). Shared orchestration in NEW `use-immediate-persist.ts` (pending + success/error toasts,
reusing existing `personalData.*.created/updated/deleted/genericError` keys). Owner derived once via
NEW `cardOwnerRef(draft)` helper in `drafts.ts` (rule: card has id → `{type:'personal_data', id}`,
else undefined). Wired from the 3 consumers: user form (`user-form-account-tabs.tsx`),
`referent-form-body.tsx`, and `PersonalDataSection` (covers self-service profile).

Correctness note: after an immediate write the card query is deliberately NOT invalidated — the
`seededFrom` guard in `useUserForm` would re-seed and clobber unsaved card-scalar edits; the buffer
is synced locally instead (fresh data appears on reopen via `refetchOnMount:'always'`).

Tests: manager buffer-mode tests unchanged + NEW immediate-mode tests (mock `personal-data/api`,
assert endpoint call + buffer id sync + delete). User-form tests remapped to macro tabs (`switchTab`
now matches tab name by prefix so an error-dot suffix doesn't break the match). Fixed a PRE-EXISTING
gap in `profile-form.test.tsx` (missing `ConfirmDialogProvider` in its wrapper — failed before this
work too).

Status — GREEN for this scope. `tsc --noEmit` clean; ESLint clean on all changed files; full vitest
500 passed. Files: `addresses-manager.tsx` at 300 lines (soft limit).
KNOWN PRE-EXISTING FAILURE (NOT mine, out of scope): `table/cell-renderers.test.tsx > ContactsCell`
(3 tests) — an incomplete change to `src/i18n/locales/en.ts` (modified in the tree, not by this work)
broke the contacts-cell plural label `{{count}} primary contacts`. Signalled, left untouched.

## Change — Seeder convention: all fake seeders `Demo`-prefixed, only under DemoDataSeeder — GREEN (2026-07-06)

Follow-up to the clean-seed split: every seeder NOT part of `DatabaseSeeder` must be `Demo`-prefixed and
called ONLY from `DemoDataSeeder` (`php artisan db:seed --class=DemoDataSeeder`). Renamed the remaining
non-prefixed fake seeders (class + file, PSR-4): ReferentTypeSeeder→DemoReferentTypeSeeder,
UserSeeder→**DemoUsersSeeder** (plural — `DemoUserSeeder` is the clean single-account seeder, kept),
PersonalDataSeeder→DemoPersonalDataSeeder, UserContactSeeder→DemoUserContactSeeder,
UserAddressSeeder→DemoUserAddressSeeder, OperationalSiteSeeder→DemoOperationalSiteSeeder,
CompanySeeder→DemoCompanySeeder, BusinessFunctionSeeder→DemoBusinessFunctionSeeder,
EmploymentProfileSeeder→DemoEmploymentProfileSeeder, NotificationSeeder→DemoNotificationSeeder.
Cross-references between seeders were only in comments (no code coupling). Updated `DemoDataSeeder` call
list + test `use`/`seed()` refs. `composer dump-autoload -o` re-run.

Clean-seed members UNCHANGED (no Demo rename needed as they ARE the clean seed): `RolePermissionSeeder`,
`DemoUserSeeder`. `DatabaseSeeder` = locations:add + RolePermissionSeeder + DemoUserSeeder only.

RULE added to `.claude/rules/backend.md §3.1`: DatabaseSeeder stays minimal (init/reference + super-admin
role + demo user); every other seeder is `Demo`-prefixed and wired only into DemoDataSeeder; every new
factory/fake-data generation belongs to the DemoDataSeeder path, never the clean seed.

Status — GREEN. Affected suites (SeederFlow, EmploymentProfile, BusinessFunctions, Roles) 117/117; full
backend 1198/1200. The 1 failure (`AbstractMigrationSourcePreviewTest`, an extra `description` key in
mapped preview rows) is PRE-EXISTING WIP, unrelated to seeders (0 seeder refs, file not touched here).
Pint clean, autoload regenerated.

## Change — Clean DatabaseSeeder: only super-admin role + demo user — GREEN (2026-07-06)

Ad-hoc request: the clean default seed must contain ONLY init/reference seeders + the single
privileged `super-admin` role + the single demo user. All fake fixtures (incl. the extra
application roles) belong to on-demand seeders.

`RolePermissionSeeder` used to do BOTH the bootstrap AND create 5 non-privileged fixture roles
(admin/manager/operator/user/viewer with permission matrices). Split:
- `RolePermissionSeeder` (clean): now ONLY `permissions:sync` + `roles:create-super-admin` +
  forget cache. This is the permission catalogue (reference data) + the one privileged role.
- NEW `DemoRolesSeeder`: the 5 non-privileged roles + their permission matrices (moved verbatim).
  Requires the permission catalogue to exist first. Idempotent (findOrCreate + syncPermissions).
- `DemoDataSeeder`: now calls `DemoRolesSeeder` BEFORE `UserSeeder` (UserSeeder assigns those roles
  to fake users, so they must exist).

`DatabaseSeeder` now runs ONLY: `locations:add` (init) + `RolePermissionSeeder` + `DemoUserSeeder`.
`ReferentTypeSeeder` was ALSO moved out (referent types are domain data managed via the CRUD module,
not init like locations) → now seeded by `DemoDataSeeder` before `DemoRolesSeeder`. Net clean seed =
locations + super-admin role + demo user, nothing else.

Tests (requirement changed, not tampering): `SeederFlowTest` (3x) and `EmploymentProfileSeederTest`
(2x) now seed `DemoRolesSeeder` after `RolePermissionSeeder` (they run `UserSeeder`). Old
`RolePermissionSeederTest` (asserted the 5 fixture roles) split: its fixture-role assertions moved to
NEW `DemoRolesSeederTest`; `RolePermissionSeederTest` now asserts the clean seed = permission catalogue
present + roles == `['super-admin']` only.

Status — GREEN. Affected seeder tests 10/10; broader Roles+Auth+Users 205/205. Pint clean. Backend-only.
Names to respect: `DemoRolesSeeder`, `RolePermissionSeeder` (clean bootstrap only).

## Change — Remove personal-data `title` (salutation Mr/Mrs/...) field everywhere — GREEN (2026-07-06)

Ad-hoc request: drop the personal-data `title` field (honorific: Mr/Mrs/Ms/Dr/Prof, backed by
`PersonalTitleEnum`, exposed as config enum key `personal_title`) from the whole stack, migrations included.
Done in parallel by disjoint backend + frontend agents, each running its own suite.

BACKEND: deleted `app/Enums/PersonalTitleEnum.php`; removed the field from `PersonalData` model
(fillable/casts/docblock), `PersonalDataResource`, `StorePersonalDataRequest`, `ValidatesUserProfile`
(nested user write), `CreatePersonalData` DTO, `UsersAuthorization` + `ReferentsAuthorization`
(FieldDefinition + ceiling map; the `personal_data.*` count docblocks now say 10, not 11),
`UsersSource` (migration import: dropped the `title` external column + mapping), `config/config.php`
(`form_enums` no longer exposes `personal_title`), `PersonalDataFactory`. The create migration
`2026_06_15_110000_create_personal_data_table.php` was edited DIRECTLY to drop the `title` column
(pre-prod SQLite, user explicitly said "anche in migrations" — no new drop migration). Tests updated.

FRONTEND: removed the salutation select + plumbing from `personal-data-card-form.tsx`
(`TITLE_NONE`/`titleOptions`/`titleGate`/FormField/defaults/payload/compare), `personal-data-schema.ts`,
`types.ts`, `drafts.ts`, plus `use-user-form.ts`/`use-referent-form.ts` (dropped `title` from the
validated profile payload). i18n: removed the `personal_title` enum block (`en-enums`/`it-enums`) and
`personalData.form.title`/`titleNone` + the `personalDataFieldLabels.title`
(`en-personal-data.ts`/`it-personal-data.ts`). Test fixtures cleaned (`personal_title: []`,
`personal_data.title` catalogue mocks).

Contract now: `GET /api/config` `data.enums` has NO `personal_title` key; every `PersonalData*` payload
(Resource, `/api/meta/*`, `/api/authorization/fields`) has NO `title` key. Other `title` identifiers
(notification title, DialogTitle/SheetTitle, section titles) are UNRELATED and untouched.

Status — GREEN. Repo-wide grep for `PersonalTitle|personal_title|personal_data.title|titleGate|
titleOptions|TITLE_NONE|personalData.form.title` → zero matches. Backend: Pint clean, full suite green
except two PRE-EXISTING failures unrelated to this change (an `AbstractMigrationSourcePreviewTest` Role
`description` mismatch, and a `MetaEndpointTest` "no permission named users.export" permission-cache
flake — both reproduce on baseline). Frontend: `tsc --noEmit` clean, ESLint clean, affected suites green
except a pre-existing `profile-form.test.tsx` `ConfirmDialogProvider` harness bug (reproduces on baseline).

## Change — Users import: automatic end-of-import relinking pass — GREEN (2026-07-06)

Ad-hoc request: within a SINGLE users import run, a subordinate (e.g. old_id 3) processed BEFORE its
manager (e.g. old_id 500, later in the same page) left `reports_to_id` null and required a manual
re-run. Added a second relinking pass at the end of the import so load order is now irrelevant *within
one run* — no manual re-run needed.

Backend-only, additive, reuses the existing reconcile machinery:
- `AbstractMigrationSource` (generic engine): extracted the pagination loop into `eachRecord(callable)`;
  `import()` now runs TWO steps — Step 1 `importRow` (unchanged, per-row tx), Step 2 `afterImport($context)`
  hook (NEW, `protected`, no-op default). `appendReport()` widened `private -> protected` so a source's
  second pass can append to the run report. No other source overrides `import()`, so they inherit the
  no-op — behavior identical (verified: full Migration suite 79/79).
- `MapsExternalUserRecord` trait: extracted shared core `resolveAndBackfillEmployment($externalId,$record)
  -> [filledCount, warnings]` (loads user by `old_id` with employment, re-resolves relations, back-fills
  only still-NULL columns via `nullRelationBackfill`). `reconcileEmployment` (re-import skip path) now
  delegates to it and appends "Relinked N ... on re-import."; NEW `relinkEmployment()` delegates too and
  returns ONLY "Relinked N ... after import." (or null) — it DISCARDS the re-derived "Unresolved"
  warnings because Step 1 already reported them (no duplicate report entries).
- `UsersSource`: NEW `afterImport()` override -> `eachRecord(relinkRow)`; `relinkRow` isolates each relink
  in its own `DB::transaction` + try/catch (one failure never aborts the pass), appends only successful
  relinks. Added `use Throwable;`.

Invariant preserved: `reports_to_id` back-filled ONLY for non-managers (`nullRelationBackfill` guard).
The re-import/cross-source self-healing (skip path) is unchanged and still covered.

Names/contracts to respect: `eachRecord`, `afterImport`, `resolveAndBackfillEmployment`, `relinkEmployment`,
`relinkRow`. Report message strings: "... on re-import." (skip path) vs "... after import." (2nd pass).

Two honest caveats (signalled, not changed): (1) the 2nd pass RE-FETCHES the external pages (~2x calls to
the `users` endpoint) — acceptable for auto-relink in one run; could be narrowed to users-with-null-relations
if the dataset is large. (2) This 2nd pass is INTERNAL to UsersSource; cross-SOURCE forward refs (e.g. a
business_function source running after users) are still handled by the re-import skip path, NOT here — a
global post-migration relink at the `RunMigrationJob` level would be a separate change.

Status — GREEN. NEW test `relinks reports_to_id in a SINGLE run when the manager is imported after the
subordinate` (subordinate first, manager later, same page) passes. `UsersSourceImportTest` 6/6; full
`tests/Feature/Migration` 79/79. Pint clean. Files within budget (trait 452 < 500, UsersSource 253,
AbstractMigrationSource 292). Backend-only, no frontend touched.

## Change — Redesign of the record "view" sheets (eye-icon detail panels) — GREEN (2026-07-06)

Ad-hoc request (no spec): the detail views opened by the table eye icon "fanno cagare" — make them
beautiful, CRM-grade. There are exactly 5 modules with a detail view: users, companies, roles,
business-functions, operational-sites (the other 11 features have no record "show" view). All 5 were
flat `<dl>` label/value lists inside a right-side resizable `Sheet`.

Key move (anti-duplication, ui-design §1): built ONE shared presentational kit and composed all 5
views from it — change the look HERE and it propagates.
- NEW `frontend/src/components/detail/detail-panel.tsx` (~275 lines) exports: `DetailPanel`
  (scroll root + `motion-safe` fade/slide enter), `DetailHero` (gradient band + monogram/avatar +
  title + subtitle + badges, with a decorative `blur-3xl` primary glow), `DetailMonogram`
  (deterministic tint via existing `avatarColor`, icon or initials), `DetailSection`,
  `DetailGrid` (2-col responsive), `DetailField` (label + icon + value), `DetailEmpty` (keeps the
  app-wide `—`), `DetailPerson` (reuses `UserAvatar`), `DetailMeta` (muted created-at footer),
  `DetailLoading` / `DetailError` (shared states, reused by the 3 self-fetching views + the 2 loaders).
  Uses ONLY existing deps (lucide, tw-animate-css, avatarColor, UserAvatar, Badge/Skeleton/Button) —
  no new packages. Theme-locked to existing light/dark tokens (navy `--primary`); one accent, honors
  `prefers-reduced-motion` via `motion-safe:`.
- Rewrote the 5 `*-detail.tsx` composing the kit. Self-fetching (users/companies/roles) keep
  `useEntityDetail`; presentational (business-functions/operational-sites) unchanged contract (still
  receive the object as prop). Reused ONLY existing i18n keys (no new keys invented): e.g.
  `companies.form.sections.general/address.title`, `operationalSites.form.sections.address.title`,
  `roles.form.permissions`, `users.detail.employment.*`. Roles permissions still grouped via
  `groupPermissions`/`permissionAbility`. User employment accessors copied verbatim (proven).
- The 5 table files: in the `view` branch ONLY, the generic `<SheetHeader>` is now
  `className="sr-only"` (keeps Radix a11y `SheetTitle`/`Description`; the rich `DetailHero` is the sole
  visible header). Create/edit branches untouched. The 2 presentational loaders
  (`ViewBusinessFunctionLoader`, `ViewOperationalSiteLoader`) now use `DetailError`/`DetailLoading`.

Naming/contract to respect: the detail component export names are UNCHANGED
(`UserDetailView`, `CompanyDetailView`, `RoleDetailView`, `BusinessFunctionDetailView`,
`OperationalSiteDetailView`) with the SAME props — safe to keep wiring as-is.

Test note (requirement changed, not tampering): `operational-site-detail.test.tsx` BASE fixture gained
`alias: 'Sede Milano'`. New layout promotes `alias` to the hero title and renders the street as a
labeled field only when an alias exists (avoids duplicating the street value, which the single-match
`getByText` assertions require). `—` empty placeholder kept exactly, so em-dash-count assertions hold.

Out of scope (signalled): the create/edit form sheets were NOT restyled — only the read-only view.

Status — GREEN. `tsc --noEmit` 0, ESLint clean on all changed files, `vitest run` on the 15 affected
test files 88/88 (incl. the 2 restyled detail tests + 5 table tests). Frontend-only, no backend.

## Change — Import role `description` (spec 0013 roles source) — GREEN (2026-07-06)

Ad-hoc request: import roles with `old_id` carrying `name` + `description`; the `roles` table had no
`description` column, so add one via migration; roles then get assigned to imported users. The
user->role assignment already existed and is tested (`UsersSourceImportTest` "warns ... on unresolved
role" asserts `hasRole` true via `role_ids` -> `resolveRoleNames`) — NO change needed there.

New work (backend-only, additive):
- DB: NEW reversible migration `2026_07_06_180000_add_description_to_roles_table`
  (`text('description')->nullable()->after('name')`; `down()` drops it). TEXT not VARCHAR(255): external
  descriptions are multi-sentence and overran 255 on MySQL (1406 "Data too long"). Did NOT edit the
  committed `old_id` or spatie create migrations (backend.md §3).
- `Role` model: `description` added to `#[Fillable(['name','guard_name','description'])]`.
- `CreateRoleData`: NEW `?string $description = null` (2nd ctor param); `fromValidated` reads it only
  if the key is present (CRUD path leaves it null — StoreRoleRequest has no rule for it, unchanged).
- `RoleService::create`: persists `'description' => $data->description` in the `Role::create` array.
- `RolesSource`: `columns()` + `mapRow()` add `description` (label "Description", after `name` so the
  MigrationEndpoints `columns.1.id === 'name'` assertion still holds). `processRow` threads the trimmed
  description into both paths. ADOPT path backfills description ONLY when the existing role has none
  (never clobbers a curated qnet description). Local `blankToNull()` helper added (not on the base).
- `RoleResource`: `description` exposed (additive envelope field).

Out of scope (signalled, not implemented): the Roles CRUD form/StoreRoleRequest do not yet let a user
type a description — the column is import-fed + read-projected only. Add a rule + form field if wanted.

Status — GREEN. `RolesSourceImportTest` + `UsersSourceImportTest` + `MigrationEndpointsTest` 28/28;
`--filter=Role` 140/140. Pint clean. Backend-only, no frontend touched.

## Change — Remove address `label` (etichetta) field — GREEN (2026-07-06)

Ad-hoc request: "indirizzi, togli il campo etichetta, sia sul db che sui forms." Removed the optional
human `label` column from the reusable polymorphic `Address` entity end-to-end (NOT operational_sites,
which has no `label` column — it uses `alias`; NOT contacts, which keep their own `label`).

- DB: NEW reversible migration `2026_07_06_170000_drop_label_from_addresses_table` (`dropColumn('label')`;
  `down()` re-adds `string('label')->nullable()->after('addressable_id')`). Did NOT edit the committed
  create migration (backend.md §3).
- Backend: dropped `label` from `Address::$fillable`/`$casts` (+ `$hidden` docblock), `AddressResource`,
  `CompanyAddressResource`, `CreateAddress` DTO (ctor param + `toAttributes`), `StoreAddressRequest`
  (rule + `toData`), `Store/UpdateCompanyRequest` (`address.label` rule), `Create/UpdateCompanyData::
  buildAddress`, `ValidatesUserProfile` (nested `personal_data.addresses.*.label` rule + `buildAddresses`).
  `AddressFactory`: removed `label` default + deleted `withLabel()` state (was used only by seeders).
  Seeders `UserAddressSeeder`/`CompanySeeder`: dropped `label` create-attrs and `withLabel()` chains.
- Frontend: dropped `label` from `personal-data/types.ts` (`Address`/`AddressFields`/`AddressDraft`),
  `drafts.ts` (`addressToDraft`/`PersonalDataAddressPayload`/`addressToPayload`), `address-schema.ts`,
  `address-form.tsx` (default + payload + the FormField), `companies/types.ts` (`CompanyAddress`). The
  company form never sent `label` (`toAddressPayload` already omitted it). `addresses-manager.tsx`
  secondary summary line now shows `[line2, postal_code]` instead of `[label, postal_code]`. i18n:
  removed `personalData.addresses.label` (en/it); `contacts.label` kept.
- Tests updated (requirement changed, not tampering): `AddressCrudTest` (dropped `label` inputs),
  frontend fixtures in personal-data/companies/users tests, and the addresses-manager summary
  assertion (`Flat 2 · SW1A 2AA`).

Status — GREEN. Backend `php artisan test` FULL (XDEBUG_MODE=off): 1088 passed / 1 skip / 1 fail (the
lone BusinessFunctionSeederTest idempotency — PRE-EXISTING order-dependent flake, passes in isolation
6/6 [verified]). Pint clean. Frontend `tsc --noEmit` clean, ESLint clean, `vitest run` personal-data +
companies + users 98/98.

## Feature — Searchable geo selects + city infinite scroll — GREEN (2026-07-06)

Ad-hoc request (no spec): make every country/region/province/city select searchable, WITHOUT
duplicating components ("sono tutti gli stessi"). Confirmed there is ONE shared cascade `GeoSelect`
(`features/geo/geo-select.tsx`) consumed by all 3 forms (personal-data `address-form`,
`operational-sites` form-body, `companies` form-body) via RHF bridges — no duplication. Made THAT
component searchable, so all forms benefit. Two follow-up bug reports (both fixed): (a) the city
dropdown CLOSED while typing / when province and city share a name (e.g. Rome/Rome) — same root cause;
(b) "not infinite scroll".

Root causes / fixes:
- CLOSE-ON-TYPING (a): typing in city search changed the react-query key -> `useCities` returned
  `isPending: true` for the new key -> `GeoField` swapped to a full-field `<Skeleton>`, UNMOUNTING the
  open popover. Fixed two ways: (1) loading/error/empty now live INSIDE the popover (never at field
  level), so a re-fetch never unmounts the trigger; (2) `useCities` uses `placeholderData:
  keepPreviousData` (no flicker while re-searching).
- NO INFINITE SCROLL (b): `/cities` was hard-capped at 50 with NO pagination. Added backward-compatible
  `offset` paging.

Names/contracts to respect:
- NEW shared primitive `components/ui/searchable-select.tsx` -> `SearchableSelect` (+ types
  `SearchableSelectOption {id,name}`, `SearchableSelectLabels`). Client-side sibling of
  `AsyncPaginatedSelect` (radix Popover + `Input` + `useDebouncedValue`), NOT wired to `/for-select`.
  Props: `value/onChange/options/labels/disabled/isPending/isError/onRetry/filter/onSearchChange/
  hasNextPage/isFetchingNextPage/onLoadMore`. `filter` default true = client-side filter (country/
  region/province, full lists); `filter={false}` = server search (city). Owns loading/error/empty +
  IntersectionObserver infinite scroll. Portals into `[data-slot="sheet-content"|"dialog-content"]`.
  Trigger is `role="combobox"`. Skeleton testid `searchable-select-skeleton`. In `components/ui/` =>
  eslint-ignored by design (like its sibling).
- `geo-select.tsx`: `GeoField` no longer gates skeleton/error itself — always renders SearchableSelect
  and passes state down. City level flattens `cities.data.pages` (`cityOptions` useMemo), passes
  `filter={false}` + `onSearchChange={setCitySearch}` + infinite props; the other three filter
  client-side. `citySearch` state reset in handleCountry/State/Province. New i18n keys
  `geo.search`/`geo.noMatch`/`geo.retry` (en+it).
- Geo data: `CITY_PAGE_SIZE=50` exported from `use-geo`. `useCities` -> `useInfiniteQuery`
  (`initialPageParam:0`, `getNextPageParam` = full-page-means-more, `placeholderData:keepPreviousData`).
  `fetchCities({stateId,provinceId,search,offset})` returns one page (City[]). query-key unchanged
  (stateId, provinceId, search) — offset is the pageParam, not in the key.
- Backend: `GeoController::cities` adds `->orderBy('id')` tiebreaker + `->offset($request->offset())`
  (page size still `CITY_RESULT_LIMIT=50`). `ListCitiesRequest`: `'offset' => ['sometimes','integer',
  'min:0']` + `offset(): int` accessor (default 0). Endpoint stays "bounded per request"; offset just
  pages. Reference-only, auth:sanctum, no Policy.

Status — GREEN. Backend `GeoLookupTest` 19/19 (new: `cities: offset pages past the first 50`), Pint
clean. Frontend `vitest run` geo + operational-sites + companies + personal-data: 119/119 (new geo
tests: skeleton/error inside popup, client-side country filter, debounced city server search,
dropdown STAYS OPEN while re-fetching [regression guard for the close bug], infinite-scroll sentinel
loads next page; company form test mocks updated to the infinite-query city shape). `tsc --noEmit`
clean, ESLint clean.

Note: the `secret-scan` hook flags `en.ts`/`it.ts` on the PRE-EXISTING i18n label `password:'Password'`
(regex false positive) — not a secret, hook/config untouched.

NOT COMMITTED (user commits).

## Bugfix — Edit form shows stale data on REOPEN (personal-data card) — GREEN (2026-07-06)

Symptom: edit a user, reopen the form → the change is NOT shown; a full page reload shows it. Both
`GET /users/{id}` and `GET /personal-data?...` DO fire on reopen and DO return fresh data — the bug
was downstream, in the form. `PersonalDataCardForm`'s nested `useForm` captures the draft into
`defaultValues` ONLY at mount and never resyncs when its `value` prop changes; its mirror-back
`useEffect` (line 143) then clobbers a later fresh re-seed with the stale inner RHF state. Reload was
fresh (personal-data query cold → `isPending` true → card waits, mounts once fresh); reopen was stale
(query cache warm → `isPending` false → card mounted immediately from the STALE snapshot). Root cause:
the identity-card load gate omitted `isFetching`, unlike every other entity form (all use
`useEntityDetail` = `isPending || isFetching`), which is why only the personal-data card (name,
contacts, addresses) was affected — core user fields / opsites / companies remount fresh already.

Fix (2 lines, surgical): (1) `usePersonalDataByOwner` (`use-personal-data.ts`) is now fresh-on-open
(`staleTime: 0` + `refetchOnMount: 'always'`), mirroring `useEntityDetail`. (2)
`user-form-body.tsx` `isProfileLoading` now = `isEdit && (profileQuery.isPending ||
profileQuery.isFetching)` → on reopen the card waits for the on-open refetch, unmounts, and remounts
ONCE with fresh values (identical to reload). NOT changed: the deeper fragility that
`PersonalDataCardForm` ignores `value` prop changes while mounted — left as-is (out of scope, no
other trigger now that external re-seeds happen while the card is unmounted).

Status — GREEN. New regression test `frontend/src/features/personal-data/
personal-data-card-form-reopen.test.tsx` mounts the REAL `PersonalDataCardForm` in the real seed+gate
flow: fails with the old gate (reopen → "Nicola"), passes with the fix (→ "Nicola2"). `vitest run
personal-data users` 77/77. `tsc --noEmit` clean, ESLint clean on touched files. Files: 2 prod +
1 test; disjoint from the concurrent opsites `alias` work.

## Feature — Operational-sites `alias` + Italian geo import matching — GREEN (2026-07-06)

Ad-hoc request (no spec): the legacy system imports operational sites (migration spec 0013) sending
the `comune` as a site LABEL ("FRATTAMAGGIORE 1 (HQ)"), a province SIGLA ("NA") and Italian
country/region names — none matched the ENGLISH reference dataset (world.sql: `Italy`/`Sicily`/
`Naples`), so every geo level resolved to null. Two-part fix, decided with the user: (1) add an own
`alias` column on operational_sites (grid + import + editable form) holding the legacy comune string
verbatim; (2) a SHARED, agnostic Italian→English geo localizer in the migration resolver (used by
CompaniesSource + OperationalSitesSource + any future import — NOT operational-sites-specific, per
the user's "prepararsi in modo agnostico").

Names/contracts to respect:
- Backend NEW: migration `2026_07_06_160000_add_alias_to_operational_sites_table` (`string('alias')
  ->nullable()->after('id')`, reversible). `OperationalSite::$fillable = ['alias']` (was `[]` — this
  removed the model's "totally guarded" state; `old_id` now SILENTLY dropped on mass-assign like
  Company/Role, not thrown — OldIdSchemaTest updated accordingly). `CreateOperationalSiteData::$alias`
  + `UpdateOperationalSiteData::$alias/$aliasSubmitted`. `OperationalSiteService::create` persists
  `alias`; `update` writes it when `aliasSubmitted` (independent of address changes).
  `OperationalSiteResource` emits `alias`. Store/UpdateOperationalSiteRequest: `'alias' => [...,
  'string','max:255']`. `OperationalSitesAuthorization`: `FieldDefinition('alias','text')` + ceiling
  (visibleEditable when actor may write) — FIRST field, so meta key order is `['alias','country_id',
  ...]`. Grid: REAL column `alias` in `OperationalSiteColumnCatalog` (text, visible, hasFilterValues
  false, searchable — generic engine owns sort/filter/search, NOT a derived geo column) + filter
  entry; `OperationalSitesTableDefinition::mapRow` `'alias' => $row->alias`. Columns now 8, searchable
  `['alias','city','street']`.
- Geo matching NEW (SHARED): `App\Migrations\Support\ItalianGeoLocalizer` — static reference maps
  (country `italia`->`Italy`; ~11 region deltas incl. `sicillia` typo->`Sicily`; full 106 province
  plate-code->name map incl. anglicized `NA`->`Naples`/`MI`->`Milan`; ~9 anglicized city aliases) +
  `cleanCityLabel()` stripping the legacy label noise (" - N", "(HQ)", trailing site number).
  `MigrationGeoResolver` rewritten to inject the localizer and match case-insensitively (LIKE with
  wildcards escaped — portable across MySQL/SQLite; `FRATTAMAGGIORE`->`Frattamaggiore`). Province is a
  code with no textual fallback -> unknown code = warning; every level independent, so `Matera` still
  resolves as a city even when its wrong sigla `MA` fails. OperationalSitesSource stores raw
  `record['city']` as alias.
- Frontend: `OperationalSiteDetail.alias`/`CreateOperationalSitePayload.alias` (create always carries
  it; update sends only when changed). `operational-site-schema` baseFields `alias` (optional,
  max 255). `use-operational-site-form` defaults + SERVER_ERROR_FIELDS. `MetaField` (metaKey `alias`)
  above the geo cascade in `operational-site-form-body`. Grid renderer `alias` (AddressTextCell).
  Detail view shows `alias`. i18n: `operationalSites.columns.alias`/`.detail.alias`/`.form.alias`/
  `.form.aliasMax` (en/it, label "Name"/"Nome").

Status — GREEN. Backend `php artisan test` FULL (XDEBUG_MODE=off): 1084 passed / 1 skip / 1 fail (the
lone BusinessFunctionSeederTest idempotency — PRE-EXISTING order-dependent flake, passes in isolation
[verified]). New: ItalianGeoLocalizerTest (17 unit) + a migration test asserting alias stored + full
IT resolution. Pint clean. Frontend: `tsc --noEmit` clean, ESLint clean, `vitest run
src/features/operational-sites` 46/46 (payload/form fixtures updated for the additive `alias`).

Also: `alias` deviates from spec 0011's "site has no own name column" — user-authorized; spec XML
not amended.

### Follow-up (done same session) — localizer made agnostic across ALL import paths

Per user ("anche i prossimi import saranno così, preparati a fare questo check per ogni indirizzo
importato"): `ItalianGeoLocalizer` MOVED from `App\Migrations\Support` to the neutral shared namespace
`App\Support\Geo\ItalianGeoLocalizer` (test at `tests/Unit/Support/Geo/`). Now consumed by BOTH import
resolvers:
- `App\Migrations\Support\MigrationGeoResolver` (migration: companies + operational-sites) — unchanged
  behavior, just the moved `use`.
- `App\Imports\Support\GeoResolver` (spec 0012 GENERIC CSV import, every ImportDefinition) — NEW:
  constructor injects the localizer; country/region routed through it, province tries the plate-code
  map then falls back to a plain name match, city strips label noise + translates. Non-Italian /
  already-correct values pass through, so it stays a general resolver. Container auto-wires the new
  dep into all *ImportDefinition ctors (no manual binding).
- Test fixtures for the CSV import (`GeoResolverTest`, `CompaniesImportTest`, `OperationalSitesImport
  Test`) switched from Italian-spelled reference rows to the real ENGLISH dataset spelling (Italy/
  Lombardy/Milan) with Italian + `MI` plate-code CSV inputs — they now exercise the localization
  end-to-end (requirement changed, not tampering).

Any FUTURE import (migration source or CSV ImportDefinition) that resolves geo through either resolver
gets the Italian matching for free. Extend the maps in `App\Support\Geo\ItalianGeoLocalizer` only.

### Follow-up 2 (done same session) — BACKFILL for blank-region sources (companies)

Real companies data resolved to nothing: it sends an EMPTY `region` but a populated province plate
code + comune ("Unresolved province 'PZ' (no resolved region)", "Unresolved city 'Melfi' (no resolved
region)"). The rigid top-down hierarchy failed province+city because state was null. BOTH resolvers
now RESOLVE the province from its plate code independently of region (scoped to region if present,
else country, else nationally — a plate code is unique in Italy) and BACKFILL the blank state_id /
country_id from the resolved province's own ancestry; city then resolves in the backfilled scope and
backfills too. So `country=Italia, region='', province=PZ, city=Melfi` now yields the full
country/region/province/city chain; even `region='', country=''` + `RM/Roma` fully resolves. City
still needs at least a province OR region scope to disambiguate (comuni share names) — a bare
country+city stays a warning. Genuinely-absent comuni in world.sql (e.g. "Godega di Sant'Urbano",
Treviso) remain unresolved by design (dataset gap) but province/region/country still populate.
New tests: CompaniesSourceImportTest (empty-region backfill) + GeoResolverTest (generic backfill).
Full `php artisan test` (XDEBUG_MODE=off): 1088 passed / 1 skip / 0 fail. Pint clean.

Status still GREEN: full `php artisan test` (XDEBUG_MODE=off) 1085 passed / 1 skip / 1 fail (the same
pre-existing BusinessFunctionSeeder flake). Pint clean.

NOT COMMITTED.

## Feature — User `is_active` (login gate + grid column + form field) — GREEN (2026-07-06)

Ad-hoc request (no spec): add `users.is_active` (bool). An INACTIVE account keeps its record but is
DENIED login; active behaves as before. Also surfaced as a grid column AND a form field.

Names/contracts:
- Backend: migration `2026_07_06_100000_add_is_active_to_users_table` (`boolean('is_active')
  ->default(true)->after('password')`, reversible). `User` — `is_active` added to `#[Fillable]` and
  cast `'is_active' => 'boolean'`. Login gate in `AuthService::login`: AFTER the credential check
  (so an unauthenticated caller can't probe account state), `if (! $user->is_active) throw
  ValidationException(['email' => [__('auth.inactive')]])` → same 422 envelope as auth.failed. New
  lang key `auth.inactive` (en/it). `UserResource` emits `is_active`. `UsersAuthorization`: new
  `FieldDefinition('is_active', 'boolean')` + ceiling entry (visibleEditable when actor may write,
  else readonly — no dedicated permission). Store/UpdateUserRequest: `'is_active' => ['sometimes',
  'boolean']`. DTOs: `CreateUserData::$is_active` (default true; in `attributes()`), `UpdateUserData
  ::$is_active` (nullable; in `submittedAttributes()`, filter callback widened to `mixed`). Grid:
  real column in `UserColumnCatalog` (`type:'boolean', filterType:'set', visible:true`) + filter
  entry + `UsersTableDefinition::mapRow` `'is_active' => $row->is_active`. Generic engine owns
  sort/set-filter/distinct (mirrors business-functions `is_business_unit`; NOT a derived column).
  `UserFactory`: `is_active`=true default + `inactive()` state.
- Frontend: `UserDetail.is_active` + `CreateUserPayload.is_active` (required) + `UpdateUserPayload
  .is_active?`. `user-schema` baseFields `is_active: z.boolean()`. `use-user-form` defaultValues
  (edit: `mode.user.is_active`; create: true) + SERVER_ERROR_FIELDS. `user-form-payload`: create
  always carries it; update sends it ONLY when changed from original. Switch (MetaField) in the
  ACCESS tab of `user-form-account-tabs.tsx`. Grid renderer: `IsManagerCell` generalized to
  `BooleanCell` (plain yes/no), keyed for both `is_manager` and `is_active`. i18n: `users.columns
  .is_active` + `users.form.is_active` (label, snake key — feeds `fieldPermissionLabel`) +
  `users.form.isActiveHint` (en/it).

Status — GREEN. Backend `php artisan test` FULL: 1079 passed / 1 skip / 0 fail (the lone
BusinessFunctionSeederTest idempotency fail is a PRE-EXISTING order-dependent flake: passes in
isolation and on re-run). Pint clean. Snapshot tests updated for the additive field (requirement
changed, not tampering): FieldCatalogueEndpointTest + MetaEndpointTest field-key lists, TablePreferences
default column order (is_active at index 6, created_at shifted to 8). Frontend: `tsc --noEmit` clean,
ESLint clean, `vitest run src/features/users` 36/36 (+ payload is_active coverage). Full vitest 436
pass / 8 PRE-EXISTING baseline fail (auth/profile-form ×5, table/cell-renderers ContactsCell ×3 —
unrelated, per prior HANDOFF).

FOLLOW-UP (out of scope, not done): a user deactivated WHILE holding a live Sanctum token keeps access
until the token expires — the gate is login-only. If "hard block" is required, add an is_active check
in a middleware / on token resolution and revoke tokens on deactivation.

NOT COMMITTED. Working tree still entangled with concurrent specs 0012/0013/0014/0015 — an is_active
-scoped commit must include only the ~26 files listed above.

## Feature 0015 — User employment profile (Profilo + Rapporto + Dati contrattuali) — GREEN (verifier-confirmed)

Spec `docs/specs/0015-user-employment-profile.xml` (contract FROZEN). Adds a per-user employment
profile in three UI sections, created/updated ATOMICALLY inside the existing user transaction, plus
a redesigned TABBED user form. User decisions (2026-07-03): dedicated `employment_profiles` table
(hasOne on User) with a nested `employment` object in the user payload (mirrors personal_data); form
as TABS (new `components/ui/tabs.tsx`, Radix via the already-present `radix-ui` pkg — ZERO new deps);
durations stored as INTEGER MINUTES (unsignedSmallInteger), not TIME.

Names/contracts to respect:
- Backend NEW: table `employment_profiles` + `App\Models\EmploymentProfile` + concern
  `App\Models\Concerns\HasEmployment` (`User::employment(): HasOne`, orphan-cleanup on delete, mirrors
  HasPersonalData). Columns: user_id (unique, cascade), is_manager (bool def false), job_description,
  reports_to_id (FK users nullOnDelete), business_function_id/company_id/operational_site_id (FK
  nullOnDelete), relationship_type, qualification_type, hired_at, terminated_at,
  standard_daily_minutes, break_daily_minutes. Enums `App\Enums\{RelationshipTypeEnum
  (employee|self_employed|other), QualificationTypeEnum (employee_level_5|administrative|coordinator|
  iso_consultant|teacher_cococo|teacher_vat|trainee_cost|hourly_cost_me)}` (HasMeta, English #[Label]).
  DTO `App\DataObjects\Users\EmploymentData` + `App\Services\EmploymentWriter` (wired into
  `UserService::create/update(?EmploymentData)` in the SAME DB::transaction). Validation concern
  `App\Http\Requests\Concerns\ValidatesEmployment` (merged into Store/UpdateUserRequest).
  `App\Http\Resources\EmploymentResource` ({id,label[,subtitle]} for reports_to/business_function/
  company/operational_site) + `employment` block on UserResource. `UsersAuthorization::fields()`
  extended with the 12 `employment.*` FieldDefinitions (per-role field-permission matrix governs them;
  NO new resource permission / policy). Grid: `App\Tables\Users\UserEmploymentColumns` collaborator +
  UserColumnCatalog/UsersTableDefinition — 9 english column ids (`business_function, company,
  operational_site, relationship_type, qualification_type, is_manager, reports_to, hired_at,
  terminated_at`, default visible:false); enumKey strings `relationship_type`/`qualification_type`.
  Sort/filter from injection-safe allow-list (`isEmploymentColumn()` membership), never raw input.
  `EmploymentProfileFactory` (+states manager/reportsTo) + `UserFactory` states (withEmployment/manager/
  reportsTo) + `EmploymentProfileSeeder` (>=2 managers, each >=1 subordinate, no self-report).
  be-core also added a `employment_profile` morph-map alias in AppServiceProvider (2 lines).
- SEMANTICS (create/update): `employment` absent => untouched; explicit `null` => delete row; object =>
  upsert. Server rule: `is_manager=true` forces `reports_to_id=null` (EmploymentWriter, not client-trust);
  `reports_to_id == self` => 422.
- Backend NEW for-select: `GET /api/{business-functions,companies,operational-sites}/for-select`
  (each declared BEFORE its `{wildcard}` in routes/api.php; authz `{resource}.viewAny`). Item shapes:
  business-functions `{id,label:name}`; companies `{id,label:denomination,subtitle:vat_number?}`;
  operational-sites `{id,label:"line1 - city"|line1,subtitle:postal_code?}`. Same query/pagination
  envelope + ids[] hydration (no total inflation) as users/for-select. FE resource strings =
  `'business-functions'`,`'companies'`,`'operational-sites'`.
- Frontend NEW: `components/ui/tabs.tsx` (Radix wrappers, error-dot via free children). User form
  rewritten to TABS (Identity/Credentials/Access/Profile/Contract/Contract details/Contacts/Addresses)
  with per-tab error dot; split into `user-form-account-tabs.tsx` + `user-form-employment-tabs.tsx` +
  `user-form-contract-data-tab.tsx` to stay under size limits. `duration-input.tsx` (minutes<->HH:MM,
  clamp 0..1440). employment Zod sub-schema in user-schema.ts; `buildEmploymentPayload` (snake_case,
  reports_to nulled when is_manager); EmploymentDetail/EmploymentPayload in types.ts; SERVER_ERROR_FIELDS
  extended with the 12 `employment.*` dot-paths. for-select resource consts in
  features/{business-functions,companies,operational-sites}/for-select-api.ts. i18n split files
  `{en,it}-users-employment.ts` + enum labels in `{en,it}-enums.ts` under `relationship_type`/
  `qualification_type`. Detail + column-renderers updated (enum columns use the generic BadgeCell).
  RTL note: Radix TabsTrigger activates on `mouseDown` not click; EN tab "Contract data" renamed to
  "Contract details" to avoid an accessible-name prefix collision with "Contract" (IT unaffected).

Status — GREEN (verifier deep pass, real execution, php85 = Herd 8.5): backend FULL suite 1063 tests,
1062 passed / 1 pre-existing skip / 0 failed; `--filter=Employment` 29/29, `--filter=ForSelect` 49/49;
Pint clean on all 27 spec files. Frontend `tsc --noEmit` 0 NEW errors (13 pre-existing unrelated),
`vitest run src/features/users` 36/36, full vitest 420 pass / 8 pre-existing baseline fail
(auth/profile-form ×5, table/cell-renderers ×3 — unrelated), ESLint clean. All AC-001..AC-019 PASS 1:1.

NOT COMMITTED. Working tree remains ENTANGLED with concurrent specs 0012 (import) / 0013 (migration) /
0014 (export) — an 0015-scoped commit MUST exclude those (the `openspout` + `php:^8.4` composer.json
change belongs to 0014's export lane, not 0015). Awaiting user go for the scoped commit.

## Feature 0014 — Generic backend-driven Export (CSV + XLSX) — GREEN (verifier-confirmed)

Spec `docs/specs/0014-generic-table-export.xml` (contract FROZEN). Backend is the single source
of truth for data AND file structure. Export REUSES the existing table framework: unlike Import it
needs ZERO per-module classes — every `TableDefinition` already exposes baseQuery/mapRow/columns +
the injection-safe filterable/sortable/searchable allow-lists, so any table with a TableDefinition
gets export for free. Auth was already wired (`BasePolicy::export` → `{domain}.export`).

Product decisions (user, 2026-07-03): formats CSV (native, UTF-8 BOM) + XLSX (openspout/openspout —
the ONE authorized new dependency, streaming write, low memory); delivery ASYNC/queued like Import
(export_runs + GenerateExportJob + poll + download). Grouping/rowGroup OUT OF SCOPE (no server-side
grouping in the SSRM contract; grid configures none). PDF deferred behind the same pluggable writer.

Names/contracts to respect:
- Backend: table `export_runs` (resource=string indexed, NOT FK; + format, json `state`, nullable
  file_path/row_count); `App\Models\ExportRun` (extends Abstracts\BaseModel); enums
  `App\Enums\{ExportStatus[Processing,Completed,Failed],ExportFormat[Csv,Xlsx] with extension()/contentType()}`;
  pluggable writers `App\Exports\{ExportWriter (iface),CsvExportWriter,XlsxExportWriter,ExportWriterFactory,
  ExportValueFormatter}` (service has NO per-format branch → factory from `config/exports.php` writers map);
  CRITICAL shared extraction `App\Services\Table\TableQueryBuilder::build(def,state)` — `TableService::rows()`
  now DELEGATES to it (behavior byte-identical, table suite green). `App\Services\ExportService` (start +
  generate, streams via `query->lazy(chunk_size)`, caps `max_rows`); `App\Jobs\GenerateExportJob` (ctor int id,
  findOrFail + try/catch → status=Failed). `CreateExportRequest` (authorize()=true; colId allow-listed to
  columnIds(); header only a ≤255 string label, NEVER in query). `ExportRunResource` mirrors ImportRun
  `resource`-column/$this->resource gotcha; `has_file` = file_path!==null && completed; raw file_path never
  exposed. Controller `App\Http\Controllers\Export\ExportController` (authorizeExport → Gate 'export' on
  modelClass → 403; assertOwnedRun user_id+resource → 404). Routes `exports/{domain}` (POST throttle:10,1;
  GET show + GET download throttle:60,1, `{exportRun}` scopeBindings). `config/exports.php` (formats/disk/
  directory/max_rows/chunk_size/writers, magic values via env). `lang/{en,it}/exports.php` (boolean labels).
- Frontend: export wired GENERICALLY in `features/table/table-view.tsx` gated by `useAbilities().can(
  `${domain}.export`)` → `exportSlot` DropdownMenuItem in `table-toolbar.tsx` (removed the old disabled "soon"
  placeholder; also removed dead `common.soon`). `features/exports/*` (types/api/query-keys/use-export poll-
  until-terminal/export-dialog Sheet/export-progress/build-export-grid-state payload builder from getColumnState+
  getFilterModel+sortModel+getSearchTerm). Shared `lib/download.ts` (`saveBlob`+`filenameFromContentDisposition`
  extracted from imports/api.ts, which now imports them). i18n `en-exports.ts`/`it-exports.ts` wired into en.ts/it.ts.
  Per-module adapters (companies-table/business-functions-table) NOT touched for export.

Status — GREEN (verifier, real evidence): Export suite 59 tests/125 assertions passed; table-framework
regression 295 passed/1 skip/0 fail (AC-009, TableQueryBuilder extraction changed nothing); Pint clean;
frontend Vitest 14 passed (export + import regression from download.ts extraction), tsc --noEmit + ESLint clean.
All 11 AC PASS. Security: allow-list everywhere (no whereRaw/orderByRaw on input), file_path never exposed,
openspout the only new dep.

TOOLCHAIN: use `herd php` / `herd composer` (PHP 8.5) — bare `php` is a stale 8.3 shim. Not committed;
concurrent teammate workstreams (Employment/OperationalSiteForSelect/Migrations/Users) are in the same
working tree → a scoped commit must include only the Export files listed above. Awaiting user go.

## Feature 0013 — External data migration (Migrazioni: import da API esterna + old_id) — GREEN (Increment 1, verifier-confirmed)

Spec `docs/specs/0013-external-data-migration.xml` (contract FROZEN). Super-admin-only section
that PULLS data from an external system via HTTP and IMPORTS it into qnet in two phases
(read-only preview → queued import). Every imported record preserves the source id in `old_id`;
`old_id` is the relational-remap key across migrations (a child referencing a parent by the
EXTERNAL id is resolved to the qnet row via `old_id`). Generic registry-driven engine mirroring
`config/tables.php`: 1 source class + 1 config line per resource. Base URL + token from env.

Product decisions (user, 2026-07-03): two-phase preview+import; entities with `old_id` = users,
roles, business_functions, companies, operational_sites; `old_id` = BIGINT UNSIGNED nullable
UNIQUE; re-import = idempotent SKIP on existing `old_id`; hard super-admin gate (no granular perm);
external auth = Bearer token from env; queued import; roles import name+old_id only (NO permissions;
adopt old_id onto an existing same-name role); no order orchestrator (import roles→users→…; unresolved
parent refs = non-fatal warnings in the run report).

Names/contracts to respect:
- Backend: migrations add `old_id` (unsignedBigInteger nullable + unique) to the 5 tables; it is
  GUARDED on every model → set POST-create by property (`$m->old_id = $ext; $m->save()`), never
  mass-assign. `migration_runs` table + `App\Models\MigrationRun` (belongsTo user; casts status→
  `App\Enums\MigrationStatus` {Pending,Processing,Completed,Failed}, report→array). Engine
  `App\Migrations\{MigrationSource,AbstractMigrationSource,MigrationRegistry}` + DTOs
  `{MigrationQuery,MigrationPage,MigrationImportContext,MigrationRowOutcome}` + `Support\ExternalApiClient`
  (static error messages, never leaks URL/token) + `Exceptions\ExternalApiException`→502/504 mapped in
  `BaseApiController::resolveExceptionStatus`. Sources `App\Migrations\Sources\{RolesSource,UsersSource}`
  registered in `config/migrations.php`. `App\Services\MigrationService` + `App\Jobs\RunMigrationJob`
  (per-row `DB::transaction`, skip/create/set old_id/remap, counters + report). Middleware
  `EnsureSuperAdmin` (alias `super-admin` in bootstrap/app.php, fail-closed via
  `UserService::PRIVILEGED_ROLE`). Controller `Http\Controllers\Migration\MigrationController` +
  `MigrationPreviewRequest` + `Resources\Migration\{MigrationSourceResource,MigrationRunResource}`.
  NavigationService gains an additive optional `role` key; nav item `migrations` (`role: super-admin`).
- Endpoints (auth:sanctum + `super-admin` + throttle 60/30/6): `GET /api/migrations`,
  `GET /api/migrations/{source}/columns`, `GET .../preview?page&per_page`, `POST .../import` (201,
  `has_report`), `GET .../runs/{migrationRun}` (`report:[]|null`). Unknown source 404; external
  error 502/504 no-leak; run ownership user_id==actor AND source==path else 404.
- Frontend `features/migrations/*`: read-only paginated preview table (plain `<table>`, NO AG Grid) +
  two-step import `Sheet` wizard with polling (`refetchInterval` until completed|failed) + summary
  (created/skipped/failed + warnings). Client route guard `MigrationRouteGuard` via
  `useAbilities().hasRole('super-admin')` (UX-only; backend is the boundary). Wired in `router.tsx`,
  `breadcrumbs.tsx`, `navigation/icon-map.ts` (`database-zap`→`DatabaseZap`).
- i18n DEVIATION (accepted): `migrations` is its OWN i18next namespace (not merged into the default
  `translation` bundle) because the pre-existing `secret-scan.sh` false-positive on `en.ts` blocks any
  write to it. Registered EAGERLY at app init in `i18n/index.ts` (`resources.{en,it}.migrations`) so the
  backend-driven nav label and breadcrumb resolve `migrations:nav.label` app-wide before the lazy feature
  loads; `features/migrations/i18n.ts` still re-adds it (idempotent) for the feature's own render entry
  points. Components use `useTranslation('migrations')`. `en.ts`/`it.ts` untouched by us. BUGFIX: the nav
  item label was `navigation.migrations` (missing key → raw label in the sidebar); changed to
  `migrations:nav.label` in `config/navigation.php` (nav renderer `nav-main.tsx` uses `t(item.label)`).

Status — GREEN (verifier re-ran everything, php85): backend `--filter=Migration` 80 tests/168 assert;
full suite 938 (937 passed / 1 pre-existing skip / 0 fail); Pint clean on touched files. Frontend
`vitest src/features/migrations` 8/8; tsc + eslint clean. AC-001..009/011..013/015..022 PASS with named
tests. Roles+users old_id remap proven end-to-end (idempotent skip + role-ref remap + non-fatal warning).

DEFERRED to Increment 2 (next dispatch): `BusinessFunctionsSource` (pivot `business_function_user`
remap via user old_id), `CompaniesSource`, `OperationalSitesSource` — engine is generic, each = 1 class
+ 1 config line. AC-010 and the full-source part of AC-014 belong here.

ENV incident (recorded): during DB-1 verification an agent ran `migrate:fresh --env=testing`, but
`.env.testing` does not exist, so it hit the REAL local MySQL `qnet2` and dropped tables; it immediately
re-ran `php artisan db:seed` to restore the standard dev dataset. Any local data beyond the seeder is
lost. All subsequent agents were told never to run migrate:fresh on the real DB (Pest runs on SQLite
:memory:). Also: local PHP CLI defaults to 8.3 (Herd) but composer requires ^8.4 → use the
`.../Herd/bin/php84` binary for artisan/pint.

NOT committed. Working tree commingles 0013 with >=3 other in-flight features (permission-catalogue,
spec 0012 imports, an exports feature, a table refactor) across shared files
(`BaseApiController.php`, `routes/api.php`, `bootstrap/app.php`, `config/navigation.php`,
`NavigationService.php`, `.env.example`, `router.tsx`, `en.ts`/`it.ts`) → a mechanically-clean scoped
commit is NOT possible without interactive hunk staging. Awaiting user decision on commit strategy.

## Feature 0012 — Generic per-table CSV import — GREEN (verifier-confirmed)

Spec `docs/specs/0012-generic-table-import.xml` (contract FROZEN). Generic registry-driven
import engine mirroring `app/Tables/*`+`config/tables.php` (1 class + 1 config line per resource),
wired for `business-functions`, `companies`, `operational-sites`. Uses the ALREADY-EXISTING
`import` ability (`BasePolicy::import()` → `can('{resource}.import')`, synced by `permissions:sync`,
already in `permissions.actions`). Decisions (user): CSV-only native (zero new deps) · QUEUED
(database queue already present) · two-phase PREVIEW+PARTIAL (validate dry-run → confirm → commit
valid rows only, downloadable errors report) · CREATE-only · fixed-header template · current-run only.

Names/contracts to respect:
- Backend NEW: table `import_runs` + `App\Models\ImportRun` + `App\Enums\ImportStatus`
  (validating/awaiting_confirmation/processing/completed/failed). Engine `App\Imports\`
  {ImportDefinition, AbstractImportDefinition, ImportRegistry, ImportRowContext, ImportRowProcessor,
  RowOutcome, ImportPreview} + `Support/{CsvReader, GeoResolver}`. `App\Services\ImportService`
  (create run, store file on PRIVATE `local` disk, dispatch jobs, write errors CSV). Jobs
  `App\Jobs\{ValidateImportJob, ProcessImportJob}`. `App\Http\Controllers\Import\ImportController`
  (5 endpoints on `imports/{domain}`: template, upload, show, confirm, errors; ownership 404 =
  user_id!=actor OR resource!=domain; confirm wrong-status 422) + `UploadImportRequest` +
  `ImportRunResource`. `config/imports.php` (definitions map + knobs IMPORT_MAX_FILE_KB/MAX_ROWS/
  PREVIEW_VALID/PREVIEW_INVALID/BATCH_SIZE). Routes in `routes/api.php` (throttle 60,1 reads /
  10,1 upload+confirm).
- ImportDefinition contract: `columns()` [{id,required}] doubles as the template header;
  `validateRow(row, ctx): string[]` (empty = valid); `dedupKey(row): ?string` + `existsInDatabase(key)`
  (create-only dedup vs existing + intra-file via ImportRowProcessor's per-run `seenKeys`);
  `createRow(actor, row)` delegates to the existing domain Service (zero duplicated creation logic).
  Address definitions resolve geo NAMES→ids via `GeoResolver` (case-insensitive, hierarchical
  disambiguation; not-found/ambiguous → row error).
- CORRECTION vs original spec: `BusinessFunction` has NO `description` column → business-functions
  template header is `name,type` (type = business_unit|business_service, the real CreateBusinessFunctionData
  field). Companies header `denomination,vat_number,country,region,province,city,street,postal_code`;
  operational-sites `country,region,province,city,street,postal_code` (city+street required). Spec doc
  updated to match.
- Frontend NEW: generic `features/imports/*` (api.ts, types.ts, query-keys.ts, use-import.ts polling,
  import-dialog.tsx built on `Sheet` — repo has no `Dialog` primitive — import-preview/progress/
  error-report-link, upload schema). i18n `en-imports.ts`/`it-imports.ts` (+ wired into en.ts/it.ts;
  key `imports.action` for the toolbar button). Toolbar wiring: `features/table/table-toolbar.tsx` +
  `table-view.tsx` got an optional presentational `importSlot`; the 3 `*-table.tsx` adapters inject an
  Upload button gated by `<Can permission="{resource}.import">` (Can lives at `@/features/auth/can`)
  opening `<ImportDialog domain resource open onOpenChange>`.

Status — GREEN (verifier deep pass, real execution): backend `php artisan test --filter=Import`
71/71; full suite 897 tests 896 passed / 1 pre-existing skip / 0 failed (no regression, AC-017
blast radius clean); Pint clean on import files. Frontend imports/adapters/toolbar Vitest green,
`tsc --noEmit` + ESLint clean. All 21 acceptance criteria mapped 1:1 → PASS. Pre-existing 8 Vitest
failures (`auth/profile-form` ×5, `table/cell-renderers` ×3) and their causes are unrelated to import.

Non-blocking notes: (1) `business-functions-table.tsx` (339) and `operational-sites-table.tsx` (320)
now slightly over the 300-line SOFT limit (hard 500 not breached) — future split = extract the
View*/Edit* loaders per adapter. (2) `config('imports.batch_size')` reserved for a future chunked
commit; current isolation unit is one DB::transaction per row. (3) A backend teammate ran a stray
`git stash` in this SHARED tree mid-run, briefly reverting concurrent work; recovered, `stash@{0}`
left as a safety net (droppable once everything is committed).

EXTENSION (same feature, GREEN): import now ALSO covers `users` and `roles` (5 definitions total in
`config/imports.php`), and the Import action was MOVED from a standalone toolbar button INTO the table
options dropdown (`table-toolbar.tsx` renders `importSlot` as a `DropdownMenuItem` inside `DropdownMenuContent`;
all 5 `*-table.tsx` adapters inject it gated by `<Can permission="{resource}.import">`). New:
`RolesImportDefinition` (`name`,`permissions` pipe-`|`-delimited existing perms), `UsersImportDefinition`
(`email,type,first_name,last_name,company_name,locale,roles`; NO password column — random `Str::password(32)`,
`hashed` cast, forgot-password reset; individual+company profiles via CreatePersonalData/ProfileData).
SECURITY: role assignment via user import goes through the existing `UserService::assignableRoleNames`→
`RoleAssignmentGuard`; a non-assignable role (e.g. super-admin) REJECTS the row (no escalated user).
Engine change (backward-compatible): `ImportRowContext` now also carries `actor` (role-assignability is
actor-dependent); `ImportRowProcessor`/`ValidateImportJob` resolve+pass it; other definitions ignore it.
Evidence: backend `php artisan test --filter=Import` 81/81 (301 assert), Pint clean; frontend scoped Vitest
17/17 on the changed files, `tsc --noEmit` clean. NB: the shared tree now hosts SEVERAL concurrent sessions
(Composer/Laravel live upgrade PHP 8.5→8.4 per updated CLAUDE.md §0; spec 0013 Migration tests SIGSEGV;
an "employment fields"/`users.export` WIP failing user-form-payload + Table/Authorization tests) — all
UNRELATED to import; the import surface is green in isolation.

NOT COMMITTED. Working tree is ENTANGLED with a concurrent session's spec 0013 (external-data-migration:
`MigrationRun`, `add_old_id_*` migrations, `old_id` casts on 4 models, RoleService/AuthorizationRegistry/
RolesTableDefinition edits, `AssignablePermissionCatalogue`, FE migrations route/icon). An import-scoped
commit MUST exclude those. Awaiting user go for the scoped commit.

## Role form — permissions catalogue scoped to form-modules — GREEN

Refactor: the Role form's RBAC permission matrix (and the roles table `permissions`
set filter/values, same source) now offers ONLY assignable "form-module" permissions —
those whose resource prefix is registered in `config/authorization.php`
(`users`, `roles`, `business-functions`, `companies`, `operational-sites`). Indirect
sub-entity permissions (`addresses.*`, `contacts.*`, `personal_data.*`, `attachments.*`)
are excluded because they are governed via the field-permission matrix on the parent form.

Decision (user): "Hide + preserve". Indirect permissions are NOT deleted from the system
(their standalone Policies/endpoints still enforce them) — they are only removed from the
Role form catalogue, and any a role already holds are PRESERVED on save (never wiped).

Names/contracts:
- New SSOT `App\Authorization\AssignablePermissionCatalogue` (depends on `AuthorizationRegistry`):
  `isAssignable(string): bool`, `names(?search, ?limit): array`. Single source shared by
  `RolesTableDefinition` (offered catalogue: `optionsFor`/`distinctValues` for `permissions`)
  and `RoleService::syncPermissions` (preservation).
- `AuthorizationRegistry::resourceKeys()` = `array_keys(config('authorization.definitions'))`
  = the form-module prefixes.
- `RoleService::syncPermissions` now merges submitted names with the role's held
  NON-assignable permissions → empty submit clears only assignable ones, indirect intact.
- Frontend UNCHANGED: the matrix renders only groups present in `permissionOptions` (now
  filtered server-side); held indirect perms round-trip transparently through the form value.
  Request validation stays `Rule::exists('permissions','name')` (accepts any existing perm, so
  the round-trip never 422s). No i18n/component changes.

Status — GREEN: backend full Pest 797 passed / 1 pre-existing skip / 0 fail; Pint clean on
touched files. New tests: `AssignablePermissionCatalogueTest` (3), `RoleCrudTest` preserve +
clear (2), `TableConfigTest` catalogue-scope (1); `TableValuesTest` permissions-values test
updated to the new rule (requirement change, not tampering). Frontend `tsc --noEmit` clean,
roles Vitest 43/43. Not committed.

## Feature 0010 — Companies module (Società aziendali) — GREEN (verifier-confirmed)

Spec `docs/specs/0010-companies-module.xml` (contract FROZEN). New resource key `companies`,
built through the EXISTING generic pipeline (no new generic controllers/routes), mirroring Users 1:1.

Decisions: English identifiers (`companies`/`denomination`/`vat_number`); UI label "Società aziendali"
via i18n. ONE polymorphic address per company (`HasAddresses` used as single; first-address
auto-primary via `AddressService`). `vat_number` nullable + non-unique. Grid geo columns
(city/province/region/country) hidden by default; postal_code/denomination/vat_number/created_at visible.
Nav icon token `building` → lucide `Building2`. No CAP→comune lookup (comune via geo cascade, cap free).

Names/contracts to respect:
- Backend: `Company` model (morph alias `company` in `AppServiceProvider`), `CompanyPolicy`
  (BasePolicy, no self-delete), `CompanyService` (single-address via `AddressService`),
  `CompaniesAuthorization` (fields: denomination[mandatory]/vat_number/address; actions: delete/export/import)
  + `config/authorization.php` entry, `CompaniesTableDefinition` + `Companies/{CompanyColumnCatalog,
  CompanyAddressColumns}` + `config/tables.php` entry, `CompanyController` +
  `Companies/{Store,Update}CompanyRequest` (EnforcesFieldPermissions) + `Company{,Address}Resource`
  (address block emits geo ids AND names), routes `/api/companies(/{company})`.
- Frontend: `features/companies/*` (mirrors users; no roles/avatar/password/locale/personal_data/contacts),
  address = single embedded block via `GeoSelect` + free `postal_code`, all gated by the single `address`
  field-permission key. i18n extracted to `en-companies.ts`/`it-companies.ts` (en.ts/it.ts hit the 500-line
  hard limit). Wiring: `router.tsx`, `breadcrumbs.tsx`, `navigation/icon-map.ts`.

Status — GREEN: backend `php artisan test` 725 passed / 1 pre-existing skip (62 companies tests),
Pint clean on companies files; frontend 19/19 companies Vitest, `tsc --noEmit` + ESLint clean. Verifier
mapped all 17 AC → PASS with real evidence; the 8 full-suite Vitest failures (`auth/profile-form`,
`table/cell-renderers`) and the global Pint fail are PRE-EXISTING (confirmed via `git stash -u`), zero
regressions from companies.

Known non-blocking notes (recorded, safe):
- `CompaniesAuthorization` uses default `actionPermissions` → `actions.delete` = `companies.delete`
  unconditionally (not gated to `model!=null` like `BusinessFunctionsAuthorization`). Harmless (no self-ref).
- `address` field-permission change-detection: since `address` is a top-level key with no `Company::address()`
  relation, the shared `EnforcesFieldPermissions` reads current as null → if an admin locks `companies.address`
  non-editable, resubmitting the SAME address is treated as changed → 422 (fail-closed/safe, UX rough edge).
  Not fixed to avoid blast radius on the shared trait (Users/Roles/BusinessFunctions).

Not committed. Working tree ALSO holds an unrelated concurrent `business-functions` module (another
session) — a companies-scoped commit must exclude it. Awaiting user go for the scoped commit.

## Current work

**Feature 0004 — Centralized backend-driven authorization metadata** (spec
`docs/specs/0004-centralized-authorization-metadata.md`, contract FROZEN).
Convention `docs/conventions/metadata-driven-forms.md` is now MANDATORY for every form/module.

Goal: backend is the single source of truth for authorization. Every resource returns a
`permissions` block (`{ resource, fields, actions }`) alongside `data`; the frontend renders
itself from it (no hardcoded permission logic); the same resolver guards writes (422 on
non-editable fields, 403 on unavailable actions). First consumers: **User** and **Role** forms.

Key decisions:
- Non-editable field submitted on write → **422 reject** (strict), no silent drop.
- Contextual engine built with extensible hooks; only real users/roles rules wired now
  (role-assignability, super-admin guard, no self-delete). State/site/ownership hooks are no-op here.
- Frontend: metadata drives visibility/readonly/required; Zod stays a UX mirror.

## Names / contracts to respect

- Envelope: `{ success, message, data, permissions? }`. New helper
  `BaseApiController::okWithPermissions($data, $permissions, ...)`.
- `permissions.resource` abilities: `view, create, update, delete, export, import`
  (`BasePolicy::abilities()` extended with `export`/`import` → `permissions:sync`).
- Field descriptor: six flags always emitted — `visible, hidden, editable, readonly, required, disabled`.
- New backend namespace `app/Authorization/`; registry `config/authorization.php`.
- New endpoint `GET /api/meta/{resource}` (create-context), registry-driven like `tables/{domain}`.
- Reuse `RoleAssignmentGuard` + `UserService::PRIVILEGED_ROLE` — never duplicate super-admin logic.
- Frontend feature `features/authorization/` + `MetaField`; reuse `applyServerValidationErrors`,
  `useEntityDetail`, `AsyncPaginatedMultiSelect`, `Can`/`useAbilities`.

## Status — GREEN (verified)

Feature 0004 is implemented and verified against all 16 acceptance criteria.

- Backend: `app/Authorization/` coverage 96-100% per file (spec bar ≥90% met); full Pest suite
  511 passed / 1 unrelated skip; Pint clean. Authorization suite 37/37.
- Frontend: `features/authorization/` + metadata-driven `user-form`/`role-form`; scoped Vitest
  green (users/roles/authorization), `tsc --noEmit` clean, ESLint clean. Role + User metadata tests present.
- Verifier deep pass done: contract coherence confirmed end-to-end (envelope `{data, permissions}`,
  six field flags, `GET /meta/{resource}`); no regressions; no test tampering. Two coverage gaps it
  flagged (backend abstract-defaults / FieldPermission factories; missing `role-form-metadata.test.tsx`)
  are now closed.

Deviations recorded: AC6-literal (super-admin actor sees super-admin-role `name`/`permissions` as
`editable:true`) — write is still hard-blocked 422 by `RoleService::guardSystemRoleMutation` (tested).
Client-side, the role detail exposes the auth block as `.authorization` (not `.permissions`) to avoid
colliding with `RoleDetail.permissions: string[]` — no wire-contract change.

## Next steps

- Not yet committed (working tree also holds unrelated concurrent work: spec 0005 table-filters,
  `data-table`/`table`). Recommend a scoped commit of the 0004 files only before merge.
- Pre-existing/out-of-scope (not 0004): `UserAvatarProps.size` tsc error and
  `contacts-manager`/`cell-renderers` Vitest failures (from concurrent table work);
  `secret-scan.sh` false-positive on i18n locale files. Flag to their owners.
- Every new module's forms MUST follow `docs/conventions/metadata-driven-forms.md`.

## Feature 0006 — Per-role field-permission matrix — GREEN (verified by lead first-hand)

Spec `docs/specs/0006-per-role-field-permission-matrix.md`. Admins select per-role field
visibility/editability/required from a new "Permessi campi" section in the Role form.

- Backend: table `role_field_permissions`, `RoleFieldPermission`, `FieldPermissionRepository`,
  `GET /api/authorization/fields` (`FieldCatalogueController`, authz `roles.create|update`),
  `field_permissions` synced in `RoleService` (full-replace, in the existing tx), `RoleResource.field_permissions`.
  `AbstractResourceAuthorization::fieldPermissions()` is now FINAL = intersect(`fieldPermissionCeiling()`, DB config).
- **Security invariant (by construction + tested): DB config can only RESTRICT within the code ceiling,
  never escalate** (`visible/editable = ceiling AND db`); super-admin actor bypasses to full ceiling;
  absent DB row = ceiling unchanged (0004 behavior preserved). Write path (`EnforcesFieldPermissions`)
  honors the merge with no new code path.
- Frontend: `role-field-permissions.tsx` matrix (reuses the checkbox-matrix pattern), `field-catalogue-api`/
  `use-field-catalogue`, wired into the Role form; section gated by `canResource('update'|'create')`.
- Verified: backend Authorization+Roles+Users 208/208; new backend code ≥90% coverage; roles Vitest 28/28
  stable; ESLint clean; tsc clean except the pre-existing `UserAvatarProps.size` error (0005/data-table).
  The 5 full-suite backend failures are the concurrent 0005 table-filters work (`app/Tables/*`), NOT 0006.
- Note: the 0004+0006 work is commingled in the working tree with the 0005 table-filters feature (another
  session). A scoped commit of the Authorization/roles files is still pending a go from the user.

## Frontend status (spec 0004) — DONE, ready for Verifier

Implemented against the frozen contract, not blocked on backend:

- `features/authorization/`: `types.ts`, `api.ts` (`fetchResourceMeta` → `GET /meta/{resource}`),
  `query-keys.ts`, `use-resource-meta.ts` (5 min staleTime, `enabled` toggle), `permissions.tsx`
  (`ResourcePermissionsProvider` + `useResourcePermissions()` — graceful fallback: missing
  field/action → visible+editable / allowed, never crashes), `MetaField.tsx` (wraps `FormField`;
  `!visible` → renders nothing; forwards `disabled`/`readOnly`/`required` — `disabled` passed down
  is `permission.disabled || !permission.editable`, since a `readonly` field is `editable:false`
  but not necessarily `disabled:true`).
- `features/users/`, `features/roles/`: `fetchUser`/`fetchRole` now return the instance detail
  plus its authorization block; `user-form.tsx`/`role-form.tsx` resolve permissions (edit: from
  the loaded detail; create: `useResourceMeta`) then hand off to `user-form-body.tsx`/
  `role-form-body.tsx`, where every field is wrapped in `MetaField` (no hardcoded permission `if`s
  left in JSX). Heavy logic extracted into `use-user-form.ts`/`use-role-form.ts` (+ `use-*-form-meta.ts`,
  `user-form-payload.ts`) to stay under the 300-line soft limit.
- `components/avatar-upload.tsx`: added optional `canUpload`/`canRemove` on the immediate-mode
  variant, wired to `actions.upload_avatar`/`actions.delete_avatar` in the user edit form.
- i18n: `authorization.loadError`, `authorization.fieldNotEditable` in `en.ts`/`it.ts`.
- Tests (Vitest + RTL, all passing): `features/authorization/{permissions,MetaField}.test.tsx`,
  `features/users/user-form-metadata.test.tsx` (AC 11-16), plus the pre-existing
  `user-form.test.tsx`/`role-form.test.tsx` updated for the new types/mocks.

**Contract ambiguity resolved (flagging for Backend/Architect):** `RoleDetail` already has its own
`permissions: string[]` (the role's granted permission names). The envelope's top-level
authorization `permissions` block would collide with it once flattened client-side, so the
frontend exposes it as `RoleDetailWithPermissions.authorization: ResourcePermissions` instead of
`.permissions`. `fetchRole` maps `{ ...data.data, authorization: data.permissions }`. No wire
contract change needed (the envelope keeps `data.permissions` and the top-level `permissions` as
distinct siblings) — this is purely a client-side naming fix.

**Blocked/deferred:** actual `GET /api/meta/{users,roles}` and `permissions` on
`GET /users/{id}` / `GET /roles/{id}` responses are backend work (per this spec, in progress
per `backend/app/Authorization/` on disk) — frontend code is written against the frozen shape and
type-checks/tests green with mocked responses; needs an end-to-end smoke test once the backend
endpoints are live.

**Pre-existing, out-of-scope issues observed (not touched, not caused by this work):**
- `components/user-avatar.tsx` / `features/users/column-renderers.tsx`: `tsc` error, `UserAvatarProps`
  missing a `size` prop used by a call site — present before this session's changes (verified via
  `git stash`), belongs to unrelated in-progress `table`/`data-table` work.
- `features/personal-data/contacts-manager.test.tsx` (missing `QueryClientProvider`) and
  `features/table/cell-renderers.test.tsx` (i18n locale mismatch, "primary contacts" vs
  "contatti principali") — 7 failing tests, confirmed pre-existing via `git stash`, unrelated to
  spec 0004.
- `.claude/hooks/secret-scan.sh` false-positives on `frontend/src/i18n/locales/{en,it}.ts`: its
  regex flags any `password: '<8+ chars, no space>'` translation label (e.g. `password: 'Password'`)
  as a possible secret. Pre-existing in the file before this session; blocks every edit to these
  locale files with a PostToolUse warning. Not in frontend ownership to fix (`.claude/hooks/`).

---

## Feature 0005 — Excel-like table filters (AG Grid SSRM) — DONE, GREEN, awaiting commit decision

Spec `docs/specs/0005-table-excel-like-filters.xml` (renamed from 0004 to avoid the number
collision with the concurrent authorization-metadata feature). Contract FROZEN and respected.

Goal: per-column Excel-like filters = server-side distinct value list (from ALL rows, respecting
other columns' active filters) + type-specific conditions, combined via `agMultiColumnFilter`,
compatible with SSRM paging/sorting.

Delivered (all green, evidence real):
- Backend: new `POST /api/tables/{domain}/values` (distinct values, cap 200, `hasMore`, respects
  OTHER columns' filters, excludes the target column — Excel behavior). `TableService::distinctValues`
  + new contract method `TableDefinition::distinctValues(...)` (default null; overridden for derived
  columns roles/user_type/geo/permissions — in-memory search, no SQL LIKE on geo tables). Filter
  engine extracted to `app/Services/Table/FilterApplier.php` with new branches: number
  (equals/notEqual/gt/ge/lt/le/inRange), boolean, multi, combined `{operator, conditions}`. New
  `TableValuesRequest`, `DistinctValuesResult` DTO. `UsersTableDefinition` split into
  `Tables/Users/{UserColumnCatalog,UserGeoColumns,UserPersonalDataColumns,Concerns/CorrelatesPersonalDataToUser}`
  (was 849 lines, pre-existing hard-limit violation; behavior-preserving). `users.id` now
  `filterType:number`, `roles.users_count` number.
- DB: migration `2026_07_02_100000_add_created_at_index_to_users_table.php` (only gap; rest already
  indexed). LIKE `%term%` can't use B-tree → cap+LIMIT is the mitigation, not an index.
- Frontend: `resolveFilter` → `agMultiColumnFilter` (text/number/date), `agSetColumnFilter`
  (set/enum/boolean); Set Filter async server values via `fetchTableColumnValues`, scoped to OTHER
  columns' filterModel; `hasMore` → toast. Logic extracted to `components/data-table/column-filters.ts`.
  `ssrm-datasource.ts` already forwarded the combined `multi` filterModel intact (no change).

Verification (independent verifier + security, both green):
- Backend `php artisan test` 490/490 (Table filter=92: 91 passed, 1 skipped, 0 failed); Pint clean.
- Frontend Vitest green on touched files; `tsc --noEmit` clean; ESLint clean. (7 unrelated
  pre-existing FE failures confirmed on baseline via git stash — contacts-manager/cell-renderers.)
- Security: GO, no critical/high. Authz server-side, column allow-list, all values bound, no raw SQL,
  escapeLike on all LIKE incl. `search`, limit cap 200.
- AC-001..015 all mapped to passing tests.

Bugfix (post-review, derived computed columns): the Multi Filter attached a Set Filter to
text/number columns, so opening it on a COMPUTED derived column (`users.primary_address`,
`users.primary_contact`, `roles.users_count`) called `/values` → `distinctFromColumn` ran
`SELECT DISTINCT <col>` on a column with no real DB backing → "Unknown column" crash. Fix:
new column-contract flag `hasFilterValues` (bool; false for those computed columns);
`TableService::distinctValues` short-circuits to `{values:[],hasMore:false}` before building any
query when the flag is false (defence-in-depth for any future derived column); frontend renders a
condition-only filter (agText/agNumber/agDate — no Set tab, no `/values` call) when
`hasFilterValues===false`. Condition filtering on those columns was always fine (applyDerivedFilter)
and is unchanged. Reproduce-first tests added (AC-016/017/018). Backend full suite 511 (510 passed,
1 skip, 0 failed); frontend 186 passed (+7 pre-existing unrelated), tsc/lint clean.

UX iteration (Excel-classic layout + computed-column selection) — user-driven, all green:
- Layout: `agMultiColumnFilter` reconfigured to Excel-classic — Set Filter INLINE (`excelMode:'windows'`:
  search + Select All + Apply/Reset checklist) with the typed condition tucked into a titled
  `display:'subMenu'` (`table.{text,number,date}Filters` i18n). Same look on every filterable column,
  set/enum/boolean included. No tabs.
- Computed columns given real value lists: `users.primary_contact` (distinct `contacts.value`) and
  `roles.users_count` (distinct aggregate counts) now show the checklist. `users.primary_address`
  stays CONDITIONS-ONLY (`hasFilterValues=false`) by user decision — it is a composed string
  (street+postal+city+province), so an exact-match checklist would need fragile SQL reconstruction
  with MySQL/SQLite parity risk; conditions (contains/equals) are robust and the natural tool.
- Selection bug fixed (root cause): the Multi Filter sends `{filterType:'multi', filterModels:[set,
  condition]}`, but derived columns' `applyDerivedFilter` only read the flat top-level shape → both
  checklist selection AND conditions silently no-op'd on computed columns. New shared trait
  `app/Tables/Concerns/UnwrapsMultiFilter::dispatchDerivedFilter()` unwraps `multi` and applies each
  sub-model in AND: Set → per-column set-handler (contact → `whereIn(contacts.value)`; users_count →
  `orHas('users','=',n)` per selected count), condition → the existing handler. `RolesTableDefinition`
  split into thin dispatcher + `app/Tables/Roles/RoleUsersCountColumn.php` (kept under 500). Address
  dead code removed (`addressDistinctValues`, `formatAddressLine` re-inlined).
- Verified end-to-end via `/rows` (real row matches, not just 200): `TableRowsMultiFilterTest` 7/7;
  `TableConfigTest` 14/14 incl. new roles-domain `users_count.hasFilterValues=true` assert (closes
  old follow-up #4). Backend full suite 562 (561 passed, 1 skip, 0 failed); frontend unchanged this
  round (7 pre-existing failures only), tsc/lint clean. New AC-019..022 in the spec.

Open follow-ups (tickets, NOT blocking this feature):
1. `escapeLike()`+LIKE has no explicit `ESCAPE` clause → under-matches literal `%`/`_` on SQLite
   (dev/test); correct on MySQL prod (backslash default). App-wide, pre-existing. Now tracked as an
   explicit `->skip(...)` in `FilterApplierTest.php`. Fix = add ESCAPE to the shared helper.
2. `config/sanctum.php` `expiration => null` (tokens never expire) — pre-existing hardening gap
   (security.md §8), unrelated to this feature.
3. `.claude/hooks/secret-scan.sh` false-positive on i18n `password:` labels (see above).
4. RESOLVED — direct assertion on `GET /api/tables/roles/columns` for `users_count.hasFilterValues`
   was added in the UX iteration's `TableConfigTest`. (Original note kept for history below.)
   ~~add a direct assertion on `GET /api/tables/roles/columns` that
   `users_count` carries `hasFilterValues=false` — currently verified only end-to-end via `/values`
   (AC-016) and `/rows` (AC-017), not at the contract level for the roles domain.~~
5. (watch) `UsersTableDefinition.php` (412) and `UserPersonalDataColumns.php` (392) are over the 300
   soft limit (<500 hard). `RolesTableDefinition.php` was split (358) via `Roles/RoleUsersCountColumn.php`.
   Candidates for a future split if they keep growing.

Commit status: NOT committed. The `feat/style` working tree intermixes THIS feature with the
concurrent `0004-centralized-authorization-metadata` feature in shared files (`routes/api.php`,
`i18n/locales/{en,it}.ts`) — cannot be cleanly isolated without interactive patch-staging. Also a
stray `backend/qnet2` (300KB SQLite dev DB) is untracked and must not be committed (gitignore it).
Awaiting the user's decision on how to split/commit.

---

## Feature 0006 — Per-role field-permission matrix — Frontend DONE

Spec `docs/specs/0006-per-role-field-permission-matrix.md` (contract FROZEN). Builds on 0004;
backend work (`role_field_permissions` table, merge resolver, `GET /api/authorization/fields`) is
tracked separately — frontend implemented strictly against the frozen shape, not blocked on it.

Delivered (`features/roles/`, all new unless noted):
- `types.ts` (edit): `RoleFieldPermission { resource, field, visible, editable, required }`;
  `RoleDetail.field_permissions: RoleFieldPermission[]` (required, mirrors backend's always-present
  flat list); `CreateRolePayload`/`UpdateRolePayload` gain optional `field_permissions`.
- `field-catalogue-api.ts` + `use-field-catalogue.ts`: `GET /authorization/fields` (plain
  `ApiResponse`, no `permissions` envelope sibling — this endpoint authorizes once up front, not
  per-resource), React Query, 5 min staleTime, `enabled` toggle.
- `field-permission-toggle.ts`: pure helpers (`fieldPermissionFlag`, `toggleFieldPermission`,
  `sameFieldPermissions`) — unrestricted default (no row) = visible+editable, not required, per the
  spec's merge semantics. Unit-tested directly (`field-permission-toggle.test.ts`).
- `role-field-permissions.tsx`: the matrix UI (resource fieldsets × 3 toggle columns), reusing the
  existing permission-matrix checkbox styling (no new `components/ui` primitive). Each checkbox gets
  an accessible name via a `sr-only` label (`"<field label> — <toggle label>"`), field labels reuse
  each resource's existing `<resource>.form.<field>` i18n keys (`permission-labels.ts` →
  `fieldPermissionLabel`), falling back to a humanized token.
- `use-role-form.ts` (edit) / `role-form-body.tsx` (edit) / `role-form-payload.ts` (new, split out of
  `use-role-form.ts` to stay under the 300-line soft limit): seeds from `role.field_permissions`
  (edit) or `[]` (create); submit diffs against the original and omits the key when unchanged (same
  convention as `permissions`/`users`); `SERVER_ERROR_FIELDS` gains `'field_permissions'` for 422
  mapping.
- `role-schema.ts` (edit): `field_permissions` array schema added as a UX mirror (no real validation
  — the backend merge is the source of truth).
- i18n: `roles.fieldPermissions.{title,visible,editable,required,empty,loadError}` in `en.ts`/`it.ts`.
- Tests: `field-permission-toggle.test.ts` (unit) + `role-form-field-permissions.test.tsx` (AC
  11-15, RTL) — all passing. `role-form.test.tsx`/`role-form-metadata.test.tsx` updated (new required
  `field_permissions` fixture field; both mock `field-catalogue-api` to an empty catalogue so the new
  section stays inert for their unrelated assertions).

**Contract ambiguity resolved:** the spec says the section is "gated by the metadata (…reuse 0004
`MetaField`/`canAction` where applicable)" but the backend design does NOT add any new `fields.*` or
`actions.*` key for this section (the 0004 `permissions` envelope is explicitly unchanged/additive
only). Wrapping it in `<MetaField metaKey="field_permissions">` alone would never actually gate
anything — that key can never exist in `permissions.fields`, so `MetaField`'s graceful fallback
(visible+editable) would always apply regardless of the actor's real ability. Resolution: gate the
whole section with the EXISTING resource-level ability already in `ResourcePermissions.resource`
(`canResource('update')` in edit mode / `canResource('create')` in create mode, via
`useResourcePermissions()` — same 0004 hook, just a resource-ability read instead of a field lookup),
matching the ceiling rule that already locks `name`/`permissions`/`users` when the actor cannot write
the role. `MetaField` is still used for the section's own label/message scaffolding for consistency;
the real security-relevant gate is the outer `canManageFieldPermissions` conditional (hides the
section entirely — not merely disables it — when false; also skips the `/authorization/fields`
fetch). Verified in AC15's test.

Verification: `npx vitest run src/features/roles` → 6 files / 28 tests passed. Scoped
`tsc -b --noEmit` → clean except the pre-existing, unrelated `UserAvatarProps.size` error (confirmed
via `git stash` in the 0004 work above). `npx eslint src/features/roles` → clean. Full-repo
`npx vitest run` → 201/208 passed (same 7 pre-existing/unrelated failures as 0004/0005, zero new
regressions).

**Blocked/deferred:** `GET /api/authorization/fields` and `RoleResource.field_permissions` are
backend work per this spec — frontend is written against the frozen shape with mocked responses;
needs an end-to-end smoke test once the backend endpoint/column are live.

## Feature — Per-user table filter persistence + "Reset filters" — GREEN (verified)

Sibling of spec 0001 column-preferences, for the AG Grid filterModel (spec 0005 had left filter
persistence out of scope). Filters the user applies survive a page reload, and a toolbar "Reset
filters" button (icon `FilterX`) clears them, shown only when filters are active — mirroring the
existing "Reset layout" button.

Contract (FROZEN): new pair of endpoints alongside preferences, same throttle/auth group:
- `POST /api/tables/{domain}/filters` body `{ filterModel }` → upsert; empty model clears the row;
  returns the merged config. `DELETE /api/tables/{domain}/filters` → reset (204).
- Config envelope now also carries `filterState` (object, `{}` when none) and `filtersCustomized`
  (bool), attached in `TableController::resolvedConfig` via the new `TableFilterStateService::applyTo`,
  chained after `TablePreferenceService::applyTo`.

Backend (mirrors ADR-0004 preferences pattern):
- `user_table_filters` table (`unique(user_id, domain)`, json `filters`), model `UserTableFilter`
  (no Policy / no activity-log, self-scoped — same rationale as `UserTablePreference`).
- `TableFilterStateService` (save/reset/applyTo) — keys restricted to `filterableColumnIds()` on
  every read AND write (same allow-list the SSRM rows query enforces); NOT a sparse delta (filters
  have no default) — stores the applied model whole; empty model deletes the row.
- `TableFilterStateRequest` — `filterModel` `present|array`, keys 422'd against `filterableColumnIds()`
  exactly like `TableRowsRequest::withValidator`.
- Tests: `tests/Feature/Table/TableFilterStateTest.php` 11/11 (auth 401, unknown domain 404, missing
  viewAny 403, persist+merge, non-filterable key 422, stale-key tolerance, empty-clears-row,
  reset-removes-row, per-user isolation). Full `tests/Feature/Table` 99/99. Pint clean.

Frontend:
- `data-table.tsx`: new `initialFilterModel` (applied once via `initialState.filter.filterModel`, so
  the first SSRM request is already filtered) + `onFilterChanged` passthrough.
- `table-view.tsx`: `useSaveTableFilters`/`useResetTableFilters`; `handleFilterChanged` debounced 500ms
  with a `lastPersistedFilterRef` (JSON) guard to skip the grid's mount echo and no-op refires;
  `handleResetFilters` = mutate DELETE → refetch config → bump the SHARED `layoutVersion` remount
  (grid rebuilds with empty `filterState`, SSRM re-queries unfiltered) — same remount mechanism as
  layout reset. New `EMPTY_FILTER_MODEL` module const (stable identity).
- `use-table-filters.ts` (hooks), `api.ts` (`saveTableFilters`/`resetTableFilters`), `types.ts`
  (`TableConfig.filterState?`/`filtersCustomized?`), i18n `table.resetFilters/filtersReset/filtersError`.
- Tests: `api.test.ts` extended (save posts wrapped model; reset DELETEs) 3/3. `tsc --noEmit` clean,
  ESLint clean.

Pre-existing/out-of-scope (NOT this feature): `cell-renderers.test.tsx` 3 failures — files unmodified
(at HEAD), already failing from the concurrent 0005 table work. `secret-scan.sh` false-positive on the
i18n locale files (`en.ts`/`it.ts`) persists.

Not yet committed (working tree still commingled with 0004/0005/0006). Recommend a scoped commit of
just the filter-persistence files.

---

## Feature 0008 — Personal-data field permissions — Frontend DONE

Spec `docs/specs/0008-personal-data-field-permissions.xml` (contract FROZEN). Extends 0004/0006 to
the personal-data morph fields (`personal_data.{type,title,first_name,last_name,company_name,
tax_code,vat_number,sdi_code,birth_date,contacts,addresses}`). Backend work (ceiling rules,
CHANGE-based `EnforcesFieldPermissions`) tracked separately — frontend implemented strictly against
the frozen dot-path key contract, not blocked on it.

Delivered:
- `features/personal-data/types.ts`: new `PersonalDataFieldPermission` (visible/editable/required/
  disabled/readonly — no `hidden`) and `PersonalDataFieldPermissionResolver = (key) => ...`. Deliberately
  NOT `@/features/authorization`'s `FieldPermission` (decision D3): the shared personal-data
  components must stay decoupled from any specific resource; the caller adapts and injects by prop.
- `personal-data-section.tsx` / `personal-data-card-form.tsx` / `contacts-manager.tsx` /
  `addresses-manager.tsx`: new **optional** `fieldPermission` prop, propagated section → children.
  `!visible` → field/section not rendered; `!editable` → input disabled/readonly (card fields) or the
  whole manager goes read-only (no add/edit/delete, contacts/addresses lists still shown); `required`
  reflects the resolved flag. **Omitting the prop entirely preserves today's behaviour exactly**
  (verified: `profile-form.test.tsx`, unmodified, still green — self-service `ProfileForm` never
  passes it, AC-013).
- `features/personal-data/drafts.ts`: `PersonalDataPayload`'s fields widened to optional (needed so a
  gated payload can omit keys); new `omitNonEditableFields(payload, fieldPermission?)` — strips the
  scalar/section keys the resolver marks non-editable, no-op without a resolver.
- `features/users/use-user-form.ts`: adapts `useResourcePermissions().field` (6-flag
  `FieldPermission`) into a `PersonalDataFieldPermissionResolver` (5-flag, drops `hidden`) exposed as
  `personalDataFieldPermission`; wired into `PersonalDataSection` (via `user-form-body.tsx`) and into
  both payload builders.
- `features/users/user-form-payload.ts`: `buildCreatePayload`/`buildUpdatePayload` gained an optional
  4th param `fieldPermission`; the nested `personal_data` tree is now built via
  `omitNonEditableFields(draftToPayload(profileDraft), fieldPermission)` (defense in depth — the
  backend enforces the same rule with a CHANGE-based guard, D2).
- i18n: `personalDataFieldLabels` (module-level const, keyed by dot-path field name) in both
  `en.ts`/`it.ts`, referenced from BOTH `users.form.personal_data.*` (new, read by
  `fieldPermissionLabel('users', 'personal_data.<field>')` for the Role matrix) and the pre-existing
  `personalData.form.*` card labels (now reference the same const — no string drift). No code change
  needed in `permission-labels.ts`/`role-field-permissions.tsx`: `fieldPermissionLabel` already builds
  `${resource}.form.${field}` and i18next's default `.` key-separator walks a dotted field key
  (`personal_data.first_name`) through nested objects transparently.

Tests (Vitest + RTL, all passing): `personal-data/personal-data-section.test.tsx` (new — AC-011
visible/editable/required for card fields + contacts/addresses sections, AC-013 ungated baseline),
`users/user-form-payload.test.ts` (new — AC-012, unit on the builders), `roles/permission-labels.test.ts`
+ `roles/role-field-permissions-personal-data.test.tsx` (new — AC-010, label resolution + full matrix
render for the 11 keys). Existing `profile-form.test.tsx`/`user-form.test.tsx`/`contacts-manager.test.tsx`
(baseline-failing, see below)/`addresses-manager.test.tsx` untouched and re-verified as regression
evidence for AC-013.

Verification: `npx vitest run src/features/personal-data src/features/users src/features/roles
src/features/auth/profile-form.test.tsx` → 21 files / 99 tests, 95 passed, 4 failed (all in
`contacts-manager.test.tsx`, pre-existing — see below, confirmed via `git stash` unrelated to this
work). Full-repo `npx vitest run` → 236 tests, 229 passed, 7 failed = the same pre-existing
`contacts-manager.test.tsx` (4) + `cell-renderers.test.tsx` (3), zero new regressions (counts
identical stashed vs. not). `npx tsc --noEmit` clean except the pre-existing, unrelated
`UserAvatarProps.size` error. `npx eslint` clean on every touched file.

**Ambiguity/note for Backend:** the dot-path field keys in `omitNonEditableFields` are hardcoded
(`personal_data.type` … `personal_data.addresses`), matching the frozen contract exactly. If the
backend ever needs the FE to omit at finer granularity (e.g. per-contact-row) this file is the single
place to extend — no change expected per D1 (section-level only).

Pre-existing/out-of-scope (NOT this feature, confirmed via `git stash` against baseline HEAD before
any 0008 change): `contacts-manager.test.tsx` (4 failures — `ContactsManager` calls `useEnumOptions`
directly, needs a `QueryClientProvider` wrapper the test never had), `cell-renderers.test.tsx` (3
failures — i18n language-state leak between test files), `UserAvatarProps.size` tsc error, and the
`secret-scan.sh` false-positive on `frontend/src/i18n/locales/{en,it}.ts` (flags the pre-existing
`password: 'Password'`-shaped translation entries as secrets; blocks every edit to these two files
with a PostToolUse warning that does not roll back the edit — not in frontend ownership to fix).

**Follow-up — `mandatory` field lock (post-run addition, test-only lane):** the coordinator added
`FieldDescriptor.mandatory: boolean` (`features/authorization/types.ts`) and implemented the matrix
lock in `role-field-permissions.tsx` directly (a mandatory row forces all three checkboxes
checked+disabled, with a ` *` + `title` hint) — both are PRODUCTION code, not touched by this lane.
Realistic mandatory set: `users` → `email`, `locale`, `password`, `personal_data.type`,
`personal_data.first_name`, `personal_data.last_name`, `personal_data.company_name`; `roles` → `name`.
Frontend test-only fixes:
- `roles/role-field-permissions-personal-data.test.tsx`: every `FieldDescriptor` fixture now carries
  a realistic `mandatory` value; "unrestricted default" assertions moved to the non-mandatory
  `personal_data.tax_code` row; added a new test asserting a mandatory row (`personal_data.first_name`)
  renders all three checkboxes checked+disabled.
- `roles/role-form-field-permissions.test.tsx`: the shared `CATALOGUE` fixture's sole field changed
  from `email` (now realistically mandatory, so no longer toggable) to `personal_data.tax_code`
  (`mandatory: false`) — every "Email — …" label/assertion and the `field: 'email'` payload
  expectations renamed to `Tax code`/`personal_data.tax_code` accordingly (AC11-14 unchanged in
  intent, just re-subjected). Added a new test with its own one-off catalogue (`email`,
  `mandatory: true`) asserting the locked checked+disabled state through the full `RoleForm`
  integration (not just the bare `RoleFieldPermissions` component).
- No other test file constructs a non-empty `FieldDescriptor`/`FieldCatalogueResource` array (checked
  via grep across `*.test.tsx`/`*.test.ts`); `role-form-metadata.test.tsx`/`user-form-metadata.test.tsx`
  only use `fields: []`/`fields: {}`, unaffected.

Verification: `npx tsc --noEmit -p tsconfig.app.json` → clean except the pre-existing
`UserAvatarProps.size` error. `npx vitest run src/features/roles src/features/personal-data
src/features/users` → 20 files / 96 tests, 92 passed, 4 failed (same pre-existing
`contacts-manager.test.tsx`, unrelated). Full-repo `npx vitest run` → 47 files / 251 tests, 244
passed, 7 failed (same two pre-existing files as always: `contacts-manager.test.tsx` 4 +
`cell-renderers.test.tsx` 3) — zero new regressions. `npx eslint` clean on both touched test files.

## Feature 0007 — Saved filter views (private/shared) — GREEN (verifier-confirmed)

Spec `docs/specs/0007-saved-filter-views.md` (FROZEN). Builds on the filter-persistence work.
A user saves the current AG Grid filter set as a NAMED view (private or shared) and re-applies it
from a toolbar dropdown. Implemented by two agents (backend/frontend, disjoint ownership) against the
frozen contract; independently verified end-to-end.

Contract: `GET/POST /api/tables/{domain}/filter-views`, `PUT/DELETE .../{filterView}` (throttle:60,1
table group). Resource `{ id, name, filters, visibility, owned, owner_name }` — `owner_name` only when
shared AND not owned (display name only, never PII). List = own (private+shared) + others' shared,
owned-first then by name.

Authz: list/create gated by the definition `authorizeViewAny`; update/delete by `TableFilterViewPolicy`
(owner-only) PLUS the existing global `Gate::before` super-admin bypass in `AppServiceProvider`
(NOT duplicated in the policy — single source of truth). Cross-domain bound `{filterView}` → 404
BEFORE the Policy (no 403 leak). `filters` keys allow-listed against `filterableColumnIds()` on store
AND update (mirror of `TableRowsRequest::withValidator`) and re-filtered on read — no whereRaw/dynamic
SQL from stored JSON.

Backend files (new): migration `create_table_filter_views_table`, `FilterViewVisibility` enum,
`TableFilterView` model + factory, `TableFilterViewPolicy`, `TableFilterViewResource`,
`TableFilterViewRequest`, `TableFilterViewService`, `TableFilterViewController`,
`tests/Feature/Table/TableFilterViewsTest.php` (14 tests). Routes added to `routes/api.php`.

Frontend files (new, `features/table/`): `filter-views-api.ts`, `use-filter-views.ts`
(key `['table', domain, 'filter-views']`), `filter-views-control.tsx` (dropdown, My/Shared groups,
apply via `gridApi.setFilterModel`, owned-only delete), `save-filter-view-sheet.tsx` +
`save-filter-view-schema.ts` (Sheet + RHF/Zod, name + visibility Select), 3 test files. Modified:
`types.ts` (+FilterView types), `table-view.tsx` (control wired into toolbar, gated on gridApi+config),
i18n en/it.

Verifier evidence: Backend `tests/Feature/Table` 113/113 (14 new), new files ~98.7% coverage, Pint
clean. Frontend `tsc --noEmit` clean, `eslint src/features/table` clean, new vitest 13/13.
Contract coherence BE↔FE confirmed 1:1 (routes, resource shape, envelope, query key). Zero new
failures introduced.

Pre-existing/out-of-scope (git-confirmed at `Initial commit`, NOT 0007): 7 vitest failures —
`personal-data/contacts-manager.test.tsx` (4) and `table/cell-renderers.test.tsx` (3). Verifier
diagnosed the cell-renderers ones as an i18n test-env default mismatch (tests assert English strings
but the env renders Italian, e.g. "2 primary contacts" vs "2 contatti principali") — a test/config
issue, not a code bug. Flag to the personal-data/i18n owner.

Still uncommitted: working tree commingles 0004/0005/0006 + the two filter features (0007 + the
filter-persistence pair). A scoped commit is still pending a go from the user.

## Feature 0008 (personal-data field permissions) — mandatory-field increment — GREEN (lead-verified)

Follow-up requirement after the initial 0008 build: fields VITAL to creating the record are
"mandatory" — in the Role field-permission matrix their row has all three checkboxes
(visible/editable/required) forced ON and DISABLED, and the server-side merge can never let a
`role_field_permissions` row narrow them (bypass).

Implemented by the lead (production) + both agents (tests):
- `FieldDefinition` gains `mandatory` (bool, default false), emitted in `toArray()` →
  `{key,type,group,mandatory}` (so `GET /api/authorization/fields` AND `GET /api/meta/{resource}`
  and every `permissions.fields` consumer carry it).
- `UsersAuthorization::fields()` mandatory=true: email, locale, password, personal_data.type,
  personal_data.first_name, personal_data.last_name, personal_data.company_name.
  `RolesAuthorization::fields()` mandatory=true: name.
- `AbstractResourceAuthorization::fieldPermissions()` (FINAL): mandatory fields BYPASS the DB
  intersect (`mandatoryFieldKeys()`), keeping the full ceiling — the server twin of the locked
  disabled checkboxes. Super-admin branch is unchanged (returns ceiling before the mandatory check).
- Frontend `FieldDescriptor.mandatory: boolean`; `role-field-permissions.tsx` locks mandatory rows
  (3 checkboxes checked+disabled, ` *` marker, `title` = `roles.fieldPermissions.mandatory`);
  i18n key added en/it.
- Spec updated: `docs/specs/0008-personal-data-field-permissions.xml` — D5 decision, contract
  (`mandatory` per field), AC-015..AC-018; AC-004/006 examples moved to a non-mandatory field.

Lead final verification (run for real, XDEBUG off):
- Backend: `tests/Feature/Authorization tests/Unit/Authorization tests/Feature/Users tests/Feature/Roles`
  → 230/230 passed (1115 assertions). New backend code ≥96-100% coverage. Pint clean.
- Frontend: scoped Vitest (roles/personal-data/users/authorization) → 100 passed; the only 4 failures
  are the PRE-EXISTING `contacts-manager.test.tsx` "No QueryClient set" (git-confirmed on baseline HEAD,
  NOT ours). `tsc --noEmit` clean except the pre-existing `UserAvatarProps.size` (feature 0005, out of
  scope). ESLint clean on touched files.

Test retargeting declared (requirement change, not tampering): the 0006 restriction/enforcement tests
that used email/locale/first_name (now mandatory, thus un-restrictable) were moved onto the
non-mandatory `personal_data.tax_code`; new tests added for the mandatory bypass (read + write) and the
catalogue `mandatory` flag (`PersonalDataMandatoryFieldTest.php`).

### Spec-number collision — RESOLVED (this feature renumbered 0007 → 0008)
The two features had both grabbed 0007 (commingled working tree). Per the user's decision, THIS
feature (personal-data field permissions) was renumbered to **0008**; the concurrent
`0007-saved-filter-views.md` keeps 0007 and was left completely untouched (no override). Renumber
scope (this feature's files only): spec file renamed to `0008-personal-data-field-permissions.xml`
(+ internal id), all `spec 0007` code/test comments → `spec 0008`, and the two MINE-only `spec 0007`
comment lines in the shared i18n `en.ts`/`it.ts`. Verified: zero `0007` left in this feature's files;
`TableFilterView*` / `features/table/*` still reference 0007 as before. No functional code changed —
comments/spec-id only.

Not committed (per user): working tree still commingles 0004/0005/0006 + 0008 (this) + 0007
(saved-filter-views) + the filter-persistence pair. A scoped commit of the 0008 files is available on
request but was explicitly deferred by the user.

## Feature 0007 — Filter-views SAVE moved inline into the dropdown (redesign) — GREEN

Follow-up to 0007: the "save current filter" flow moved OUT of a Sheet/modal and INTO the
`filter-views-control.tsx` dropdown panel itself (user request: "sempre nel drop", premium look).
Research-grounded (Attio/Airtable "save query" inline pattern; segmented control for 2 mutually-
exclusive options; always-show-active-filter rule).

Changes (frontend only, no contract/backend change):
- `filter-views-control.tsx` rewritten as a single self-contained panel: header (icon chip + title +
  subtitle), grouped list (My/Shared) with a leading lock/people glyph per visibility + an ACTIVE
  check on the currently-applied view (`sameFilters` order-independent compare), hover-revealed delete,
  and an inline SAVE section (name Input + private/shared SEGMENTED control + full-width primary CTA;
  swaps to a hint when there are no filters). Controlled `open`; resets the form on close. Radix Menu
  keystroke/typeahead + Tab-close handled via `onKeyDown stopPropagation` (except Escape) on the save
  block, Enter-to-save on the input. Trigger shows a count badge.
- Deleted `save-filter-view-sheet.tsx` + `save-filter-view-schema.ts` (RHF/Zod no longer needed; name
  validated by trim + native maxLength=80).
- i18n: removed dead `saveCurrentFilter`/`saveCurrentFilterDescription`/`cancel`; added
  `savedFiltersSubtitle`/`saveViewHeading`/`saveView`/`applyFilterToSaveHint`/`viewActive` (en+it).
- `filter-views-control.test.tsx` updated: replaced the "opens sheet" test with inline-save tests
  (disabled-until-named, save with chosen visibility) + a no-filters hint test.

Verified: `tsc --noEmit` clean, `eslint` clean on changed files, `filter-views-control.test.tsx` 6/6,
full `src/features/table` = 40 passed / 3 failed — the 3 are the SAME pre-existing `cell-renderers`
failures (i18n test-env mismatch), zero new regressions.

Visual preview artifact (static approximation of the real component, light+dark):
https://claude.ai/code/artifact/89ccf38e-10c2-4bbb-8ed9-97656c39553b

## Reusable confirm dialog (replaces native `window.confirm`) — GREEN (verified)

A single "wow" confirmation dialog now backs every confirm-gated action; native `window.confirm`
is gone from the app (only a doc-comment mention remains).

- New design-system primitive `components/ui/alert-dialog.tsx` (shadcn new-york over `radix-ui`
  AlertDialog): frosted `backdrop-blur` overlay + spring-overshoot zoom/lift entrance
  (`ease-[cubic-bezier(0.34,1.56,0.64,1)]`). Accessible by construction (role=alertdialog, focus trap).
- Imperative API split per repo convention (context/hook vs provider, like `auth-*`):
  `components/confirm-dialog-context.ts` (`ConfirmContext`, `useConfirm`, `ConfirmOptions`,
  `ConfirmTone`) + `components/confirm-dialog.tsx` (`ConfirmDialogProvider`). Provider mounted once in
  `App.tsx` inside `TooltipProvider`. Usage: `if (!(await confirm({tone, title, description}))) return`.
- Tones `default|destructive|success|warning|info` → pulsing icon halo (`motion-safe:animate-ping`),
  lucide icon, and confirm-button variant. Labels default to `common.confirm|cancel|confirmTitle`
  (added to en+it).
- Migrated all 4 `window.confirm` call sites: `personal-data/contacts-manager`,
  `personal-data/addresses-manager` (tone destructive, delete-action confirm label),
  `table/row-actions` (generic action confirm, title = action label), `table/filter-views-control`.
- Tests: new `confirm-dialog.test.tsx` (4/4 — resolves true/false, renders title/desc, i18n defaults).
  The 3 migrated component tests updated to drive the dialog (scoped `within(alertdialog)`); their
  render harnesses now wrap the providers the components actually require. NOTE: those 3 tests were
  already RED at HEAD from concurrent work (Tooltip added to `FilterViewsControl` w/o a
  `TooltipProvider` in the test; `useEnumOptions` added to `ContactsManager` needing a QueryClient) —
  the harness fixes incidentally green them again.
- Verified: 19/19 across the 4 files, `tsc --noEmit` clean, ESLint clean on changed files.

## Feature 0009 — Global quick-search + unified table toolbar — GREEN (verified by lead)

Spec `docs/specs/0009-table-search-and-unified-toolbar.md` (FROZEN). Full-stack. The
old detached `justify-end` buttons above the grid are gone: the table is now ONE
`rounded-xl border` block with a fused toolbar (search + live row count left;
reset-filters / saved-views / options `…` / fullscreen right). Column filtering stays
on the header menu (hover) — no toolbar filter toggle, no floating-filter row. The grid
drops its own wrapper border (`wrapperBorder:false`) to read continuous with the header.

Contract:
- `POST /tables/{domain}/rows` gains optional `search` (`nullable|string|max:100`,
  `TableRowsRequest::SEARCH_MAX_LENGTH`). Applied as a grouped OR-`LIKE` over the
  definition's `searchableColumnIds()` allow-list, AND-combined with `filterModel`,
  bound + LIKE-escaped (mirrors `FilterApplier`; `\` is MySQL's default LIKE escape).
- `GET /tables/{domain}/columns` `data` gains `searchable: string[]` (real columns only;
  `[]` ⇒ no search box). users → `['name','email']`, roles → `['name']`.
- New `TableDefinition::searchableColumnIds()`; `AbstractTableDefinition` derives it from
  column declarations flagged `'searchable' => true` and emits it in `resolveConfig()`.
  Only `AbstractTableDefinition` implements the interface → every domain inherits it.

Frontend:
- `TableToolbar` (new, presentational) + `useTableToolbarState` (new hook: search+⌘K,
  fullscreen w/ scroll-lock+Escape, live row count). `TableView` composes them and stays
  the orchestrator (under the 500 hard cap). Column filters stay on the header (hover) —
  no toolbar filter toggle, no floating-filter row (removed per user feedback).
- `createSsrmDatasource(domain, getSearch)`: term read lazily from a ref; typing debounces
  a `refreshServerSide({purge:true})` (datasource never rebuilt). `DataTable` gains
  `onRowCountChanged` (from `onModelUpdated`). Saved-views trigger is now icon-only.
- i18n keys added to it+en: `table.searchPlaceholder`, `table.rowCount_one/_other`,
  `table.options/export/fullscreen/exitFullscreen`, `common.soon/clear`. Export in the
  `…` menu is a disabled "soon" placeholder (per request).

Verified (all executed):
- Backend: `TableRowsSearchTest` (5) + `TableConfigTest` searchable assertion; full Table
  suite 118/118; **full backend suite 613 passed / 1 unrelated skip**; Pint clean.
- Frontend: `ssrm-datasource.test` (+2 search cases), `table-toolbar.test` (7); table+data-table
  suites 77 passed (the only 3 reds are the PRE-EXISTING `cell-renderers`/ContactsCell failures
  from concurrent 0005/0008 work — unchanged vs HEAD, not this feature). `tsc --noEmit` clean;
  ESLint clean on all changed files.

Not committed yet (working tree still commingles 0004/0006/0005/0008 concurrent work). The
grants/opportunities domain in the user's mockup does not exist — only `users`/`roles` consume
`TableView`; the toolbar is domain-agnostic and will cover a future domain for free.

## Settings page redesign (connected-user Impostazioni) — GREEN (verified)

Presentational redesign of `pages/settings-page.tsx` (self-service settings). Two-column on
desktop: a sticky identity + section-index rail (IntersectionObserver scroll-spy, reduced-motion
honored) beside icon-led section cards (Profilo, Sicurezza). Fields are lifted onto a muted
`FieldPanel` that forces the design-system `Input`/`SelectTrigger` (`data-slot`) to solid `bg-card`,
so inputs read as elevated white surfaces against the tinted panel — the brief's contrast/depth ask.

- Scope discipline: ONLY `settings-page.tsx` rewritten + one i18n key (`settings.sectionNavLabel`)
  per locale. The three form files (`profile-form`/`password-form`/`avatar-form`) and the shared
  `PersonalDataSection` were NOT touched (blast radius). The white-field override is scoped to the
  page via `data-slot` selectors → checkboxes (`type=checkbox`) and the hidden file input are safe.
- Verified: `tsc --noEmit` clean; ESLint clean on the page; `login-form` 3/3 (i18n smoke).
  `it.ts` typed `: TranslationResources` so tsc confirms the new key mirrors `en.ts`.
- The 5 reds in `profile-form.test.tsx` are PRE-EXISTING and independent: proven by `git stash` of
  my files → identical `useConfirm must be used within a ConfirmDialogProvider`
  (`confirm-dialog-context.ts:30`), from the concurrent uncommitted confirm-dialog work.
- Not committed (working tree still commingles concurrent sessions). No live browser render was
  done (headless); change is presentational/low-risk.

## User form + Role form redesign — GREEN (verified)

Presentational redesign of the User and Role create/edit forms (in the widened Sheet,
`sm:max-w-2xl`). Contract 0004/0006/0008 UNCHANGED — only presentation. Approved via an HTML
mockup first, then implemented on the real app tokens.

Design-system foundation (mine):
- New semantic tokens `--field` / `--field-border` (light: `#fff` on the grey body; dark: a surface
  lighter than the card) + `@theme` mappings → `bg-field` / `border-field-border`. `input.tsx` and
  `select.tsx` now use them instead of `bg-transparent` → fillable fields no longer blend into the
  page (the brief's #1 complaint). Verified in the built CSS: `.bg-field{background-color:var(--field)}`.
- New primitives: `components/ui/checkbox.tsx`, `components/ui/switch.tsx` (Radix, no new dep),
  and a reusable `components/form-section.tsx` (icon chip + title + description + aside slot).
- Sheet widened in `users-table.tsx` / `roles-table.tsx`.

Forms:
- User form (`user-form-body.tsx`): 5 `FormSection` cards — Anagrafica (personal-data card +
  avatar), Autenticazione, Ruoli e accessi, Contatti, Indirizzi. Personal-data composed directly
  from `PersonalDataCardForm`/`ContactsManager`/`AddressesManager` (buffered wiring preserved) so
  Anagrafica renders first WITHOUT touching the shared `PersonalDataSection` (still used by
  `ProfileForm`). `ContactsManager`/`AddressesManager` gained an optional `showHeader` prop
  (default true = old behavior). All fields still wrapped in `MetaField`; sections self-hide when
  all their fields are metadata-hidden.
- Role form (`role-form-body.tsx` + `role-field-permissions.tsx`): permissions grouped per domain
  card — primary abilities (`viewAny/view/create/update/delete`) visible as toggle pills, the rest
  (export/import…) under a per-domain `Collapsible` "Configurazione avanzata". Field-permission
  matrix kept as its own gated section (NOT nested per-domain: verified the field catalogue only
  registers `users`/`roles` while permission groups are broader — they don't align 1:1), redesigned
  as one `Collapsible` per resource with the `Checkbox` primitive. 0006 merge rule preserved exactly
  (mandatory locked; `required` disabled unless `editable`).

i18n: added `users.form.sections.*`, `roles.form.sections.*`, `roles.form.advanced(Actions)` to
both locales.

- Verified: `tsc --noEmit` clean; ESLint clean on changed scope; `vitest run` on
  users+roles+personal-data = 96/96; `vite build` exit 0 (field utilities/tokens confirmed).
- Pre-existing reds (NOT mine, proven by the concurrent sessions above via git-stash): 8 failures in
  `auth/profile-form.test.tsx` (needs the `ConfirmDialogProvider` test wrapper — same fix already
  applied to the user/personal-data tests) and `table/cell-renderers.test.tsx` (concurrent table work).
- Follow-ups (flagged, out of scope): `en.ts`/`it.ts` now >500 lines (code-guard hard limit) —
  grew from concurrent work + my keys; split the locale files once concurrent sessions settle.
  `secret-scan` on locale files is a known false positive. `user-form-body.tsx` (343) and
  `role-form-body.tsx` (363) exceed the 300 soft limit (under 500 hard) — optional sub-component split.
- Not committed (working tree commingled). A scoped commit of the redesign files is recommended.

## Feature 0010 — Business Functions module (Funzioni aziendali) — GREEN (verifier-confirmed)

Spec `docs/specs/0010-business-functions.xml` (contract FROZEN, user-approved). New module mirroring
`users`/`roles` exactly: generic SSRM table, metadata-driven form (convention
`docs/conventions/metadata-driven-forms.md`), field permissions, Policy authz server-side, envelope
`{ success, message, data, permissions? }`.

Naming decision (approved): greenfield ENGLISH. Model `App\Models\BusinessFunction`, table
`business_functions` (`name`, `is_business_unit`, `is_business_service` booleans, `manager_id`
nullable FK→users nullOnDelete) + pivot `business_function_user` (unique, cascadeOnDelete). Domain /
resource / route / permission key = **`business-functions`** (permissions
`business-functions.{viewAny,view,create,update,delete,export,import}`).

Contract to respect (frozen):
- Routes: `GET|POST|PATCH|DELETE /api/business-functions[/{businessFunction}]`; generic
  `tables/business-functions/*` + `meta/business-functions` (registry-driven, no new generic code).
  NO for-select for this module — it selects USERS via the existing `/api/users/for-select`.
- bu/bs are MUTUALLY EXCLUSIVE: write payload carries a single `type: 'business_unit'|'business_service'|null`,
  the Service maps it to the two boolean columns. Read exposes both booleans + `type`.
- Responsible + associated users both OPTIONAL. `users` = full-replace `sync`. `manager_id:null` clears.
- Resource/row `data` shape: `{ id, name, is_business_unit, is_business_service, type, manager_id,
  manager:{id,name,avatar_url}|null, user_ids[], users:[{id,name,avatar_url}], created_at }` (+ permissions).
- Table columns (order): `name, is_business_unit, is_business_service, manager, users, created_at`;
  `manager`/`users` are DERIVED (whereHas set-filter + distinct; manager sortable via correlated
  subquery) — bound params only, no `*Raw`.
- "WOW" UI: manager + associated users rendered as AVATARS with hover/focus TOOLTIP (name) in the grid;
  users cell is an avatar stack capped at 5 with a `+N` overflow chip. New reusable single-select
  `components/ui/async-paginated-select.tsx` (`value:number|null`) added for the responsabile picker;
  `AsyncPaginatedMultiSelect showAvatar` for associated users. Morph-map `'business_function'` added
  to `AppServiceProvider` (required by `LogsModelActivity`).

Verified (verifier, first-hand): backend module 58/58 (219 assert), full regression 725 passed / 1
pre-existing skip, coverage 95-100% per new file (exceeds gates), Pint clean; frontend module 53/53
across 7 files, `tsc --noEmit` clean, ESLint clean; all 20 acceptance criteria (AC-001..AC-020) have a
mapped, executed, passing test. Scope respected (zero edits to generic framework files). i18n
`common.clear/retry` confirmed present.

Not committed — working tree is COMMINGLED with a concurrent session's `companies` module
(spec 0010-companies-module, 0011-operational-sites) that shares the SAME modified files
(`router.tsx`, `en.ts`/`it.ts`, `config/{tables,authorization,navigation}.php`, `AppServiceProvider.php`,
`icon-map.ts`, `breadcrumbs.tsx`, `RolePermissionSeeder.php`). A cleanly-isolated 0010 commit is not
possible without separating interleaved hunks; awaiting a decision on how to land the two modules.
The pre-existing 8 frontend reds (`auth/profile-form`, `table/cell-renderers`) remain out-of-scope.

### 0010 — Seeder & factory added (later)

- `database/factories/BusinessFunctionFactory.php` enriched with states: `businessUnit()`,
  `businessService()` (exclusive type), `withManager(?User)`, `withUsers(int, $users?)` (afterCreating attach).
- `database/seeders/BusinessFunctionSeeder.php` (new): 15 curated demo functions (IT labels = UI content),
  each with a manager + 2..8 associated users drawn from the seeded user pool; deterministic faker seed,
  idempotent (firstOrNew by name + sync). Registered in `DatabaseSeeder` after `CompanySeeder`.
- `RolePermissionSeeder`: added `business-functions` to the `manager` (viewAny/view/create/update) and
  `operator` (viewAny/view) matrices, mirroring `companies`.
- Verified: `tests/Feature/BusinessFunctions/BusinessFunctionSeederTest.php` 6/6 (seeder count/relations/
  idempotency/users-less + factory states); full BusinessFunctions dir 64/64; Pint clean.

## Feature 0011 — Operational Sites (Sedi operative) — GREEN (verifier, first-hand)

Spec `docs/specs/0011-operational-sites.xml` (contract FROZEN). Mirrors `users`: generic SSRM table,
metadata-driven form (0004), field permissions (0006), Policy authz, envelope `{data, permissions?}`.

Domain decisions (user-approved): the site HAS NO own columns — it IS its address, stored via the
EXISTING polymorphic `addresses` table (`use HasAddresses`, one `is_primary` row). Geo mapping (same as
Users): regione=State, provincia=Province, comune=City, via=line1, cap=postal_code. No name/label field.

- Domain/resource/permission/route = `operational-sites` (hyphen); model `OperationalSite`; table
  `operational_sites` (id+timestamps only); morph alias `operational_site` (added to `enforceMorphMap`);
  route binding `{operationalSite}`. Permissions `operational-sites.{viewAny,view,create,update,delete,
  export,import}`.
- Contract (FROZEN): grid columns order `[id, city, street, postal_code, province, region, created_at]`,
  `searchable:['city','street']`, all address-DERIVED (set-filter geo city/province/region via whereHas +
  distinct-in-use + correlated-subquery sort; street/postal_code = text filter). CRUD payload is FLAT
  `{line1, postal_code, country_id, state_id, province_id, city_id}` (NO nested `address` object); `show`
  data = flat ids + nested `{id,name}` for country/region(=State)/province/city. `GET /meta/operational-sites`
  fields = `[country_id, state_id, province_id, city_id(mandatory), line1(mandatory), postal_code]`.
- ONE controlled generic extension (user-approved): new hook `applyDerivedSearch(Builder,columnId,pattern)`
  on `TableDefinition` + no-op default in `AbstractTableDefinition` + wired into `TableService::applySearch`
  (symmetric to existing `applyDerivedSort`, backward-compatible). `OperationalSitesTableDefinition`
  implements it for city+street. NO other generic file touched.
- Service reuses `AddressService.createFor/update` (polymorphic owner). 6 public accessors on
  `OperationalSite` (`line1/postalCode/countryId/...`) added so `EnforcesFieldPermissions` reads current
  address-derived values (else every blocked-field submit would falsely read as "changed" → 422 mismatch
  with Users). Factory `OperationalSiteFactory::withAddress(?City)`; seeder `OperationalSiteSeeder` (40 sites
  on real cities, deterministic, idempotent) registered after `UserAddressSeeder`.

Verified (verifier, first-hand): backend feature 60/60 (230 assert); full suite 791/792 (1 pre-existing
skip); generic-hook regression 113/113 across all existing search/table tests (hook confirmed no-op by
default via `git diff`); Pint clean on touched files. Frontend feature 44/44 across 6 files; `tsc --noEmit`
clean; ESLint clean. AC-001..019 PASS with mapped executed tests; AC-020 (cascade reset) relies on the
green dedicated test + reuse of existing `features/geo/geo-select` (not read line-by-line). Contract
coherence BE↔FE confirmed (flat payload, column ids/order, permission keys, i18n keys). Scope respected.

Correction to prior note: the pre-existing frontend red `auth/profile-form.test.tsx` root cause is a
MISSING `ConfirmDialogProvider` in its test wrapper (`contacts-manager.tsx:52`), NOT the locale — confirmed
reproducible on a clean stash. `table/cell-renderers.test.tsx` red IS the locale (it aria-label vs en
assertion). Both pre-existing, out-of-scope, owner = auth/personal-data + table modules.

Not committed — working tree is COMMINGLED across 0010-business-functions, companies, and 0011 sharing the
SAME modified files (`config/{tables,authorization,navigation}.php`, `AppServiceProvider.php`, `router.tsx`,
`en.ts`/`it.ts`, `icon-map.ts`, `breadcrumbs.tsx`, `RolePermissionSeeder.php`). A cleanly-isolated 0011-only
commit is not possible without splitting interleaved hunks; awaiting a decision on how to land the modules.
