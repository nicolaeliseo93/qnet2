import { useTranslation } from 'react-i18next'
import { Badge } from '@/components/ui/badge'
import { geoScopeLabelKey, type GeoScope } from '@/features/geo/geo-scope'

interface GeoScopeBadgeProps {
  scope: GeoScope
  /**
   * The finest non-null level's name, e.g. "Milano" for a `city` scope.
   * Omit it where no single place name applies (e.g. the projects table's
   * `geo_scope` column, next to its own country/state/province/city columns):
   * the badge then shows the scope label alone.
   */
  place?: string
}

/**
 * Compact, reusable badge for the derived geo scope (spec 0027 D-2): the
 * scope label (Nazionale/Regionale/Provinciale/Cittadino), optionally plus
 * the matching place name. Shared by the project detail, project card,
 * projects table and campaign detail — domain-agnostic, it only speaks the
 * `GeoScope` contract.
 */
export function GeoScopeBadge({ scope, place }: GeoScopeBadgeProps) {
  const { t } = useTranslation()

  return (
    <Badge variant="secondary" className="max-w-full gap-1 text-xs">
      <span className="shrink-0">{t(geoScopeLabelKey(scope))}</span>
      {place ? (
        <>
          <span className="shrink-0 text-muted-foreground" aria-hidden="true">
            -
          </span>
          <span className="truncate">{place}</span>
        </>
      ) : null}
    </Badge>
  )
}
