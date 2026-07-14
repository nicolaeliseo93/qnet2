import { useTranslation } from 'react-i18next'
import { useFormState, useWatch, type Control, type UseFormSetValue } from 'react-hook-form'
import { Globe } from 'lucide-react'
import { FormSection } from '@/components/form-section'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { GeoSelect, type GeoValue } from '@/features/geo/geo-select'
import type { ProjectFormValues } from '@/features/projects/use-project-form'

/**
 * Field-permission keys backing the geo cascade (spec 0004/0027 BR-6):
 * `<GeoSelect>` renders the 4 levels as a single reusable visual unit, so —
 * unlike the single-field `ProjectRelationField`s — visibility/edit state is
 * aggregated here rather than gated per level (mirrors
 * `operational-site-form-body`'s `GEO_META_KEYS`, and the campaign side's
 * `CampaignGeoSection`).
 */
const GEO_META_KEYS = ['country_id', 'state_id', 'province_id', 'city_id'] as const

interface ProjectGeographySectionProps {
  control: Control<ProjectFormValues>
  setValue: UseFormSetValue<ProjectFormValues>
}

/**
 * The project's geo cascade (spec 0027 BR-4): a dedicated Geography
 * `FormSection` wrapping `<GeoSelect>`, bridged to RHF via `useWatch`/
 * `setValue` (mirrors `features/operational-sites/operational-site-form-body`
 * and `features/campaigns/campaign-geo-section`). Extracted so
 * `ProjectFormBody` stays within the engineering size limits (spec 0023
 * precedent: `ProjectRelationField`).
 */
export function ProjectGeographySection({ control, setValue }: ProjectGeographySectionProps) {
  const { t } = useTranslation()
  const { field: fieldPermission } = useResourcePermissions()
  const { errors } = useFormState({ control })

  const geoPermissions = GEO_META_KEYS.map((key) => fieldPermission(key))
  const geoVisible = geoPermissions.some((permission) => permission.visible)
  const geoDisabled = geoPermissions.some(
    (permission) => permission.disabled || !permission.editable,
  )

  const geoValue: GeoValue = {
    country_id: useWatch({ control, name: 'country_id' }) ?? null,
    state_id: useWatch({ control, name: 'state_id' }) ?? null,
    province_id: useWatch({ control, name: 'province_id' }) ?? null,
    city_id: useWatch({ control, name: 'city_id' }) ?? null,
  }

  const handleGeoChange = (next: GeoValue) => {
    setValue('country_id', next.country_id, { shouldValidate: true, shouldDirty: true })
    setValue('state_id', next.state_id, { shouldValidate: true, shouldDirty: true })
    setValue('province_id', next.province_id, { shouldValidate: true, shouldDirty: true })
    setValue('city_id', next.city_id, { shouldValidate: true, shouldDirty: true })
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
      title={t('projects.form.sections.geography.title')}
      description={t('projects.form.sections.geography.description')}
    >
      <GeoSelect value={geoValue} onChange={handleGeoChange} disabled={geoDisabled} />
      {hierarchyError && (
        <p className="text-sm font-medium text-destructive" role="alert">
          {hierarchyError}
        </p>
      )}
    </FormSection>
  )
}
