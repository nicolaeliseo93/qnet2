# Architecture Decision Record

## ADR ID

0012

## Title

Atomic nested write of a User with its PersonalData, Contacts and Addresses

## Status

ACCEPTED

## Date

2026-06-15

---

## Context

A user can today be created with its bare account fields only (name, email,
locale, password, roles). The reusable PersonalData module (ADR 0006) — the
registry card plus its polymorphic Contacts and Addresses — could only be filled
*after* the user existed, through the separate per-entity endpoints
(`/personal-data`, `/contacts`, `/addresses`). An operator therefore had to save
the user first and re-open it in edit to complete the profile.

The product requirement is that the full structure (User → PersonalData →
Contacts/Addresses) can be entered and persisted in a single step, with the same
experience in create and edit, and that a failure anywhere rolls the whole thing
back so no half-provisioned user is ever left behind.

Constraints:

- The ownership chain (`User` morphOne `PersonalData` morphMany
  `Contacts`/`Addresses`) only acquires ids *during* the save, so the client
  must send the tree by structure, not by id.
- The frontend must reuse the existing owner-agnostic components, with no
  user-only implementation (requirement).
- Personal data is sensitive; the per-entity endpoints remain gated behind the
  Legal sign-off noted in ADR 0006.

---

## Decision

**Backend.** Extend the existing user write endpoints rather than adding new
ones:

- `POST /api/users` and `PUT|PATCH /api/users/{user}` accept a nested
  `personal_data` object that itself carries optional `contacts[]` and
  `addresses[]` arrays. The user has **no client-supplied `name`**: `users.name`
  is derived server-side from the card (single source of truth) via
  `CreatePersonalData::displayName()` — individual → `"First Last"`, company →
  `"Company Name"` (whitespace-collapsed, capped to 255). Because the name comes
  from the card, `personal_data` is **required on create**; on update it stays
  optional and, when present, re-derives `users.name` (absent → name untouched).
  *(Amends the original "optional / backward compatible" stance for the create
  path — see the updated requirement.)*
- The whole operation runs inside **one** `DB::transaction` in `UserService`;
  any validation or persistence failure rolls back the user too.
- Persistence is delegated to the existing services so their invariants are
  reused, not duplicated: `PersonalDataService::upsertFor`, and new
  `ContactService::sync` / `AddressService::sync` that diff the submitted
  collection against the card's current children (id present & owned → update;
  id absent or not owned → create; existing & owned but missing from payload →
  delete).
- Authorization for the nested profile is the **user ability itself**
  (`users.create` / `users.update`) — managing a user's personal data is part of
  managing the user, exactly as the avatar endpoints are gated by `users.update`.
  No separate `personal_data.*` permission is required on this path.
- `UserResource` gains a `personal_data` block emitted only `whenLoaded`, so the
  single response returns the freshly persisted tree.

**Wire contract** (the integration boundary):

```jsonc
{
  // NO "name": users.name is derived from personal_data server-side.
  "email": "...", "locale": "en",
  "password": "...", "password_confirmation": "...",
  "roles": ["editor"],
  "personal_data": {                      // REQUIRED on create; optional on update
    "type": "individual",                 // required when personal_data present
    "title": "mr",
    "first_name": "...", "last_name": "...", "company_name": null,
    "tax_code": "...", "vat_number": "...", "birth_date": "1990-01-01",
    "contacts": [                          // optional; present → authoritative sync
      { "id": 12, "type": "email", "value": "a@b.com", "label": "Work", "is_primary": true },
      { "type": "phone", "value": "+39 333 1234567", "is_primary": false }
    ],
    "addresses": [                         // optional; present → authoritative sync
      { "id": 5, "line1": "...", "city_id": 1, "state_id": 2, "country_id": 3, "is_primary": true }
    ]
  }
}
```

Collection semantics: a `contacts`/`addresses` key **present** is authoritative
(empty array deletes all owned children); **absent** leaves that collection
untouched. `personal_data` **absent** leaves the card untouched.

**Frontend.** Convert the agnostic personal-data components from
self-contained (each calling the API on its own with an existing `ownerId`) to
**controlled / buffered** components (`value` + `onChange`). `PersonalDataSection`
holds the whole draft tree; `UserForm` owns that state for both create and edit
and submits it inside the single user payload. The card is always active in both
flows (no add/remove affordance) and its identity fields (name + surname, or
company name) are mandatory — the save is blocked client-side until valid. In edit
the buffer is seeded from the loaded card (or a blank active card when there is
none); in create it starts blank. No per-entity network calls remain in the user
flow.

---

## Alternatives Considered

- **New dedicated endpoint (`POST /api/users/full`)** — rejected: duplicates
  routing/validation and creates two parallel creation contracts to maintain; the
  optional nested field on the existing endpoint achieves atomicity with less
  surface.
- **Sequential client-side calls (create user, then card, then children) with
  client-side compensation on failure** — rejected: not truly atomic, leaves
  orphan rows on partial failure, pushes transactional responsibility to the
  browser.
- **Pre-allocating a user id / draft user** — rejected: leaks half-built users
  and breaks the single-primary and one-card invariants.
- **Keeping edit self-contained, buffering only create** — rejected by product
  decision (unify edit too); a single buffered model removes the create/edit
  behavioral split.

---

## Trade-offs

- **Pro:** one atomic call, backward compatible, invariants reused from the
  existing services, identical create/edit experience, agnostic components stay
  agnostic (controlled, still no user-specific code).
- **Con:** the agnostic components lose their immediate-persistence behavior in
  favor of a parent-owned buffer; their tests are rewritten accordingly.
- **Giving up:** per-entity incremental persistence within the user form (a field
  is no longer saved the instant it is added; it is saved on the form Save).

---

## Consequences

- The per-entity endpoints (`/personal-data`, `/contacts`, `/addresses`) remain
  unchanged and available for other owners and direct use; only the user flow
  switches to the nested write.
- The nested sync centralizes the diff in the domain services, keeping the
  controller thin.
- Tech debt: none introduced intentionally. The duplicated validation shape
  (nested vs per-entity rules) is mitigated by reusing the enums' rule sources
  and a shared FormRequest concern.

---

## Affected Agents

Architect, Backend, Frontend, Reviewer, QA, Security, Documentation; Legal gate
(ADR 0006) still governs release of personal-data exposure.

---

## Risks

- Sync diff edge cases (primary flag across a batch, cross-card id spoofing) —
  mitigated by intersecting payload ids with the card's owned children and by the
  services' existing single-primary demotion.
- Coverage of the new transactional path must include rollback (no orphan user on
  nested failure).

---

## References

- ADR 0006 (personal-data polymorphic modules), ADR 0010 (address geo + primary),
  ADR 0011 (for-select).
- Spec `docs/specs/0003-atomic-user-profile-creation.md`.
