# Architecture Decision Record

> ADR generato dal template `templates/architecture-decision.md`.
> Owner: Architect Agent.

---

## ADR ID

0003

## Title

AG Grid table loading skeleton (column-shaped SSRM placeholders)

## Status

ACCEPTED

## Date

2026-06-12

---

## Context

The generic, domain-driven DataTable (ADR-0002, building on ADR-0001) renders
its rows through the AG Grid **Server-Side Row Model (SSRM)**: rows are streamed
one block at a time from `POST /api/tables/{domain}/rows`. The table has two
distinct loading phases, and only the first was handled:

1. **Config phase** — `TableView` (`frontend/src/features/table/table-view.tsx`)
   loads the table schema via TanStack Query (`useTableConfig`). While
   `isPending`, it already shows a generic two-block skeleton (a header bar plus
   one tall block). At this point the columns are **not yet known**, so the
   placeholder cannot mirror the column layout.

2. **Row phase** — once the config is resolved, the grid mounts and the SSRM
   datasource streams rows. AG Grid's default behavior here is poor for our UX:
   - During an in-place block reload (sort, filter, page change) it shows a
     single full-width "Loading…" row.
   - On the **initial** load, before the first response reveals the row count,
     it paints a **single** loading row, not a full page.

The product request was explicit: while rows load, show a **skeleton that
follows the columns**, and render **as many skeleton rows as the page size**, not
a single line. The placeholder must stay domain-agnostic — the generic
`DataTable` must not learn anything about specific business columns.

Constraints:

- Stack is fixed (ADR-0001): AG Grid Enterprise + SSRM, React + TS.
- Reuse the existing shadcn `Skeleton` primitive (`@/components/ui/skeleton`);
  do not introduce a new animation or dependency.
- The generic `DataTable` wrapper must remain free of domain logic.

---

## Decision

Drive the row-phase loading state entirely through **native AG Grid SSRM
options**, rendering a column-shaped skeleton, in
`frontend/src/components/data-table/data-table.tsx`:

1. **Per-cell skeleton renderer** — a `SkeletonLoadingCell` component (reusing
   the shadcn `Skeleton`) is set as `loadingCellRenderer`. Combined with
   `suppressServerSideFullWidthLoadingRow: true`, AG Grid renders the placeholder
   **once per cell of every loading row**, so the skeleton mirrors the real
   column layout for free — without the wrapper knowing the data. The trailing,
   right-pinned actions column (keyed by the internal `ACTIONS_COLUMN_ID`
   constant) gets a narrower bar so the row reads as content rather than a solid
   block.

2. **Full page of skeleton rows on initial load** — `serverSideInitialRowCount`
   is seeded with `blockSize` (the backend `defaultPagination.limit`). Without
   it, SSRM paints a single loading row until the first response reveals the row
   count; seeding the count makes the grid paint a **full page** of skeleton rows
   immediately. In-place reloads (sort/filter/page) already replace a whole page
   of rows, which the per-cell renderer covers.

The **config phase** placeholder in `TableView` is intentionally left as the
generic two-block skeleton: the columns are unknown at that point, so a
column-shaped skeleton is not possible there.

This is a small, local, frontend-only change. Per `standards/orchestration.md`
(§3, "Per modifiche piccole e locali, l'Architect Agent può essere saltato") it
does not alter the API contract, the datasource, or the table config — it only
changes how the existing generic wrapper paints its loading state, and therefore
applies uniformly to **every** domain that mounts the table, not just `users`.

---

## Alternatives Considered

- **Custom AG Grid loading overlay (`loadingOverlayComponent`)** — rejected: for
  SSRM the overlay component is not used for block loading; the overlay wrapper
  rendered empty in practice. `serverSideInitialRowCount` + per-cell renderer is
  the idiomatic SSRM mechanism and far simpler.
- **A bespoke React skeleton table rendered as a sibling overlay, controlled by
  tracking SSRM loading state** — rejected: it would duplicate AG Grid's row
  virtualization, require threading loading state out of the datasource, and
  risk column misalignment with the real grid. Higher complexity, more debt
  (`standards/coding-standards.md` → Prefer Simplicity).
- **Leaving AG Grid's default single full-width loading row** — rejected: does
  not satisfy the product request (column-shaped, page-sized skeleton).
- **Default AG Grid skeleton (no `serverSideInitialRowCount`)** — rejected: shows
  a single skeleton row on the initial load instead of a full page.

---

## Trade-offs

- **Vantaggi**
  - Column-shaped skeleton with zero knowledge of the data — the generic wrapper
    stays domain-agnostic.
  - Reuses the existing `Skeleton` primitive and native AG Grid options; ~30 net
    lines, no new dependency, low maintenance surface.
  - Applies to all current and future table domains automatically.
- **Svantaggi**
  - `serverSideInitialRowCount: blockSize` is a heuristic: if the real result set
    is smaller than the page size, the grid briefly paints more skeleton rows
    than real rows and self-corrects on the first response. This is the intended
    visual trade-off and is handled by AG Grid's reconciliation.
  - Until the first response, AG Grid's pagination panel shows an estimated count
    (e.g. "Page 1 of more") because the true total is not yet known.
- **Cosa rinunciamo a ottenere**
  - A column-shaped skeleton during the **config** phase (columns unknown there).
  - The number of *visible* skeleton rows is bounded by the grid's fixed 600px
    height (~12 visible), even though a full `blockSize` page is seeded. Making
    the grid height dynamic is out of scope.

---

## Consequences

- **Positivi**: consistent, column-aware loading UX across every domain table;
  the placeholder structure is derived from the live `ColDef`s, so it can never
  drift from the real columns.
- **Negativi**: none structural. Two known transient cosmetics (over-paint on
  small result sets; estimated pagination count) documented above so a future
  maintainer does not "fix" the intended behavior.
- **Debito tecnico tracciabile**: no unit test yet for `SkeletonLoadingCell`
  (actions vs data column width). `standards/quality-gates.md` Definition of Done
  expects coverage; tracked as a follow-up for QA/Frontend (a small Vitest/RTL
  render test asserting `w-12` for the actions column and `w-[70%]` otherwise).

---

## Affected Agents

- **Frontend Agent** (owner of the implementation in `data-table.tsx`).
- **Reviewer** (reviewed: APPROVED WITH WARNINGS — only the missing unit test).
- **QA** (validate the loading states: initial load, sort, filter, page change,
  empty result set, dark mode; add the missing render test).
- **Documentation Agent** (this ADR).

---

## Risks

- **AG Grid API drift**: the behavior relies on `loadingCellRenderer`,
  `suppressServerSideFullWidthLoadingRow` and `serverSideInitialRowCount`. If AG
  Grid changes these in a future major, the loading UX (not correctness) is
  affected. Mitigation: options are centralized in one wrapper.
- **Coupling to `ACTIONS_COLUMN_ID`**: the skeleton special-cases the actions
  column by its internal id. The id is defined and consumed in the same file, so
  the coupling is internal and safe.
- **Untested presentational logic** (see Consequences → debito tecnico).

---

## References

- `docs/adr/0001-backend-driven-datatable-ag-grid-ssrm.md` — original SSRM table
  pattern (SUPERSEDED BY ADR-0002, design reused).
- `docs/adr/0002-generic-domain-driven-table-registry.md` — generic
  domain-driven table this loading state applies to.
- `docs/api/0002-generic-tables.md` — table config/rows contract (provides
  `defaultPagination.limit` used as `serverSideInitialRowCount`).
- Implementation: `frontend/src/components/data-table/data-table.tsx`
  (`SkeletonLoadingCell`, grid options); config-phase skeleton in
  `frontend/src/features/table/table-view.tsx`.
