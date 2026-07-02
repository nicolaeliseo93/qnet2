import { Fragment } from 'react'
import { Link, useLocation } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import {
  Breadcrumb,
  BreadcrumbItem,
  BreadcrumbLink,
  BreadcrumbList,
  BreadcrumbPage,
  BreadcrumbSeparator,
} from '@/components/ui/breadcrumb'

/**
 * Maps a URL path segment to an i18n label key. Segments not listed here fall
 * back to a humanized version of the segment itself, so the breadcrumb never
 * breaks when a new route is added.
 */
const SEGMENT_LABELS: Record<string, string> = {
  dashboard: 'navigation.dashboard',
  users: 'navigation.users',
  roles: 'navigation.roles',
  settings: 'navigation.settings',
  login: 'auth.signInTitle',
  'forgot-password': 'auth.forgotPasswordTitle',
  'reset-password': 'auth.resetPasswordTitle',
}

function humanize(segment: string): string {
  return segment
    .replace(/-/g, ' ')
    .replace(/\b\w/g, (c) => c.toUpperCase())
}

/** Resolves the i18n label for a single URL segment, with a safe fallback. */
function useSegmentLabel(segment: string | undefined): string {
  const { t, i18n } = useTranslation()
  if (!segment) return ''
  const key = SEGMENT_LABELS[segment]
  return key && i18n.exists(key) ? t(key) : humanize(segment)
}

/**
 * Plain page title for the current route (the last URL segment), rendered in
 * the top app bar. This is a heading, NOT a breadcrumb.
 */
export function AppPageTitle({ className }: { className?: string }) {
  const { pathname } = useLocation()
  const segments = pathname.split('/').filter(Boolean)
  const label = useSegmentLabel(segments[segments.length - 1])

  if (!label) {
    return null
  }

  return <h1 className={className ?? 'text-base font-semibold'}>{label}</h1>
}

interface Crumb {
  label: string
  href: string
}

/**
 * Breadcrumb derived directly from the current URL (no router `handle` /
 * `useMatches` dependency). For `/users` it renders a single "Utenti" crumb;
 * for nested paths it renders the full hierarchy.
 */
export function AppBreadcrumbs() {
  const { t, i18n } = useTranslation()
  const { pathname } = useLocation()

  const segments = pathname.split('/').filter(Boolean)

  if (segments.length === 0) {
    return null
  }

  const crumbs: Crumb[] = segments.map((segment, index) => {
    const href = '/' + segments.slice(0, index + 1).join('/')
    const key = SEGMENT_LABELS[segment]
    const label = key && i18n.exists(key) ? t(key) : humanize(segment)
    return { label, href }
  })

  return (
    <Breadcrumb>
      <BreadcrumbList>
        {crumbs.map((crumb, index) => {
          const isLast = index === crumbs.length - 1

          return (
            <Fragment key={crumb.href}>
              <BreadcrumbItem>
                {isLast ? (
                  <BreadcrumbPage>{crumb.label}</BreadcrumbPage>
                ) : (
                  <BreadcrumbLink asChild>
                    <Link to={crumb.href}>{crumb.label}</Link>
                  </BreadcrumbLink>
                )}
              </BreadcrumbItem>
              {!isLast ? <BreadcrumbSeparator /> : null}
            </Fragment>
          )
        })}
      </BreadcrumbList>
    </Breadcrumb>
  )
}
