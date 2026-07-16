import { useTranslation } from 'react-i18next'
import { useFormState, useWatch, type Control, type UseFormSetValue } from 'react-hook-form'
import { Globe } from 'lucide-react'
import { FormSection } from '@/components/form-section'
import { FieldHint } from '@/components/field-hint'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { GeoSelect, type GeoValue } from '@/features/geo/geo-select'
import type { ProjectFormValues } from '@/features/projects/use-project-form'

/** Staggered mount reveal (motion-safe), second of the four cascading sections. */
const SECTION_REVEAL_GEOGRAPHY =
  'motion-safe:animate-in motion-safe:fade-in-0 motion-safe:slide-in-from-bottom-1 motion-safe:fill-mode-both motion-safe:duration-300 motion-safe:delay-150'

/**
 * Field-permission keys backing the geo cascade (spec 0004/0027 BR-6):
 * `<GeoSelect>` renders the 4 levels as a single reusable visual unit, so —
 * unlike the single-field `ProjectRelationField`s — visibility/edit state is
 * aggregated here rather than gated per level (mirrors
 * `operational-site-form-body`'s `GEO_META_KEYS`, and the campaign side's
 * `CampaignGeoSection`).
 */
const GEO_META_KEYS = ['country_id', 'state_id', 'province_id', 'city_id'] as const

/** Geo cascade levels, positionally aligned with `GEO_META_KEYS`, for the required-marker mapping. */
const GEO_LEVELS = ['country', 'state', 'province', 'city'] as const

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
  // The geo cascade is not a MetaField, so its required markers are surfaced
  // via GeoSelect from the same field permissions (country_id is required).
  const geoRequiredLevels = GEO_LEVELS.filter((_, index) => geoPermissions[index].required)

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
      aside={
        <FieldHint
          text={t('projects.form.hints.geography')}
          label={t('projects.form.sections.geography.title')}
        />
      }
      className={SECTION_REVEAL_GEOGRAPHY}
    >
      <GeoSelect
        value={geoValue}
        onChange={handleGeoChange}
        disabled={geoDisabled}
        requiredLevels={geoRequiredLevels}
      />
      {hierarchyError && (
        <p className="text-sm font-medium text-destructive" role="alert">
          {hierarchyError}
        </p>
      )}
    </FormSection>
  )
}
