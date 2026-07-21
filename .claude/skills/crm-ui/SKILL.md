---
name: crm-ui
description: CRM interface design for THIS project's stack (React 19 + shadcn/ui new-york + Tailwind 4 + AG Grid SSRM). Concrete patterns for data tables, detail sheets, create/edit forms, toolbars/filters, status badges, and loading/empty/error states — compact enterprise density, anchored to components/ui and the project design tokens. Use when building or restyling any CRM screen (list, detail, form, dialog, toolbar, filter, stats). NOT for landing pages or marketing sites.
metadata:
  origin: project
---

# CRM UI

Interface design guidance for the CRM screens of this project. Operationalizes
`.claude/rules/ui-design.md` (§sizing compatto, §design-system unica fonte di verità)
into concrete CRM archetypes on the real stack. This skill **composes existing
primitives**, it does not introduce new dependencies or new base styles.

## When to Activate

- Building or restyling a list/table view, detail sheet, create/edit form, toolbar,
  filter bar, status badge, stats panel, or confirmation dialog.
- Reviewing a CRM screen for density, states coverage, or design-system adherence.
- Any `frontend/src/features/**` UI work that renders data-heavy enterprise screens.

Do NOT use for marketing/landing/portfolio pages — those are a different design
language and conflict with the compact enterprise density required here.

## Non-negotiables (inherit from project rules)

- **`components/ui/` is the only source of truth** for base style. Compose it; never
  duplicate or re-skin a primitive. Check it first (`badge`, `button`, `card`, `dialog`,
  `sheet`, `skeleton`, `stat-card`, `stat-bar-list`, `select`, `searchable-select`,
  `async-paginated-select`, `multi-select`, `form`, `dropdown-menu`, `alert-dialog`, ...).
- **Compact by default** (client preference, binding): tables/toolbars/chips at `text-xs`,
  minimal padding, `size-3.5` icons. Take the smaller end of the scale (`ui-design.md §2`).
  Enlarge only for WCAG legibility, tap target ≥24px, or a primary CTA.
- **No new dependencies.** No hard-coded colors — use CSS-variable tokens
  (`bg-card`, `text-muted-foreground`, `border`, `--primary`, `bg-destructive`, ...),
  theme-safe in light/dark.
- **UI hides, backend authorizes.** Gate visibility with `<Can>` (`features/auth/can.tsx`)
  / abilities — never rely on client checks for security.
- **Server data → TanStack Query** with per-feature `query-keys.ts`. Never fetch in JSX
  or `useEffect`. Business logic in hooks, JSX stays presentational (`frontend.md §2,§3,§6`).

## CRM screen archetypes

### 1. List / table view (the core CRM surface)
- Built on the generic table framework in `features/table/`: `table-view.tsx`,
  `ssrm-datasource.ts` (AG Grid Server-Side Row Model), `table-toolbar.tsx`,
  `row-actions.tsx`, cell renderers in `cell-renderers.tsx` / `rich-cells.tsx`
  (registered via `renderer-registry.ts`). A new tabular entity = new `TableDefinition`
  in the registry, **not** an ad-hoc grid or bespoke endpoints (`backend.md §4`).
- **Density:** compact rows, `text-xs`/`text-sm` cells, icon actions `size-3.5`.
- **Row actions:** first 3 inline, the rest under the `⋯` overflow menu
  (`INLINE_ACTION_LIMIT` in `row-actions.tsx` — do not raise it per-screen; it is a
  shared cross-table rule). Destructive action = destructive tint + confirm.
- **Sorting/filtering are server-driven** from an allow-list of columns, never raw input
  (`orderByRaw`/`whereRaw` are SQLi sinks — `backend.md §8`). Respect the SSRM contract:
  don't break the `columns`/`rows` shape or the params sent to the backend.
- **Cells that carry meaning** (status, user, tags, relations) get a rich renderer with a
  badge/avatar/icon, not a bare string — keep it compact and truncate long text.

### 2. Detail / module page — resizable sheet
- Use `components/ui/sheet.tsx` for the entity detail surface (see
  `features/users/user-detail-sheet.tsx` as the reference pattern). Sheet, not a full
  route reload, for in-context inspection; a dedicated page for deep module work.
- Structure: compact header (chip icon + title + subtitle/`SheetDescription`),
  scrollable body with sectioned content, sticky footer for primary action.
- Sections are `card`/`separator` grouped; label–value pairs on a tight grid.

### 3. Create / edit form
- **React Hook Form + Zod** (`components/ui/form.tsx`); form types derive from the Zod
  schema (single source of truth). Client validation mirrors server, never replaces it.
- **Dialog vs Sheet:** short form (≤ ~6 fields) → `dialog`; long/multi-section or
  side-by-side-with-context → `sheet`. Keep `DialogContent` compact (`sm:max-w-md`,
  `p-0` + inner padding bands when you want header/body/footer separation).
- Selects: `select` for small static sets; `searchable-select` for medium; 
  `async-paginated-select` / `async-paginated-multi-select` for server-backed large sets
  (respect their pagination contract). Never hand-roll a combobox.
- **Accessible error triad** (RHF+Zod does not wire ARIA for you — `frontend.md §10`):
  `aria-invalid={!!error}` + `aria-describedby={errorId}` + `<span id={errorId} role="alert">`.

### 4. Toolbar & filters
- One compact toolbar (`table-toolbar.tsx`): search, filter controls, saved views
  (`filter-views-control.tsx`), bulk-action slot, primary "New" CTA on the right.
- Chips/segmented controls: `text-xs px-2.5 py-1`, icon `size-3.5`. Active filter count as
  a small `badge`. Advanced filters live in `features/table/advanced-filters`.
- A filter that adds a value shows a removable chip; "clear all" when ≥1 active.

### 5. Status, badges, workflow state
- Status = `badge` with a **functional, token-based color** mapped from the status/group
  (see the statuses & workflow features: `opportunity-statuses`, `pipeline-statuses`,
  `opportunity-workflows`). Never encode state by color alone — pair with a label/icon
  (WCAG, `ui-design.md §4`). Keep the palette consistent across every screen showing that
  same status.

### 6. States — always cover all four
Every data surface must handle: **loading**, **empty**, **error**, **populated**.
- **Loading:** `skeleton` rows that match the real row layout (not a spinner over blank).
- **Empty:** icon-in-circle + short title + one-line hint (+ optional CTA). Keep the exact
  i18n key already used where one exists (e.g. `attachments.empty`).
- **Error:** inline banner with icon + human message from the `{ success, message }`
  envelope — never leak class/model names. Offer retry where it makes sense.
- Numeric conditionals use a ternary, not `&&`: `{count > 0 ? <Badge/> : null}`
  (`{count && ...}` prints a literal `0` — `frontend.md §10`).

### 7. Stats / KPI
- Use `stat-card.tsx` / `stat-bar-list.tsx` / `stat-chart` primitives (see `features/stats`
  and the module stats panel). Compact tiles, token colors, no bespoke chart lib.

## Density quick reference (from ui-design.md §2 — do not enlarge without reason)

| Element | Default |
|---|---|
| Body text | `text-sm` (`text-base` only for primary content) |
| Page heading | `text-xl` / `text-2xl` (never > `text-3xl`) |
| Card heading | `text-base font-semibold` |
| Card padding | `p-3` / `p-4` (`p-6` only large cards) |
| Button padding | `px-3 py-1.5` (`px-4 py-2` only primary CTA) |
| Tab / chip | `text-xs px-2.5 py-1`, icon `size-3.5` |
| Grid gap | `gap-3` / `gap-4` |
| Radius / shadow | `rounded-lg` / `shadow-sm` (`shadow-md` on hover/focus) |

## Anti-patterns (CRM-specific)

- Re-skinning or duplicating a `components/ui/` primitive instead of composing it.
- Oversized cards/tabs/buttons; giant hero-style headers on a data screen.
- Fixed heights/widths (`h-[500px]`) → use `min-h-`/`max-h-`/`h-auto`, `w-full max-w-*`.
- A `Button` that blends into the page (e.g. bare `variant="outline"` on a light body) —
  give it a fill so it detaches from the background (`frontend.md §9`).
- Hard-coded hex colors instead of tokens; state signalled by color only.
- Building a new grid/combobox/select from scratch when the framework/primitive exists.
- Skipping empty/error/loading states ("looks done" until the list is empty in prod).

## Pre-flight checklist

- [ ] Reused `components/ui/` (and `features/table/` for tables) — nothing re-skinned?
- [ ] Compact density (§2)? Smaller end chosen unless a real reason to enlarge?
- [ ] Tokens only (theme-safe light/dark), no hard-coded colors?
- [ ] Loading (skeleton) + empty + error + populated all handled?
- [ ] Server data via TanStack Query + `query-keys`; logic in hooks, JSX pure?
- [ ] `<Can>`-gated where actions are permission-bound (UI hides, backend authorizes)?
- [ ] Field errors wired with the accessible ARIA triad?
- [ ] 375 / 768 / 1024: no horizontal scroll, no overlap, focus visible, text truncated?
