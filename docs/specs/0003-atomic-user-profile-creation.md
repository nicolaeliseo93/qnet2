# Feature Spec 0003 — Atomic User + Profile creation

> Status: ACCEPTED · Date: 2026-06-15 · Owner: Architect → Backend + Frontend
> Related: ADR 0012, ADR 0006, ADR 0010.

## Problem

An operator must be able to create a user complete with its personal-data card,
contacts and addresses in a single step, with the same experience as edit, and
with all-or-nothing persistence.

## Behavior

### Backend — `POST /api/users`, `PUT|PATCH /api/users/{user}`

Accept the user account fields plus a nested `personal_data` object (see ADR 0012
for the exact wire contract). The user has **no client-supplied `name`**:
`users.name` is derived server-side from the card identity (individual →
`"First Last"`, company → `"Company Name"`), centralized in
`CreatePersonalData::displayName()` and applied consistently on create and update.
`personal_data` is **required on create** (sole source of the name) and optional
on update (when present, the name is re-derived).

- `personal_data` absent → user account only (today's behavior).
- `personal_data` present → upsert the card owned by the user (`personable`),
  then synchronize its `contacts` and `addresses` collections.
- A `contacts`/`addresses` key present is authoritative (empty array = delete all
  owned children); absent = leave that collection untouched.
- The whole operation is a single transaction; any failure rolls back the user.
- Authorized by `users.create` / `users.update` (the nested profile is part of
  managing the user — avatar precedent). Personal-data input is validated exactly
  as the per-entity endpoints validate it (per-type contact `value`, geo
  `exists`, per-type personal-data name requirements, `birth_date` before today).
- The response (`UserResource`) includes the persisted `personal_data` tree.

### Frontend — user create + edit form

- The agnostic personal-data components become controlled (buffered). The user
  form owns the draft and submits it within the single user payload.
- The personal-data card is ALWAYS active in both create and edit (no add/remove
  affordance). The identity fields are mandatory: first + last name for an
  individual, company name for a company. The save is blocked client-side until
  they are valid (the backend enforces the same per-type rule).
- Create: the section is shown from the start with a blank, active card.
- Edit: the section is seeded from the loaded card (or a blank active card when
  the user has none yet) and the same single Save sends the full tree
  (creates/updates/deletes children by diff).
- Loading uses skeletons; the form handles loading / error / empty and 422
  mapping onto nested fields.

## Acceptance Criteria

Backend (Pest feature + service tests):

1. Create user with full `personal_data` (card + ≥1 contact + ≥1 address) → 201,
   all rows persisted, response carries the tree.
2. Create user without `personal_data` → 201, no card (back-compat).
3. Invalid nested data (e.g. bad contact `value` for type, non-existent
   `city_id`, missing required name for `individual`) → 422 with nested error
   keys, and **no user row created** (rollback).
4. Update adds, updates and removes children by diff; absent collection key
   leaves it untouched; empty array deletes all.
5. Cross-card id in payload is treated as create, never updates another card's
   child.
6. Single-primary invariants hold after a batch (one primary contact per type,
   one primary address per owner).
7. Authorization: 403 without `users.create` / `users.update`; no
   `personal_data.*` permission required on this path.

Frontend (Vitest + RTL):

8. Create form renders the always-active personal-data section (no add button)
   and submits one request with the nested payload; the save is blocked when the
   mandatory identity fields are missing.
9. Edit form seeds from the card and submits the diff in one request.
10. Controlled managers add/edit/remove items in the buffer without network
    calls; validation errors surface inline.

## Out of scope

- Changing the standalone per-entity endpoints.
- The Legal release gate of ADR 0006 (unchanged).
