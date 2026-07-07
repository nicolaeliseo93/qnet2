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
import { useNavigation } from '@/features/navigation/use-navigation'
import { resolveIcon } from '@/features/navigation/icon-map'
import type { NavigationItem } from '@/features/navigation/types'

/**
 * Maps a URL path segment to an i18n label key. Segments not listed here fall
 * back to a humanized version of the segment itself, so the breadcrumb never
 * breaks when a new route is added.
 */
const SEGMENT_LABELS: Record<string, string> = {
  dashboard: 'navigation.dashboard',
  users: 'navigation.users',
  roles: 'navigation.roles',
  companies: 'navigation.companies',
  'business-functions': 'navigation.businessFunctions',
  attributes: 'navigation.attributes',
  'product-categories': 'navigation.productCategories',
  'ea-sectors': 'navigation.eaSectors',
  products: 'navigation.products',
  // Namespaced key (`ns:key`): the migrations module registers its own
  // i18next namespace instead of merging into `en.ts`/`it.ts` (see
  // `features/migrations/i18n.ts`).
  migrations: 'migrations:nav.label',
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

/** Depth-first lookup of the navigation node whose route matches `route`. */
function findNavItemByRoute(
  items: NavigationItem[],
  route: string,
): NavigationItem | null {
  for (const item of items) {
    if (item.route === route) {
      return item
    }
    const nested = findNavItemByRoute(item.children, route)
    if (nested) {
      return nested
    }
  }
  return null
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
  const navigation = useNavigation()

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

  // The module icon is the one the sidebar uses for the top-level crumb: match
  // its route against the backend-driven navigation tree.
  const moduleItem = navigation.data
    ? findNavItemByRoute(navigation.data, crumbs[0].href)
    : null
  const ModuleIcon = moduleItem?.icon ? resolveIcon(moduleItem.icon) : null

  return (
    <Breadcrumb>
      <BreadcrumbList>
        {crumbs.map((crumb, index) => {
          const isLast = index === crumbs.length - 1
          const icon =
            index === 0 && ModuleIcon ? (
              <ModuleIcon className="size-4 shrink-0" aria-hidden />
            ) : null

          return (
            <Fragment key={crumb.href}>
              <BreadcrumbItem>
                {isLast ? (
                  <BreadcrumbPage className="flex items-center gap-1.5">
                    {icon}
                    {crumb.label}
                  </BreadcrumbPage>
                ) : (
                  <BreadcrumbLink asChild>
                    <Link to={crumb.href} className="flex items-center gap-1.5">
                      {icon}
                      {crumb.label}
                    </Link>
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
