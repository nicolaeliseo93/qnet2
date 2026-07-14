import type { ReactNode } from 'react'
import { AppBreadcrumbs } from '@/routes/breadcrumbs'

interface PageHeaderProps {
  /**
   * Page-level actions rendered on the same row as the breadcrumb, aligned to
   * the right. The primary create action of every table lives HERE (aligned to
   * the breadcrumb), not inside the table toolbar.
   */
  actions?: ReactNode
  /** Optional page title, rendered below the breadcrumb. */
  title?: string
  /** Optional page subtitle, rendered below the title. */
  subtitle?: string
}

/**
 * Standard page header: breadcrumb (+ optional title/subtitle) on the left,
 * page-level actions on the right. Shared by every table module so the
 * "New entity" button is always aligned with the breadcrumb — existing tables
 * and future ones alike. `title`/`subtitle` are opt-in: pages that only need
 * the breadcrumb row keep rendering exactly as before.
 */
export function PageHeader({ actions, title, subtitle }: PageHeaderProps) {
  return (
    <div className="flex items-center justify-between gap-4">
      <div className="flex flex-col gap-1">
        <AppBreadcrumbs />
        {title ? <h1 className="text-xl font-semibold">{title}</h1> : null}
        {subtitle ? <p className="text-sm text-muted-foreground">{subtitle}</p> : null}
      </div>
      {actions ? (
        <div className="flex items-center gap-2">{actions}</div>
      ) : null}
    </div>
  )
}
