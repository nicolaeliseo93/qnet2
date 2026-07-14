import { Globe } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import { useFormState, useWatch, type Control, type UseFormSetValue } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { GeoSelect, type GeoValue } from '@/features/geo/geo-select'
import { useResourcePermissions } from '@/features/authorization/permissions'
import type { CampaignFormValues } from '@/features/campaigns/use-campaign-form'

interface CampaignGeoSectionProps {
  control: Control<CampaignFormValues>
  setValue: UseFormSetValue<CampaignFormValues>
}

/** Field-permission keys backing the geo cascade (spec 0004/0027 meta contract). */
const GEO_META_KEYS = ['country_id', 'state_id', 'province_id', 'city_id'] as const

/**
 * The campaign's geo cascade (spec 0027 BR-4/BR-5): reuses `<GeoSelect>`
 * exactly like `OperationalSiteFormBody` (never duplicated), bridged to RHF
 * via `useWatch`/`setValue`. Field-permission visibility/edit state is
 * aggregated across the 4 levels, mirroring the operational-site cascade
 * (the unit is not worth rendering unless at least one level is visible, and
 * is locked as a whole the moment any level is not editable) — on top of
 * that, individual levels the linked project already fills are locked via
 * `lockedLevels` (BR-5), independently of field permissions. Extracted out of
 * `CampaignFormBody` for the engineering size limits (mirrors why
 * `CampaignPlanningSection` was split out).
 */
export function CampaignGeoSection({ control, setValue }: CampaignGeoSectionProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const { errors } = useFormState({ control })

  const geoPermissions = GEO_META_KEYS.map((key) => fieldPermission(key))
  const geoVisible = geoPermissions.some((permission) => permission.visible)
  const geoDisabled = geoPermissions.some(
    (permission) => permission.disabled || !permission.editable,
  )

  const lockedLevels = useWatch({ control, name: 'geo_locked_levels' })
  const geoValue: GeoValue = {
    country_id: useWatch({ control, name: 'country_id' }) ?? null,
    state_id: useWatch({ control, name: 'state_id' }) ?? null,
    province_id: useWatch({ control, name: 'province_id' }) ?? null,
    city_id: useWatch({ control, name: 'city_id' }) ?? null,
  }

  const handleGeoChange = (next: GeoValue) => {
    setValue('country_id', next.country_id, { shouldValidate: true, shouldDirty: true })
    setValue('state_id', next.state_id, { shouldDirty: true, shouldValidate: true })
    setValue('province_id', next.province_id, { shouldDirty: true, shouldValidate: true })
    setValue('city_id', next.city_id, { shouldDirty: true, shouldValidate: true })
  }

  if (!geoVisible) {
    return null
  }

  // BR-4 hierarchy (client mirror, `withGeoHierarchyRule`): surfaced as one
  // alert below the cascade, since the shared `<GeoSelect>` (owned by a
  // different lane) does not expose per-level input ids to wire
  // `aria-describedby` onto individually — mirrors the existing `serverError`
  // convention already used at the bottom of this same form.
  const hierarchyError =
    errors.country_id?.message ?? errors.province_id?.message ?? errors.city_id?.message

  return (
    <FormSection
      icon={Globe}
      title={t('campaigns.form.sections.geography.title')}
      description={t('campaigns.form.sections.geography.description')}
    >
      {lockedLevels.length > 0 && (
        <p className="text-xs text-muted-foreground">{t('campaigns.form.geoInheritedFromProject')}</p>
      )}
      <GeoSelect
        value={geoValue}
        onChange={handleGeoChange}
        disabled={geoDisabled}
        lockedLevels={lockedLevels}
      />
      {hierarchyError && (
        <p className="text-sm font-medium text-destructive" role="alert">
          {hierarchyError}
        </p>
      )}
    </FormSection>
  )
}
