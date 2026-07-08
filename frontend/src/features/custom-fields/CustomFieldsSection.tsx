import { useMemo } from 'react'
import { SlidersHorizontal } from 'lucide-react'
import type { Control, FieldPath } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { useFormField } from '@/components/ui/form'
import { MetaField } from '@/features/authorization/MetaField'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import { CUSTOM_FIELD_COMPONENT_REGISTRY } from '@/features/custom-fields/field-component-registry'
import {
  isCustomFieldDescriptor,
  rawKey,
  type CustomFieldDescriptor,
  type CustomFieldsFormShape,
  type CustomFieldValue,
} from '@/features/custom-fields/types'

interface CustomFieldsSectionProps<TFieldValues extends CustomFieldsFormShape> {
  /** Domain key of the host resource, e.g. `companies` (the same one whose form calls `/meta/{resource}`). */
  resource: string
  control: Control<TFieldValues>
  /**
   * Pre-loaded descriptors, bypassing the internal `useResourceMeta` fetch.
   * The host form already loads `/meta/{resource}` for its own native fields
   * via the SAME query key, so in production this stays unset and the two
   * calls dedupe through the TanStack Query cache; the prop exists for
   * tests/previews that would rather not stand up a query mock.
   */
  fields?: CustomFieldDescriptor[]
}

/** Sort key: tab, then group, then the admin-defined `sort_order` — `tab` only affects ordering here (no tabs UI in this generic engine). */
function sortKey(descriptor: CustomFieldDescriptor): [string, string, number] {
  return [descriptor.tab ?? '', descriptor.group ?? '', descriptor.sort_order ?? 0]
}

/** Groups already-sorted descriptors by their `group` label; `null` = ungrouped, rendered flat (no heading). */
function groupByLabel(
  fields: CustomFieldDescriptor[],
): Map<string | null, CustomFieldDescriptor[]> {
  const groups = new Map<string | null, CustomFieldDescriptor[]>()
  for (const descriptor of fields) {
    const key = descriptor.group ?? null
    const bucket = groups.get(key)
    if (bucket) {
      bucket.push(descriptor)
    } else {
      groups.set(key, [descriptor])
    }
  }
  return groups
}

/**
 * Renders the resource's `source:'custom'` fields (spec 0021 AC-022):
 * ordered by (tab, group, sort_order), each wrapped in `<MetaField>` so the
 * role's visible/editable/required/disabled flags apply exactly like a
 * native field. Renders nothing when the resource has no custom fields
 * (zero-code rollout: mounting this on a resource without any definition is
 * a no-op).
 */
export function CustomFieldsSection<TFieldValues extends CustomFieldsFormShape>({
  resource,
  control,
  fields: providedFields,
}: CustomFieldsSectionProps<TFieldValues>) {
  const { field: fieldPermission } = useResourcePermissions()
  const metaQuery = useResourceMeta(resource, providedFields === undefined)

  const customFields = useMemo(() => {
    const source = providedFields ?? metaQuery.data?.fields.filter(isCustomFieldDescriptor) ?? []
    return [...source]
      .filter((descriptor) => fieldPermission(descriptor.key).visible)
      .sort((a, b) => {
        const [aTab, aGroup, aOrder] = sortKey(a)
        const [bTab, bGroup, bOrder] = sortKey(b)
        return aTab.localeCompare(bTab) || aGroup.localeCompare(bGroup) || aOrder - bOrder
      })
  }, [fieldPermission, metaQuery.data, providedFields])

  if (customFields.length === 0) {
    return null
  }

  return (
    <>
      {[...groupByLabel(customFields).entries()].map(([group, descriptors]) =>
        group === null ? (
          <div key="ungrouped" className="flex flex-col gap-4">
            {descriptors.map((descriptor) => (
              <CustomFieldItem key={descriptor.key} control={control} descriptor={descriptor} />
            ))}
          </div>
        ) : (
          <FormSection key={group} icon={SlidersHorizontal} title={group}>
            {descriptors.map((descriptor) => (
              <CustomFieldItem key={descriptor.key} control={control} descriptor={descriptor} />
            ))}
          </FormSection>
        ),
      )}
    </>
  )
}

interface CustomFieldItemProps<TFieldValues extends CustomFieldsFormShape> {
  control: Control<TFieldValues>
  descriptor: CustomFieldDescriptor
}

/** One custom field, gated by `<MetaField>` and rendered via the type→component registry. */
function CustomFieldItem<TFieldValues extends CustomFieldsFormShape>({
  control,
  descriptor,
}: CustomFieldItemProps<TFieldValues>) {
  const name = `custom_fields.${rawKey(descriptor.key)}` as unknown as FieldPath<TFieldValues>

  return (
    <MetaField
      control={control}
      name={name}
      metaKey={descriptor.key}
      label={descriptor.label}
      description={descriptor.help_text}
    >
      {({ field, disabled, readOnly }) => (
        <CustomFieldControlBridge
          descriptor={descriptor}
          value={field.value as CustomFieldValue}
          onChange={field.onChange as (value: CustomFieldValue) => void}
          disabled={disabled}
          readOnly={readOnly}
        />
      )}
    </MetaField>
  )
}

interface CustomFieldControlBridgeProps {
  descriptor: CustomFieldDescriptor
  value: CustomFieldValue
  onChange: (value: CustomFieldValue) => void
  disabled: boolean
  readOnly: boolean
}

/**
 * Resolves the accessible-error triad (frontend.md §10) via `useFormField()`
 * — called here, one level INSIDE `<MetaField>`'s render prop, because
 * `<FormControl>`'s automatic `Slot` id injection only reaches its immediate
 * JSX child, one level too shallow for a registry-dispatched control (see
 * `CustomFieldControlProps`).
 */
function CustomFieldControlBridge({
  descriptor,
  value,
  onChange,
  disabled,
  readOnly,
}: CustomFieldControlBridgeProps) {
  const { formItemId, formDescriptionId, formMessageId, error } = useFormField()
  const FieldControl = CUSTOM_FIELD_COMPONENT_REGISTRY[descriptor.type]

  return (
    <FieldControl
      descriptor={descriptor}
      value={value}
      onChange={onChange}
      disabled={disabled}
      readOnly={readOnly}
      id={formItemId}
      describedBy={error ? `${formDescriptionId} ${formMessageId}` : formDescriptionId}
      invalid={Boolean(error)}
    />
  )
}
