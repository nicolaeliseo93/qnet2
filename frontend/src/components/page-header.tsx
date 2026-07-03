import type { ReactNode } from 'react'
import { AppBreadcrumbs } from '@/routes/breadcrumbs'

interface PageHeaderProps {
  /**
   * Page-level actions rendered on the same row as the breadcrumb, aligned to
   * the right. The primary create action of every table lives HERE (aligned to
   * the breadcrumb), not inside the table toolbar.
   */
  actions?: ReactNode
}

/**
 * Standard page header: breadcrumb on the left, page-level actions on the
 * right, on a single row. Shared by every table module so the "New entity"
 * button is always aligned with the breadcrumb — existing tables and future
 * ones alike.
 */
export function PageHeader({ actions }: PageHeaderProps) {
  return (
    <div className="flex items-center justify-between gap-4">
      <AppBreadcrumbs />
      {actions ? (
        <div className="flex items-center gap-2">{actions}</div>
      ) : null}
    </div>
  )
}
