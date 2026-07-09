import type {
  CreateCustomFieldDefinitionPayload,
  CustomFieldConfig,
  CustomFieldDefinitionDetail,
  CustomFieldOptionInput,
  CustomFieldRelationTarget,
  CustomFieldValidation,
  UpdateCustomFieldDefinitionPayload,
} from '@/features/custom-fields/types'
import type { CustomFieldDefinitionFormValues } from '@/features/custom-fields/custom-field-definition-schema'

/**
 * Builds the `custom-fields` admin write payloads (spec 0021 AC-025). The
 * form keeps ALL per-type config/validation fields in one flat bag (simpler
 * RHF wiring); this module is the single seam that projects the relevant
 * subset per `type` onto the wire shape the backend expects (per-type
 * `config`: text{minLength,maxLength,regex,transform};
 * textarea{rows,maxLength}; integer/decimal{min,max,step,decimals};
 * boolean/enum{display}), mirroring the backend's `FieldTypeHandler::toMeta()`
 * counterpart.
 */

/** Drops `undefined`/`null`/`''` entries; returns `undefined` when nothing is left (never send an empty config/validation object). */
function omitEmpty<T extends Record<string, unknown>>(input: T): Partial<T> | undefined {
  const entries = Object.entries(input).filter(
    ([, value]) => value !== undefined && value !== null && value !== '',
  )
  return entries.length > 0 ? (Object.fromEntries(entries) as Partial<T>) : undefined
}

function buildConfig(values: CustomFieldDefinitionFormValues): CustomFieldConfig | undefined {
  const config = values.config
  switch (values.type) {
    case 'text':
      return omitEmpty({
        minLength: config.minLength ?? undefined,
        maxLength: config.maxLength ?? undefined,
        regex: config.regex,
        transform: config.transform || undefined,
      })
    case 'textarea':
      return omitEmpty({ rows: config.rows ?? undefined, maxLength: config.maxLength ?? undefined })
    case 'integer':
    case 'decimal':
      return omitEmpty({
        min: config.min ?? undefined,
        max: config.max ?? undefined,
        step: config.step ?? undefined,
        decimals: config.decimals ?? undefined,
      })
    case 'boolean':
    case 'enum':
      // The form types `display` loosely as string; narrow to the config union.
      return omitEmpty({ display: config.display as CustomFieldConfig['display'] })
    case 'relation':
      return undefined
    default:
      return undefined
  }
}

function buildValidation(values: CustomFieldDefinitionFormValues): CustomFieldValidation | undefined {
  const validation = values.validation
  return omitEmpty({
    required: validation.required || undefined,
    unique: validation.unique || undefined,
    min: validation.min ?? undefined,
    max: validation.max ?? undefined,
    regex: validation.regex,
    email: validation.email || undefined,
    url: validation.url || undefined,
    exists: validation.exists || undefined,
    distinct: validation.distinct || undefined,
  })
}

function buildRelationTarget(
  values: CustomFieldDefinitionFormValues,
): CustomFieldRelationTarget | undefined {
  if (values.type !== 'relation') {
    return undefined
  }
  return {
    entity_type: values.relation_target.entity_type,
    cardinality: values.relation_target.cardinality,
    for_select_resource: values.relation_target.for_select_resource,
  }
}

/** Maps the form's option rows to the wire shape, assigning `sort_order` from array position. */
function buildOptions(values: CustomFieldDefinitionFormValues): CustomFieldOptionInput[] | undefined {
  if (values.type !== 'enum') {
    return undefined
  }
  return values.options.map((option, index) => ({
    value: option.value,
    label: option.label,
    color: option.color || undefined,
    icon: option.icon || undefined,
    sort_order: index,
    is_default: option.is_default,
  }))
}

/** Builds the create payload: identity fields + the per-type config/validation/relation_target/options projection. */
export function buildCreatePayload(
  values: CustomFieldDefinitionFormValues,
): CreateCustomFieldDefinitionPayload {
  return {
    entity_type: values.entity_type,
    key: values.key,
    type: values.type,
    label: values.label,
    description: values.description || undefined,
    help_text: values.help_text || undefined,
    placeholder: values.placeholder || undefined,
    icon: values.icon || undefined,
    group: values.group || undefined,
    tab: values.tab || undefined,
    sort_order: values.sort_order,
    config: buildConfig(values),
    validation: buildValidation(values),
    relation_target: buildRelationTarget(values),
    is_indexed: values.is_indexed,
    is_active: values.is_active,
    options: buildOptions(values),
  }
}

/** `undefined`/`null`/`''` all mean "not set" for a plain optional text field diff. */
function textChanged(next: string, original: string | null | undefined): string | undefined {
  return next !== (original ?? '') ? next : undefined
}

/**
 * Builds a partial PATCH payload carrying only fields that changed from the
 * original definition (spec AC-019: `entity_type`/`type`/`key` are immutable
 * once the field has values — the server enforces that; sending them
 * unchanged, as the read-only edit fields do, is always safe).
 */
export function buildUpdatePayload(
  values: CustomFieldDefinitionFormValues,
  original: CustomFieldDefinitionDetail,
): UpdateCustomFieldDefinitionPayload {
  const payload: UpdateCustomFieldDefinitionPayload = {}

  if (values.entity_type !== original.entity_type) payload.entity_type = values.entity_type
  if (values.key !== original.key) payload.key = values.key
  if (values.type !== original.type) payload.type = values.type
  if (values.label !== original.label) payload.label = values.label

  const description = textChanged(values.description, original.description)
  if (description !== undefined) payload.description = description
  const helpText = textChanged(values.help_text, original.help_text)
  if (helpText !== undefined) payload.help_text = helpText
  const placeholder = textChanged(values.placeholder, original.placeholder)
  if (placeholder !== undefined) payload.placeholder = placeholder
  const icon = textChanged(values.icon, original.icon)
  if (icon !== undefined) payload.icon = icon
  const group = textChanged(values.group, original.group)
  if (group !== undefined) payload.group = group
  const tab = textChanged(values.tab, original.tab)
  if (tab !== undefined) payload.tab = tab

  if (values.sort_order !== original.sort_order) payload.sort_order = values.sort_order
  if (values.is_indexed !== original.is_indexed) payload.is_indexed = values.is_indexed
  if (values.is_active !== original.is_active) payload.is_active = values.is_active

  const nextConfig = buildConfig(values)
  if (JSON.stringify(nextConfig ?? null) !== JSON.stringify(original.config ?? null)) {
    payload.config = nextConfig ?? null
  }

  const nextValidation = buildValidation(values)
  if (JSON.stringify(nextValidation ?? null) !== JSON.stringify(original.validation ?? null)) {
    payload.validation = nextValidation ?? null
  }

  const nextRelationTarget = buildRelationTarget(values)
  if (JSON.stringify(nextRelationTarget ?? null) !== JSON.stringify(original.relation_target ?? null)) {
    payload.relation_target = nextRelationTarget ?? null
  }

  if (values.type === 'enum') {
    const nextOptions = buildOptions(values) ?? []
    const normalize = (list: { value: string; label: string; color?: string | null; icon?: string | null; is_default?: boolean }[]) =>
      list.map((option) => ({
        value: option.value,
        label: option.label,
        color: option.color ?? undefined,
        icon: option.icon ?? undefined,
        is_default: option.is_default,
      }))
    const normalizedNext = normalize(nextOptions)
    const normalizedOriginal = normalize(
      [...original.options].sort((a, b) => a.sort_order - b.sort_order),
    )
    if (JSON.stringify(normalizedNext) !== JSON.stringify(normalizedOriginal)) {
      payload.options = nextOptions
    }
  }

  return payload
}
