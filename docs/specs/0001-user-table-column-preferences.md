# Feature Specification

> Template di specifica feature. Owner: Product Manager Agent.
> Companion of `docs/adr/0004-user-table-column-preferences.md` (Architect).

---

## Feature Name

Per-user table column preferences (personal table layout that sticks)

## Status

APPROVED

## Priority

High — generates immediate, recurring user value (every table user, every visit)
at low, bounded cost on top of the existing Table Registry.

---

## Product Summary

Each user can personalize any data table — column order, width, and visibility —
and have that layout restored automatically on every future visit. It is one
mechanism shared by every table (users, products, companies, tasks, orders, …);
no per-table product work.

---

## Problem Statement

Tables today look identical for everyone. Users who care about different columns
(or different widths/ordering) must re-arrange the grid on every visit, and lose
the arrangement on reload. This is repeated, wasted effort on a screen people use
daily.

---

## Target Users

Every authenticated user who works on a data table they are allowed to view. The
benefit scales with how often a user returns to the same table.

---

## User Value

The table remembers "how I like to work": fewer repeated adjustments, faster
orientation, a layout tuned to each user's job — with a one-click way back to the
standard layout if they over-customize.

---

## MVP Scope

### Incluso

- Persist, per user per table: **column order** (drag & drop), **column width**
  (resize), **column visibility** (show/hide).
- **Auto-save on change**: every adjustment is saved transparently (debounced),
  no explicit "save" button — minimal user effort.
- **Restore on load**: the saved layout is applied automatically when the table
  opens.
- **Reset to default** ("set columns to default"): one explicit action that
  discards the user's personalization and returns to the application default.
- Same behavior on every table (consistency over creativity).

### Escluso (→ Future Enhancements)

- Saved/multiple named layout presets.
- Saved sort and saved filters.
- Shared / role-level / team layouts.
- Import / export of layouts.

---

## User Flow

1. User opens a table → sees their previously saved layout (or the app default on
   first visit).
2. User reorders / resizes / hides a column → the change is saved automatically in
   the background. No confirmation, no save button.
3. User returns later (new session) → the table reopens exactly as they left it.
4. User clicks **Reset to default** → the table returns to the standard layout;
   their personalization is cleared. Preferences are **never** cleared any other
   way.

---

## Acceptance Criteria

- [ ] Reordering, resizing, or hiding a column persists without an explicit save.
- [ ] Reopening the table in a new session restores order, width, and visibility.
- [ ] A user only ever sees/affects **their own** layout (no cross-user effect).
- [ ] A user can personalize only tables they are allowed to view.
- [ ] "Reset to default" restores the application default and clears the saved
      layout; nothing else clears it.
- [ ] Removing or renaming a column in the table's default config does not break a
      user with an older saved layout (the obsolete entry is ignored).
- [ ] Behavior is identical across all tables (no per-table divergence).

---

## Success Criteria

- Measurable reduction in repeated layout adjustments per returning user (a
  returning user re-arranges columns far less than once per session).
- Adoption: a meaningful share of active table users have a saved layout within
  the first weeks, without support tickets about "my columns reset".

---

## Future Enhancements

Saved sort, saved filters, multiple named presets, role/shared layouts,
import/export. The storage (`preferences` JSON delta + merge service in ADR-0004)
is intentionally shaped to absorb these later **without a data migration** — but
they stay in the backlog until there is real demand (Avoid Feature Creep).

---

## Dependencies

- ADR-0002 generic Table Registry + `GET /api/tables/{domain}/columns` (the
  default schema this personalizes).
- ADR-0004 (Architect) — storage model, merge rules, `POST`/`DELETE`
  `…/preferences` endpoints, server-side sparse diff.

---

## Risks

- **Confusing "reset" semantics** — must be an obvious, explicit action and clearly
  scoped to "this table"; mitigated by a single labelled control and the flow
  above.
- **Silent save failures** — a failed background save must not make the user think
  their layout is kept; Frontend handles the error state (loading/error/empty
  triad) without nagging on the happy path.
- **Over-customization dead-end** — addressed by the reset action being in MVP.

---

## Next Owner

Backend Agent (implements ADR-0004: migration, model, merge service, endpoints,
validation), then Frontend Agent (AG Grid state ↔ debounced persistence + reset
control). Security validates self-scoping and the write whitelist before merge.
