import { useTranslation } from 'react-i18next'
import type { Control } from 'react-hook-form'
import { FormControl } from '@/components/ui/form'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { MetaField } from '@/features/authorization/MetaField'
import type { ForSelectItem } from '@/features/for-select/types'
import type { ProjectFormValues } from '@/features/projects/use-project-form'
import type { ProjectRelationRef } from '@/features/projects/types'

/**
 * The project's 5 optional single-relation fields, all sharing the exact same
 * picker shape. `state_id` is NOT one of them: it is one of the 4 geo cascade
 * levels, rendered by `<GeoSelect>` in the Geography section (spec 0027).
 */
type ProjectRelationFieldName =
  | 'registry_id'
  | 'source_id'
  | 'business_function_id'
  | 'product_category_id'
  | 'partner_id'

interface ProjectRelationFieldProps {
  control: Control<ProjectFormValues>
  name: ProjectRelationFieldName
  metaKey: string
  label: string
  resource: string
  searchPlaceholder: string
  /** The loaded detail's hydrated `{id, name}` projection for this relation (edit mode only). */
  selected: ProjectRelationRef | null
}

/** Renders a `{id, name}` relation ref as the `ForSelectItem` shape `AsyncPaginatedSelect` hydrates from. */
function toForSelectItem(ref: ProjectRelationRef | null): ForSelectItem | null {
  return ref ? { id: ref.id, label: ref.name } : null
}

/**
 * One of the project's 5 optional single-relation pickers (registry, source,
 * business function, product category, partner): identical shape — an
 * `AsyncPaginatedSelect` inside `MetaField`, hydrated from the loaded
 * detail's `{id, name}` projection in edit mode. Extracted so
 * `ProjectFormBody` stays within the engineering size limits (spec 0023).
 */
export function ProjectRelationField({
  control,
  name,
  metaKey,
  label,
  resource,
  searchPlaceholder,
  selected,
}: ProjectRelationFieldProps) {
  const { t } = useTranslation()

  return (
    <MetaField control={control} name={name} metaKey={metaKey} label={label}>
      {({ field, disabled }) => (
        <FormControl>
          <AsyncPaginatedSelect
            resource={resource}
            value={field.value}
            onChange={field.onChange}
            selectedItem={toForSelectItem(selected)}
            disabled={disabled}
            labels={{
              placeholder: t('projects.form.selectPlaceholder'),
              searchPlaceholder,
              empty: t('projects.form.selectEmpty'),
              error: t('projects.form.selectError'),
              clearLabel: t('common.clear'),
              triggerLabel: label,
              retry: t('common.retry'),
            }}
          />
        </FormControl>
      )}
    </MetaField>
  )
}
