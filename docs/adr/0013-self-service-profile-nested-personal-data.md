# Architecture Decision Record

## ADR ID
0013

## Title
Self-service profile manages PersonalData, Contacts and Addresses via the nested `/auth/me` write, reusing the Users module

## Status
ACCEPTED

## Date
2026-06-15

---

## Context
The authenticated user's profile page (settings) historically edited only flat
account fields (name, email, locale) through `PATCH /api/auth/me`
(`AuthController::updateProfile` → `AuthService::updateProfile`, a flat
`$user->update()`), plus avatar and password on dedicated endpoints. It could
not manage the reusable PersonalData module (registry card + polymorphic
Contacts + Addresses) that the Users admin module already manages.

Product requirement: the profile must let the user manage the identity sheet,
contacts (add/edit/delete + primary) and addresses (add/edit/delete + primary)
with an experience **identical** to the Users module, reusing components, logic
and forms integrally — no fork, no duplication — and reusing the same backend
application logic as the single source of truth for validations, relations and
CRUD.

Facts verified in the repository before deciding:
- The personal-data FE components are already owner-agnostic and controlled
  (`PersonalDataSection` value/onChange, no HTTP) — used by `UserForm`.
- The BE nested write is centralized: trait `ValidatesUserProfile`
  (FormRequest-agnostic), DTO `ProfileData`, and the upsert/sync logic inside
  `UserService` (one `DB::transaction`: `PersonalDataService::upsertFor` +
  `ContactService::sync` + `AddressService::sync`). See ADR 0012.
- `UserResource` already emits a `personal_data` block `whenLoaded`, but
  `AuthController::me()` did not eager-load it, so `GET /auth/me` omitted it.
- The per-entity endpoints (`/personal-data`, `/contacts`, `/addresses`) are
  gated by `{resource}.{ability}` permissions with **no ownership filter**
  (`BasePolicy`); `config/personal_data.php` maps `personable_type=user` to
  `User::class` but does not restrict which user.

---

## Decision
Extend the existing self-service endpoints rather than add new ones, mirroring
ADR 0012 for the admin path:

- `PATCH /api/auth/me` accepts an **optional** nested `personal_data` object
  (same wire shape as `PATCH /users/{user}`: card fields + optional `contacts[]`
  / `addresses[]`, with authoritative-sync collection semantics: key present —
  even `[]` — means authoritative sync incl. deletions; key absent means
  untouched; `personal_data` absent means the card is untouched). The owner is
  forced server-side to `auth()->user()`; the request **never** accepts
  `roles`, `password`, `personable_type` or `personable_id`.
- **Email is read-only on self-service (product decision, 2026-06-15):** the
  registration email cannot be changed via `/auth/me`. `PATCH /auth/me` accepts
  only `locale` + `personal_data`; an `email` in the payload is **silently
  ignored** (not validated, not written, response stays `200`). `GET /auth/me`
  continues to expose `email` for display. The email remains managed by the
  admin Users module (`StoreUserRequest`/`UpdateUserRequest`), unchanged.
- `GET /api/auth/me` eager-loads `personalData.contacts`/`personalData.addresses`
  and returns the same `personal_data` block via `UserResource` /
  `PersonalDataResource`.
- Validation and DTO assembly reuse `ValidatesUserProfile` verbatim
  (`profileRequired()` = false: update semantics).
- Persistence reuses the existing nested-write logic. `UserService::writeProfile`
  is **extracted into a shared `App\Services\ProfileWriter`** injected by both
  `UserService` and `AuthService`, so the upsert + sync invariants
  (single-primary demotion, owned-children diff, one transaction) and the
  `users.name` derivation are the single source of truth.
- **Full parity on the display name (product decision, 2026-06-15):** the flat
  `name` field is **removed** from the self-service profile. `users.name` is
  **derived from the card** (`CreatePersonalData::displayName()`) exactly as the
  Users admin path does — `ProfileWriter` is the sole derivation point. There is
  no free-text name on `/auth/me`.
- Authorization is **ownership by construction** (self-scoped to the
  authenticated user; the `/auth/me` routes carry no `{user}` id parameter). No
  Spatie permission is required and none of the `personal_data.*` / `contacts.*`
  / `addresses.*` permissions are granted to regular users.
- The self-service write routes are rate-limited (`throttle:60,1` for the
  profile, `throttle:6,1` for the password change) to align with the other
  authenticated write modules.
- Frontend reuses `PersonalDataSection` and `drafts.ts`
  (`emptyPersonalDataDraft`/`cardToDraft`/`draftToPayload`) with **zero component
  changes**; only `features/auth/{types,api,profile-form}` are touched.

---

## Alternatives Considered
- **Separate self-service CRUD endpoints** (`/auth/me/contacts`, ...) reusing the
  polymorphic endpoints — rejected: those endpoints have no ownership filter, so
  enabling them for self-service would require granting every user
  `personal_data.*` / `contacts.*` / `addresses.*` (cross-user IDOR) or building
  a new ownership layer; it also creates a second write contract that can drift
  from the admin one. Loses on security and maintainability.
- **Keeping a flat, free-text `name` on `/auth/me`** (additive, no card-derived
  name) — rejected by product in favor of full parity with the Users module;
  the name must come from the identity card so account name and card display
  name cannot diverge.
- **Client-side sequential calls** (card, then children) — rejected: not atomic,
  orphan rows on partial failure (same rationale as ADR 0012).

---

## Trade-offs
- Pro: one atomic self-service call; invariants and validation reused (single
  source of truth); identical admin/self experience; agnostic components stay
  agnostic; minimal new surface (no new routes).
- Con: to rename themselves users must fill the identity card (the card becomes
  the only source of the display name on self-service, consistent with Users).
- Giving up: the previous ability to set a free-text account name independent of
  the identity sheet.

---

## Consequences
- Positive: closes the `GET /auth/me` exposure gap; the profile gains full
  identity/contacts/addresses management with no fork; the `ProfileWriter`
  extraction removes the last private bottleneck and de-duplicates the write
  path across Users and self-service.
- Negative: personal-data sensitive fields (`tax_code`, `vat_number`,
  `birth_date`) are now exposed on a per-user self endpoint — widens the privacy
  surface (Legal gate, ADR 0006, governs release).
- Technical debt: none intended.

---

## Affected Agents
Architect, Backend, Frontend, Reviewer, QA, Security; the Legal gate (ADR 0006)
governs release of the personal-data exposure on `/auth/me`.

---

## Risks
- Cross-user child-id spoofing in the nested sync — mitigated by the services'
  existing owned-children intersection; covered by self-service ownership tests
  for both contacts and addresses.
- Forbidden-field injection (`roles`/`password`/`personable_*`) — mitigated by
  the request rules + the service forcing the owner + the `User` `#[Fillable]`
  whitelist; covered by tests.
- `ProfileWriter` extraction must not regress the Users write path — covered by
  the full Users suite (regression green).
- Privacy-surface widening (`tax_code`/`vat_number`/`birth_date`) — **Legal
  sign-off (ADR 0006) required before release**.
- Email change is currently not re-verified (pre-existing behavior, not
  introduced here) — to be confirmed with Product/Legal.

---

## References
- ADR 0006 (polymorphic personal-data / contacts modules — Legal gate).
- ADR 0010 (address geo cascade + primary flag).
- ADR 0012 (atomic user profile nested write — the admin path this mirrors).
- ADR 0011 (for-select API standard).
- Files:
  - `backend/app/Http/Requests/Concerns/ValidatesUserProfile.php`
  - `backend/app/DataObjects/Users/ProfileData.php`
  - `backend/app/Services/ProfileWriter.php`
  - `backend/app/Services/UserService.php`
  - `backend/app/Services/AuthService.php`
  - `backend/app/Http/Requests/Auth/UpdateProfileRequest.php`
  - `backend/app/Http/Controllers/Auth/AuthController.php`
  - `backend/app/Http/Resources/UserResource.php`, `PersonalDataResource.php`
  - `backend/routes/api.php`
  - `frontend/src/features/personal-data/*` (reused unchanged)
  - `frontend/src/features/auth/{types,api,profile-form}.tsx`
