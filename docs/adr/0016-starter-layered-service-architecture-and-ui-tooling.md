# Architecture Decision Record

## ADR ID

0016

## Title

Starter baseline: Laravel Layered Service Architecture, Animate UI, and
shadcn/ui Chart

## Status

ACCEPTED

## Date

2026-06-17

---

## Context

The repository acts as a reusable starter and local Agent OS reference. The
existing standards already describe thin Laravel controllers, service-owned
business logic, DTO boundaries, and a React/shadcn frontend stack, but two
parts were still implicit in the starter definition:

1. The backend pattern was described by rules, not by an explicit architectural
   name that new contributors can recognize immediately.
2. The frontend starter stack did not explicitly list the chosen motion and
   charting tooling, even though the project standardizes on shadcn-based UI
   composition and wants reusable conventions for interactive surfaces.

The user request is to add these choices to the starter:

- Laravel Layered Service Architecture
- Animate UI
- shadcn charts

Per `standards/decision-making.md`, a reusable starter-level architectural
choice must be documented explicitly.

## Decision

The starter baseline is updated as follows:

1. The Laravel backend pattern is named explicitly as **Laravel Layered Service
   Architecture**, with the canonical flow
   `Request -> FormRequest -> Controller -> Service -> Model -> Database`.
2. The frontend stack now explicitly includes **Animate UI** for motion
   primitives.
3. The frontend charting standard now explicitly includes **shadcn/ui Chart**
   with **Recharts** as the underlying chart engine.

These are documented in the architecture standard and reflected in the backend
and frontend starter README files.

## Alternatives Considered

- Keep the current standards as-is, with the pattern implied by the rules —
  rejected because onboarding remains slower and the starter is less explicit.
- Adopt a custom chart abstraction instead of shadcn/ui Chart + Recharts —
  rejected because it adds maintenance cost and conflicts with the "minimal
  dependencies and known solutions" rule.
- Use Framer Motion directly as the documented starter primitive — rejected as
  the starter request explicitly prefers Animate UI as the higher-level motion
  layer.

## Trade-offs

- Advantages
  - Faster onboarding: the backend pattern is recognizable by name.
  - Stronger consistency across starter-based projects.
  - Motion and charts follow one documented frontend path instead of ad hoc
    choices.
- Disadvantages
  - The starter standard now commits to a more opinionated frontend toolset.
  - Future swaps of motion or chart tooling will require a standards update and
    likely another ADR.
- What we give up
  - Some flexibility for teams that would otherwise choose a different motion or
    chart library by default.

## Consequences

- Positive
  - New developers and agents can identify the backend layering immediately.
  - Frontend work can reuse a clearer starter convention for animated surfaces
    and data visualization.
- Negative
  - The documentation must stay aligned with the real installed package layer in
    projects derived from this starter.
- Technical debt
  - None introduced in code. Documentation and starter manifests must remain in
    sync over time.

## Affected Agents

- Architect
- Backend
- Frontend
- Documentation
- Reviewer

## Risks

- If a derived project documents these tools but does not actually install them,
  onboarding can become misleading.
- Animate UI and shadcn chart installation details can evolve independently from
  this repository and should be validated when package manifests are updated.

## References

- `standards/architecture.md`
- `backend/README.md`
- `frontend/README.md`
- User request on 2026-06-17 to add these choices to the starter
