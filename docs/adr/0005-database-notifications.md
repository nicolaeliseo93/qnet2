# Architecture Decision Record

## ADR ID

0005

## Title

User notifications via Laravel native database channel

## Status

ACCEPTED

## Date

2026-06-15

---

## Context

The product needs a reusable, domain-agnostic notification system: an authenticated
user must be able to list their notifications, distinguish read/unread, see the
unread count, mark one or all as read, and have the UI refresh periodically.

Constraints:

- The stack already standardizes on Laravel + Sanctum + Spatie Permission and a
  thin Controller → Service → Model flow with a standard response envelope
  (`BaseApiController`).
- `App\Models\User` already uses the `Notifiable` trait, so the framework's
  notification infrastructure is available with no new dependency.
- The feature must be reusable across modules without duplicating logic and must
  respect authentication, authorization, performance and error handling.

---

## Decision

Use **Laravel's native notification system with the `database` channel** as the
single source of truth for user notifications.

1. **Storage**: the canonical `notifications` table (uuid PK, `type`,
   `notifiable` morph, `data` JSON, `read_at`). One extra composite index on
   `(notifiable_type, notifiable_id, read_at)` to keep the unread-count and list
   queries cheap.
2. **Authorization by ownership, not by permission.** Native notifications are
   intrinsically per-user. Every endpoint operates exclusively on
   `auth()->user()->notifications()`; the client never supplies a user id and a
   single notification is resolved through the relationship (`->findOrFail()`),
   so a foreign uuid yields **404** with no cross-user access path. This mirrors
   the existing self-service avatar endpoints (`me/avatar`) — no Spatie permission
   and no navigation entry is introduced.
3. **Agnostic payload convention.** Notification classes write a `data` array of
   the shape `{ title, message, level, action_url }`. `NotificationResource`
   forwards `data` verbatim; the frontend renders it generically with fallbacks,
   so new notification types need no API/UI change.
4. **Thin layering.** `NotificationController` (validation + ownership scoping +
   response) → `NotificationService` (business logic, returns a typed
   `NotificationListResult` DTO) → the `DatabaseNotification` model via the
   `Notifiable` relationship.

---

## Alternatives Considered

- **Custom `notifications` table + custom model** — rejected: reinvents what the
  framework already provides on the existing `Notifiable` trait, more code to
  maintain, loses `notify()` / broadcast/mail channel reuse.
- **Spatie-permission gate (`notifications.view`)** — rejected: a user reading
  *their own* notifications is ownership, not a grantable capability; a permission
  would be misleading and add sync/seed overhead for no security gain.
- **WebSocket / broadcast push** — rejected for this iteration: polling satisfies
  the requirement with far less infrastructure; the database channel keeps the
  door open to add broadcasting later without changing the read API.

---

## Trade-offs

- Advantages: zero new dependencies, reuses framework + existing conventions,
  inherently per-user safe, trivially extensible to new notification types.
- Disadvantages: polling has higher latency than push and a periodic request cost
  (mitigated: lightweight unread-count poll; list fetched only when the panel is
  open; polling paused when the tab is hidden and when unauthenticated).
- We give up real-time delivery for now.

---

## Consequences

- A `notifications` migration is added; `php artisan migrate` required on deploy.
- The frontend polling interval is environment-configurable
  (`VITE_NOTIFICATIONS_POLL_INTERVAL`), tunable per environment with no code change.
- Future broadcasting can be layered on the same stored notifications.

---

## Affected Agents

Architect, Backend, Frontend, Security, Reviewer, QA, Documentation.

## Risks

- A notification class that omits `title`/`message` would render a fallback —
  handled gracefully on the client; documented as the payload convention.
- Aggressive poll intervals could add load — bounded by a sane default and the
  open-panel-only list fetch.

## References

- `docs/api/0004-notifications.md` (frozen API contract)
- `standards/architecture.md`, `standards/security-standards.md`
- Laravel docs: Notifications (database channel)
