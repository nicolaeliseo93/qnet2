import { useTranslation } from 'react-i18next'
import { Building2, History, MapPin, Receipt } from 'lucide-react'
import {
  DetailEmpty,
  DetailError,
  DetailField,
  DetailGrid,
  DetailHero,
  DetailLoading,
  DetailMeta,
  DetailMonogram,
  DetailPanel,
  DetailSection,
} from '@/components/detail/detail-panel'
import { formatDateTime } from '@/features/table/cell-renderers'
import { ActivityLogSection } from '@/features/activity-log/activity-log-section'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchCompany } from '@/features/companies/api'
import type { CompanyAddress } from '@/features/companies/types'

interface CompanyDetailProps {
  companyId: number
}

/**
 * Read-only detail of a single company, fetched fresh from the (re-authorized)
 * detail endpoint. Composed from the shared detail kit; rendered inside a Sheet.
 */
export function CompanyDetailView({ companyId }: CompanyDetailProps) {
  const { t } = useTranslation()
  const {
    data: company,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(['companies', 'detail', companyId], () => fetchCompany(companyId))

  if (isError) {
    return (
      <DetailError
        message={t('companies.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !company) {
    return <DetailLoading />
  }

  const createdAt = formatDateTime(company.created_at)

  return (
    <DetailPanel>
      <DetailHero
        media={<DetailMonogram name={company.denomination} icon={<Building2 />} />}
        title={company.denomination}
        subtitle={locationSummary(company.address)}
      />

      <DetailSection title={t('companies.form.sections.general.title')}>
        <DetailGrid>
          <DetailField label={t('companies.form.vatNumber')} icon={<Receipt />}>
            {company.vat_number || <DetailEmpty />}
          </DetailField>
        </DetailGrid>
      </DetailSection>

      <DetailSection title={t('companies.form.sections.address.title')} icon={<MapPin />}>
        <AddressBlock address={company.address} />
      </DetailSection>

      {company.permissions.actions.view_activity ? (
        <DetailSection title={t('activityLog.title')} icon={<History />}>
          <ActivityLogSection resource="companies" id={company.id} />
        </DetailSection>
      ) : null}

      {createdAt ? (
        <DetailMeta label={t('companies.columns.created_at')}>{createdAt}</DetailMeta>
      ) : null}
    </DetailPanel>
  )
}

/** A short "City, Country" line for the hero subtitle, or undefined. */
function locationSummary(address: CompanyAddress | null): string | undefined {
  if (!address) {
    return undefined
  }
  const summary = [address.city, address.country].filter(Boolean).join(', ')
  return summary || undefined
}

/** Street lines followed by the muted postal/geo summary. */
function AddressBlock({ address }: { address: CompanyAddress | null }) {
  if (!address) {
    return <DetailEmpty />
  }

  const summary = [address.postal_code, address.city, address.province, address.region, address.country]
    .filter(Boolean)
    .join(', ')

  return (
    <div className="flex flex-col gap-0.5 text-sm text-foreground">
      <span>{address.line1}</span>
      {address.line2 ? <span>{address.line2}</span> : null}
      {summary ? <span className="mt-1 text-muted-foreground">{summary}</span> : null}
    </div>
  )
}
