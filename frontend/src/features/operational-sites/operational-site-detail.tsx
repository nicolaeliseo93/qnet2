import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { formatDateTime } from '@/features/table/cell-renderers'
import type { OperationalSiteDetail } from '@/features/operational-sites/types'

interface OperationalSiteDetailViewProps {
  operationalSite: OperationalSiteDetail
}

/**
 * Read-only detail of a single operational site. Purely presentational: the
 * caller (the table's "view" sheet, owned elsewhere) fetches the fresh detail
 * and passes it down — this component owns no data-fetching state, mirroring
 * the field layout of `BusinessFunctionDetailView` for visual consistency.
 */
export function OperationalSiteDetailView({ operationalSite }: OperationalSiteDetailViewProps) {
  const { t } = useTranslation()
  const createdAt = formatDateTime(operationalSite.created_at)

  return (
    <dl className="flex flex-col gap-4 overflow-y-auto p-4 text-sm">
      <Field label={t('operationalSites.detail.alias')}>
        {operationalSite.alias || <EmptyValue />}
      </Field>
      <Field label={t('operationalSites.detail.line1')}>{operationalSite.line1}</Field>
      <Field label={t('operationalSites.detail.postal_code')}>
        {operationalSite.postal_code || <EmptyValue />}
      </Field>
      <Field label={t('operationalSites.detail.city')}>
        {operationalSite.city?.name ?? <EmptyValue />}
      </Field>
      <Field label={t('operationalSites.detail.province')}>
        {operationalSite.province?.name ?? <EmptyValue />}
      </Field>
      <Field label={t('operationalSites.detail.region')}>
        {operationalSite.region?.name ?? <EmptyValue />}
      </Field>
      <Field label={t('operationalSites.detail.country')}>
        {operationalSite.country?.name ?? <EmptyValue />}
      </Field>
      <Field label={t('operationalSites.detail.created_at')}>{createdAt || <EmptyValue />}</Field>
    </dl>
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
