import { useTranslation } from 'react-i18next'
import { Building2, Globe, Hash, Map, MapPin, MapPinned } from 'lucide-react'
import {
  DetailEmpty,
  DetailField,
  DetailGrid,
  DetailHero,
  DetailMeta,
  DetailMonogram,
  DetailPanel,
  DetailSection,
} from '@/components/detail/detail-panel'
import { formatDateTime } from '@/features/table/cell-renderers'
import type { OperationalSiteDetail } from '@/features/operational-sites/types'

interface OperationalSiteDetailViewProps {
  operationalSite: OperationalSiteDetail
}

/**
 * Read-only detail of a single operational site. Purely presentational: the
 * caller (the table's "view" sheet) fetches the fresh detail and passes it
 * down. The alias headlines the panel; the street is a labeled field when an
 * alias exists, otherwise the street itself headlines it.
 */
export function OperationalSiteDetailView({ operationalSite }: OperationalSiteDetailViewProps) {
  const { t } = useTranslation()
  const createdAt = formatDateTime(operationalSite.created_at)
  const title = operationalSite.alias ?? operationalSite.line1

  return (
    <DetailPanel>
      <DetailHero
        media={<DetailMonogram name={title} icon={<MapPin />} />}
        title={title}
      />

      <DetailSection title={t('operationalSites.form.sections.address.title')} icon={<MapPinned />}>
        <DetailGrid>
          {operationalSite.alias ? (
            <DetailField label={t('operationalSites.detail.line1')} icon={<Building2 />} full>
              {operationalSite.line1}
            </DetailField>
          ) : null}
          <DetailField label={t('operationalSites.detail.postal_code')} icon={<Hash />}>
            {operationalSite.postal_code || <DetailEmpty />}
          </DetailField>
          <DetailField label={t('operationalSites.detail.city')} icon={<MapPin />}>
            {operationalSite.city?.name ?? <DetailEmpty />}
          </DetailField>
          <DetailField label={t('operationalSites.detail.province')} icon={<Map />}>
            {operationalSite.province?.name ?? <DetailEmpty />}
          </DetailField>
          <DetailField label={t('operationalSites.detail.region')} icon={<Map />}>
            {operationalSite.region?.name ?? <DetailEmpty />}
          </DetailField>
          <DetailField label={t('operationalSites.detail.country')} icon={<Globe />}>
            {operationalSite.country?.name ?? <DetailEmpty />}
          </DetailField>
        </DetailGrid>
      </DetailSection>

      {createdAt ? (
        <DetailMeta label={t('operationalSites.detail.created_at')}>{createdAt}</DetailMeta>
      ) : null}
    </DetailPanel>
  )
}
