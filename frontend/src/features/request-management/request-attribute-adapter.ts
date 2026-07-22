import type {
  CustomFieldConfig,
  CustomFieldDescriptor,
  CustomFieldOption,
  CustomFieldRelation,
  CustomFieldType,
} from '@/features/custom-fields/types'
import type { ApplicableAttribute, ApplicableAttributeOption } from '@/features/request-management/types'

/**
 * Bridges an Attribute (spec 0049, Product Category template) onto the
 * `CUSTOM_FIELD_COMPONENT_REGISTRY` type→component contract (frontend.md
 * §"vocabolario type CONDIVISO"): the 13 Attribute types are the SAME
 * vocabulary as `CustomFieldType`, so the existing controls render an
 * Attribute unmodified, given a descriptor shaped like their native one. This
 * is a pure read adapter — it never writes back to the custom-fields feature.
 */

function toOption(option: ApplicableAttributeOption): CustomFieldOption {
  return { value: option.value, label: option.label, color: option.color }
}

/**
 * The contract only guarantees `relation_target` is a loose JSON object (spec
 * 0049 data_contract). Resolves it defensively; an attribute missing (or
 * malformed) `for_select_resource`/`cardinality` renders no relation control
 * (`RelationFieldControl` returns null without one) rather than crashing.
 */
function toRelation(relationTarget: Record<string, unknown> | null): CustomFieldRelation | undefined {
  const forSelectResource = relationTarget?.for_select_resource
  if (typeof forSelectResource !== 'string' || forSelectResource === '') {
    return undefined
  }
  return {
    for_select_resource: forSelectResource,
    cardinality: relationTarget?.cardinality === 'many' ? 'many' : 'one',
  }
}

/** Maps one `ApplicableAttribute` to the `CustomFieldDescriptor` shape the registry's controls expect. */
export function toCustomFieldDescriptor(attribute: ApplicableAttribute): CustomFieldDescriptor {
  return {
    key: attribute.code,
    type: attribute.type as CustomFieldType,
    group: null,
    mandatory: false,
    label: attribute.name,
    source: 'custom',
    description: attribute.description,
    help_text: attribute.help_text,
    placeholder: attribute.placeholder,
    icon: attribute.icon,
    tab: null,
    sort_order: attribute.sort_order,
    config: (attribute.config as CustomFieldConfig | null) ?? null,
    options: attribute.options.map(toOption),
    relation: toRelation(attribute.relation_target),
  }
}
