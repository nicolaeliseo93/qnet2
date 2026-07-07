import { useTranslation } from 'react-i18next'
import { Building2, MapPin } from 'lucide-react'
import { type Control, type UseFormSetValue, useWatch } from 'react-hook-form'
import { AvatarUpload } from '@/components/avatar-upload'
import { FormSection } from '@/components/form-section'
import { Input } from '@/components/ui/input'
import { Textarea } from '@/components/ui/textarea'
import { FormControl } from '@/components/ui/form'
import { GeoSelect, type GeoValue } from '@/features/geo/geo-select'
import { MetaField } from '@/features/authorization/MetaField'
import { useResourcePermissions } from '@/features/authorization/permissions'
import type { CompanySiteFormMode } from '@/features/company-sites/company-site-form'
import type { CompanySiteFormValues } from '@/features/company-sites/use-company-site-form'

interface ProfileTabContentProps {
  mode: CompanySiteFormMode
  control: Control<CompanySiteFormValues>
  setValue: UseFormSetValue<CompanySiteFormValues>
  siteName: string
  onLogoFileSelected: (file: File | null) => void
  onLogoUpload: (file: File) => Promise<void>
  onLogoRemove: () => Promise<void>
  canUploadLogo: boolean
  canRemoveLogo: boolean
}

/**
 * Profilo tab: identity fields, the logo (avatar-upload pattern, collection
 * `logo`) and the site's single embedded polymorphic address (ADR 0010),
 * gated as a whole on the `address` field permission. Mirrors
 * `CompanyFormBody`'s address block and `IdentityTabContent`'s avatar modes.
 */
export function ProfileTabContent({
  mode,
  control,
  setValue,
  siteName,
  onLogoFileSelected,
  onLogoUpload,
  onLogoRemove,
  canUploadLogo,
  canRemoveLogo,
}: ProfileTabContentProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const addressPermission = fieldPermission('address')
  const addressDisabled = addressPermission.disabled || !addressPermission.editable

  const geoValue: GeoValue = {
    country_id: useWatch({ control, name: 'address.country_id' }) ?? null,
    state_id: useWatch({ control, name: 'address.state_id' }) ?? null,
    province_id: useWatch({ control, name: 'address.province_id' }) ?? null,
    city_id: useWatch({ control, name: 'address.city_id' }) ?? null,
  }

  return (
    <>
      <FormSection
        icon={Building2}
        title={t('companySites.form.sections.general.title')}
        description={t('companySites.form.sections.general.description')}
      >
        {mode.type === 'edit' ? (
          <AvatarUpload
            mode="immediate"
            label={t('companySites.form.logoLabel')}
            name={siteName}
            avatarUrl={mode.companySite.logo_url}
            onUpload={onLogoUpload}
            onRemove={onLogoRemove}
            canUpload={canUploadLogo}
            canRemove={canRemoveLogo}
          />
        ) : (
          <AvatarUpload
            mode="deferred"
            label={t('companySites.form.logoLabel')}
            name={siteName}
            onFileSelected={onLogoFileSelected}
          />
        )}

        <MetaField control={control} name="name" metaKey="name" label={t('companySites.form.name')}>
          {({ field, disabled, readOnly }) => (
            <FormControl>
              <Input autoComplete="off" disabled={disabled} readOnly={readOnly} {...field} />
            </FormControl>
          )}
        </MetaField>

        <MetaField control={control} name="email" metaKey="email" label={t('companySites.form.email')}>
          {({ field, disabled, readOnly }) => (
            <FormControl>
              <Input type="email" autoComplete="off" disabled={disabled} readOnly={readOnly} {...field} />
            </FormControl>
          )}
        </MetaField>

        <div className="grid grid-cols-1 gap-3 sm:grid-cols-2">
          <MetaField
            control={control}
            name="fiscal_code"
            metaKey="fiscal_code"
            label={t('companySites.form.fiscalCode')}
          >
            {({ field, disabled, readOnly }) => (
              <FormControl>
                <Input autoComplete="off" disabled={disabled} readOnly={readOnly} {...field} />
              </FormControl>
            )}
          </MetaField>

          <MetaField
            control={control}
            name="vat_number"
            metaKey="vat_number"
            label={t('companySites.form.vatNumber')}
          >
            {({ field, disabled, readOnly }) => (
              <FormControl>
                <Input autoComplete="off" disabled={disabled} readOnly={readOnly} {...field} />
              </FormControl>
            )}
          </MetaField>
        </div>

        <div className="grid grid-cols-1 gap-3 sm:grid-cols-3">
          <MetaField control={control} name="phone" metaKey="phone" label={t('companySites.form.phone')}>
            {({ field, disabled, readOnly }) => (
              <FormControl>
                <Input autoComplete="off" disabled={disabled} readOnly={readOnly} {...field} />
              </FormControl>
            )}
          </MetaField>

          <MetaField control={control} name="pec" metaKey="pec" label={t('companySites.form.pec')}>
            {({ field, disabled, readOnly }) => (
              <FormControl>
                <Input autoComplete="off" disabled={disabled} readOnly={readOnly} {...field} />
              </FormControl>
            )}
          </MetaField>

          <MetaField control={control} name="fax" metaKey="fax" label={t('companySites.form.fax')}>
            {({ field, disabled, readOnly }) => (
              <FormControl>
                <Input autoComplete="off" disabled={disabled} readOnly={readOnly} {...field} />
              </FormControl>
            )}
          </MetaField>
        </div>

        <MetaField control={control} name="notes" metaKey="notes" label={t('companySites.form.notes')}>
          {({ field, disabled, readOnly }) => (
            <FormControl>
              <Textarea disabled={disabled} readOnly={readOnly} {...field} />
            </FormControl>
          )}
        </MetaField>
      </FormSection>

      {addressPermission.visible && (
        <FormSection
          icon={MapPin}
          title={t('companySites.form.sections.address.title')}
          description={t('companySites.form.sections.address.description')}
        >
          <MetaField
            control={control}
            name="address.line1"
            metaKey="address"
            label={t('companySites.form.line1')}
          >
            {({ field, disabled, readOnly }) => (
              <FormControl>
                <Input autoComplete="address-line1" disabled={disabled} readOnly={readOnly} {...field} />
              </FormControl>
            )}
          </MetaField>

          <MetaField
            control={control}
            name="address.line2"
            metaKey="address"
            label={t('companySites.form.line2')}
          >
            {({ field, disabled, readOnly }) => (
              <FormControl>
                <Input autoComplete="address-line2" disabled={disabled} readOnly={readOnly} {...field} />
              </FormControl>
            )}
          </MetaField>

          <MetaField
            control={control}
            name="address.postal_code"
            metaKey="address"
            label={t('companySites.form.postalCode')}
          >
            {({ field, disabled, readOnly }) => (
              <FormControl>
                <Input autoComplete="postal-code" disabled={disabled} readOnly={readOnly} {...field} />
              </FormControl>
            )}
          </MetaField>

          <GeoSelect
            value={geoValue}
            onChange={(next) => {
              setValue('address.country_id', next.country_id)
              setValue('address.state_id', next.state_id)
              setValue('address.province_id', next.province_id)
              setValue('address.city_id', next.city_id)
            }}
            disabled={addressDisabled}
          />
        </FormSection>
      )}
    </>
  )
}
