import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { formatDateTime } from '@/features/table/cell-renderers'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchCompany } from '@/features/companies/api'
import type { CompanyAddress } from '@/features/companies/types'

interface CompanyDetailProps {
  companyId: number
}

/**
 * Read-only detail of a single company, fetched fresh from the
 * (re-authorized) detail endpoint. Handles loading and error states;
 * rendered inside a Sheet.
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
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive">{t('companies.detail.loadError')}</p>
        <Button variant="outline" size="sm" onClick={() => refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (isLoading || !company) {
    return (
      <div className="flex flex-col gap-4 p-4">
        <Skeleton className="h-6 w-1/2" />
        <Skeleton className="h-6 w-2/3" />
        <Skeleton className="h-6 w-1/3" />
      </div>
    )
  }

  const createdAt = formatDateTime(company.created_at)

  return (
    <dl className="flex flex-col gap-4 overflow-y-auto p-4 text-sm">
      <Field label={t('companies.form.denomination')}>{company.denomination}</Field>
      <Field label={t('companies.form.vatNumber')}>
        {company.vat_number || <EmptyValue />}
      </Field>
      <Field label={t('companies.form.sections.address.title')}>
        <AddressField address={company.address} />
      </Field>
      <Field label={t('companies.columns.created_at')}>{createdAt || <EmptyValue />}</Field>
    </dl>
  )
}

/** The address block: the street lines followed by the geo/postal summary line. */
function AddressField({ address }: { address: CompanyAddress | null }) {
  if (!address) {
    return <EmptyValue />
  }

  const summary = [address.postal_code, address.city, address.province, address.region, address.country]
    .filter(Boolean)
    .join(', ')

  return (
    <div className="flex flex-col gap-0.5">
      <span>{address.line1}</span>
      {address.line2 && <span>{address.line2}</span>}
      {summary && <span className="text-muted-foreground">{summary}</span>}
    </div>
  )
}

/** Em-dash placeholder for an empty field value. */
function EmptyValue() {
  return <span className="text-muted-foreground">—</span>
}

function Field({ label, children }: { label: string; children: ReactNode }) {
  return (
    <div className="flex flex-col gap-1">
      <dt className="font-medium text-muted-foreground">{label}</dt>
      <dd>{children}</dd>
    </div>
  )
}
