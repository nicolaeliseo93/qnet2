import { useTranslation } from 'react-i18next'
import { Info, MapPin, Phone } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
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
import { enumLabelOf } from '@/features/config/enum-label'
import { formatDateTime } from '@/features/table/cell-renderers'
import { AddressesManager } from '@/features/personal-data/addresses-manager'
import { ContactsManager } from '@/features/personal-data/contacts-manager'
import { cardToDraft } from '@/features/personal-data/drafts'
import type { PersonalDataFieldPermissionResolver } from '@/features/personal-data/types'
import type { ReferenceRef, RegistryDetail } from '@/features/registries/types'

/**
 * Renders the (owner-agnostic, reused unchanged) contacts/addresses managers
 * in pure read-only mode: visible, never editable — no add/edit/remove
 * affordance shows up.
 */
const READ_ONLY_FIELD_PERMISSION: PersonalDataFieldPermissionResolver = () => ({
  visible: true,
  editable: false,
  required: false,
  disabled: false,
  readonly: true,
})

/** No-op change handler: the read-only managers never call it. */
function noopChange(): void {}

/** Comma-joined names of a relation list, or the empty-value placeholder. */
function refList(refs: ReferenceRef[]) {
  return refs.length > 0 ? refs.map((ref) => ref.name).join(', ') : <DetailEmpty />
}

interface RegistryDetailViewProps {
  registry: RegistryDetail
}

/**
 * Read-only detail of a single registry, fetched fresh from the
 * (re-authorized) detail endpoint. Composed from the shared detail kit;
 * rendered inside a Sheet. Contacts/addresses reuse the same managers as the
 * form (in read-only mode) so the card content never diverges from what the
 * edit form would show (spec 0020).
 */
export function RegistryDetailView({ registry }: RegistryDetailViewProps) {
  const { t } = useTranslation()
  const createdAt = formatDateTime(registry.created_at)
  const draft = registry.personal_data ? cardToDraft(registry.personal_data) : null

  return (
    <DetailPanel>
      <DetailHero
        media={<DetailMonogram name={registry.name} />}
        title={registry.name}
        badges={
          <>
            <Badge variant="secondary">
              {registry.is_supplier ? t('common.yes') : t('common.no')} — {t('registries.form.isSupplier')}
            </Badge>
            {registry.agreement_status && (
              <Badge variant="secondary">
                {enumLabelOf('agreement_status', registry.agreement_status)}
              </Badge>
            )}
          </>
        }
      />

      <DetailSection title={t('registries.detail.details')} icon={<Info />}>
        <DetailGrid>
          <DetailField label={t('registries.form.source')}>
            {registry.source?.name ?? <DetailEmpty />}
          </DetailField>
          <DetailField label={t('registries.form.vatGroup')}>
            {registry.vat_group || <DetailEmpty />}
          </DetailField>
          <DetailField label={t('registries.form.sizeClass')}>
            {registry.size_class ? enumLabelOf('size_class', registry.size_class) : <DetailEmpty />}
          </DetailField>
          <DetailField label={t('registries.form.employeeCount')}>
            {registry.employee_count ?? <DetailEmpty />}
          </DetailField>
          <DetailField label={t('registries.form.supervisor')}>
            {registry.supervisor?.name ?? <DetailEmpty />}
          </DetailField>
          <DetailField label={t('registries.form.commercial')}>
            {registry.commercial?.name ?? <DetailEmpty />}
          </DetailField>
          <DetailField label={t('registries.form.reporter')}>
            {registry.reporter?.name ?? <DetailEmpty />}
          </DetailField>
          <DetailField label={t('registries.form.sectors')} full>
            {refList(registry.sectors)}
          </DetailField>
          <DetailField label={t('registries.form.referents')} full>
            {refList(registry.referents)}
          </DetailField>
          <DetailField label={t('registries.form.managers')} full>
            {refList(registry.managers)}
          </DetailField>
          <DetailField label={t('registries.form.agreementNotes')} full>
            {registry.agreement_notes || <DetailEmpty />}
          </DetailField>
        </DetailGrid>
      </DetailSection>

      <DetailSection title={t('registries.form.sections.contacts.title')} icon={<Phone />}>
        {draft ? (
          <ContactsManager
            value={draft.contacts}
            onChange={noopChange}
            fieldPermission={READ_ONLY_FIELD_PERMISSION}
            showHeader={false}
          />
        ) : (
          <DetailEmpty />
        )}
      </DetailSection>

      <DetailSection title={t('registries.form.sections.addresses.title')} icon={<MapPin />}>
        {draft ? (
          <AddressesManager
            value={draft.addresses}
            onChange={noopChange}
            fieldPermission={READ_ONLY_FIELD_PERMISSION}
            showHeader={false}
            showSiteType
          />
        ) : (
          <DetailEmpty />
        )}
      </DetailSection>

      {createdAt ? (
        <DetailMeta label={t('registries.columns.created_at')}>{createdAt}</DetailMeta>
      ) : null}
    </DetailPanel>
  )
}
