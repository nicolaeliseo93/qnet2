import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { Landmark, Mail, MapPin, Receipt, Star, Users } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { UserAvatar } from '@/components/user-avatar'
import {
  DetailEmpty,
  DetailError,
  DetailField,
  DetailGrid,
  DetailHero,
  DetailLoading,
  DetailMeta,
  DetailPanel,
  DetailSection,
} from '@/components/detail/detail-panel'
import { formatDateTime } from '@/features/table/cell-renderers'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchCompanySite, setDefaultCompanySite } from '@/features/company-sites/api'
import type { CompanySiteAddress, CompanySiteBank } from '@/features/company-sites/types'

interface CompanySiteDetailProps {
  companySiteId: number
  /** Called after a successful set-default so the caller can refresh the grid. */
  onDefaultChange?: () => void
}

/**
 * Read-only detail of a single company site, fetched fresh from the
 * (re-authorized) detail endpoint. Composed from the shared detail kit;
 * rendered inside a Sheet. Also hosts the "Società di Default" action
 * (AC-020): shown only when the site is not already the default.
 */
export function CompanySiteDetailView({ companySiteId, onDefaultChange }: CompanySiteDetailProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [settingDefault, setSettingDefault] = useState(false)
  const {
    data: site,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(['company-sites', 'detail', companySiteId], () =>
    fetchCompanySite(companySiteId),
  )

  if (isError) {
    return (
      <DetailError
        message={t('companySites.detail.loadError')}
        retryLabel={t('common.retry')}
        onRetry={() => refetch()}
      />
    )
  }

  if (isLoading || !site) {
    return <DetailLoading />
  }

  const handleSetDefault = async () => {
    setSettingDefault(true)
    try {
      const updated = await setDefaultCompanySite(site.id)
      queryClient.setQueryData(['company-sites', 'detail', site.id], {
        ...updated,
        permissions: site.permissions,
      })
      toast.success(t('companySites.form.defaultSet'))
      onDefaultChange?.()
    } catch {
      toast.error(t('companySites.form.defaultError'))
    } finally {
      setSettingDefault(false)
    }
  }

  const createdAt = formatDateTime(site.created_at)
  const canSetDefault = !site.is_default && site.permissions.actions.set_default

  return (
    <DetailPanel>
      <DetailHero
        media={<UserAvatar name={site.name} src={site.logo_url} className="size-14" />}
        title={site.name}
        subtitle={site.email}
        badges={
          site.is_default ? (
            <Badge variant="secondary">{t('companySites.detail.defaultBadge')}</Badge>
          ) : null
        }
      />

      {canSetDefault && (
        <div className="flex justify-end border-b px-6 py-3">
          <Button
            type="button"
            variant="secondary"
            size="sm"
            onClick={() => void handleSetDefault()}
            disabled={settingDefault}
          >
            <Star aria-hidden="true" />
            {settingDefault ? t('companySites.form.settingDefault') : t('companySites.form.setDefault')}
          </Button>
        </div>
      )}

      <DetailSection title={t('companySites.form.sections.general.title')}>
        <DetailGrid>
          <DetailField label={t('companySites.form.fiscalCode')} icon={<Receipt />}>
            {site.fiscal_code || <DetailEmpty />}
          </DetailField>
          <DetailField label={t('companySites.form.vatNumber')} icon={<Receipt />}>
            {site.vat_number || <DetailEmpty />}
          </DetailField>
          <DetailField label={t('companySites.form.phone')}>{site.phone || <DetailEmpty />}</DetailField>
          <DetailField label={t('companySites.form.pec')} icon={<Mail />}>
            {site.pec || <DetailEmpty />}
          </DetailField>
        </DetailGrid>
      </DetailSection>

      <DetailSection title={t('companySites.form.sections.address.title')} icon={<MapPin />}>
        <AddressBlock address={site.address} />
      </DetailSection>

      <DetailSection title={t('companySites.form.sections.banks.title')} icon={<Landmark />}>
        <BanksBlock banks={site.banks} />
      </DetailSection>

      <DetailSection title={t('companySites.form.sections.responsibles.title')} icon={<Users />}>
        <DetailGrid>
          <DetailField label={t('companySites.form.responsibleRda')}>
            {site.responsible_rda?.label || <DetailEmpty />}
          </DetailField>
          <DetailField label={t('companySites.form.responsibleTickets')}>
            {site.responsible_tickets?.label || <DetailEmpty />}
          </DetailField>
          <DetailField label={t('companySites.form.responsibleValidationContracts')}>
            {site.responsible_validation_contracts?.label || <DetailEmpty />}
          </DetailField>
          <DetailField label={t('companySites.form.responsibleValidationContractsTwo')}>
            {site.responsible_validation_contracts_two?.label || <DetailEmpty />}
          </DetailField>
        </DetailGrid>
      </DetailSection>

      {createdAt ? (
        <DetailMeta label={t('companySites.columns.created_at')}>{createdAt}</DetailMeta>
      ) : null}
    </DetailPanel>
  )
}

/** Street lines followed by the muted postal/geo summary (mirrors CompanyDetailView). */
function AddressBlock({ address }: { address: CompanySiteAddress | null }) {
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

/** The site's banks as a compact name + IBAN list. */
function BanksBlock({ banks }: { banks: CompanySiteBank[] }) {
  if (banks.length === 0) {
    return <DetailEmpty />
  }

  return (
    <ul className="flex flex-col gap-2">
      {banks.map((bank) => (
        <li key={bank.id} className="flex flex-col gap-0.5 text-sm">
          <span className="font-medium text-foreground">{bank.name}</span>
          {bank.iban ? <span className="text-muted-foreground">{bank.iban}</span> : null}
        </li>
      ))}
    </ul>
  )
}
