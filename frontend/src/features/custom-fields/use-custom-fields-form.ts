import { useMemo } from 'react'
import { useTranslation } from 'react-i18next'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { useResourceMeta } from '@/features/authorization/use-resource-meta'
import type { ResourcePermissions } from '@/features/authorization/types'
import {
  buildCustomFieldsSchema,
  type CustomFieldsSchema,
} from '@/features/custom-fields/build-custom-fields-schema'
import { customFieldErrorPaths } from '@/features/custom-fields/custom-fields-errors'
import {
  isCustomFieldDescriptor,
  type CustomFieldDescriptor,
  type CustomFieldValue,
} from '@/features/custom-fields/types'

/**
 * The single reusable integration point for wiring a resource's admin-defined
 * custom fields (spec 0021) into ANY module's create/edit form. It reads the
 * resource meta + the active `ResourcePermissionsProvider` scope and returns
 * everything a bespoke form hook needs, so each module only:
 *   1. embeds `.schema` under its Zod object's `custom_fields` key
 *      (via `asCustomFieldsField`),
 *   2. seeds `custom_fields: cf.defaultValues` in its RHF defaultValues,
 *   3. spreads `cf.errorPaths` into its 422 server-error field list,
 *   4. mounts `<CustomFieldsSection resource={...} control={form.control} />`,
 *   5. adds `custom_fields` to its create/update payload via
 *      `buildCustomFieldsCreate` / `buildCustomFieldsUpdate`.
 * No per-module custom-field logic — future modules get custom fields with the
 * same five-line integration.
 */

const EMPTY_DESCRIPTORS: CustomFieldDescriptor[] = []
const EMPTY_VALUES: Record<string, CustomFieldValue> = {}

/** Only `.fields` is consulted by `buildCustomFieldsSchema`; the rest is a stub. */
const RESOURCE_STUB: ResourcePermissions['resource'] = {
  view: true,
  create: true,
  update: true,
  delete: true,
  export: true,
  import: true,
}

export type CustomFieldsFormMode =
  | { type: 'create' }
  | { type: 'edit'; customFields?: Record<string, CustomFieldValue> | null }

export interface CustomFieldsFormIntegration {
  descriptors: CustomFieldDescriptor[]
  schema: CustomFieldsSchema
  defaultValues: Record<string, CustomFieldValue>
  errorPaths: string[]
}

export function useCustomFieldsForm(
  resource: string,
  mode: CustomFieldsFormMode,
): CustomFieldsFormIntegration {
  const { t } = useTranslation()
  const metaQuery = useResourceMeta(resource)
  const { field: fieldPermission } = useResourcePermissions()

  const descriptors = useMemo(
    () => metaQuery.data?.fields.filter(isCustomFieldDescriptor) ?? EMPTY_DESCRIPTORS,
    [metaQuery.data],
  )

  const permissions = useMemo<ResourcePermissions>(
    () => ({
      resource: RESOURCE_STUB,
      actions: {},
      fields: Object.fromEntries(
        descriptors.map((descriptor) => [descriptor.key, fieldPermission(descriptor.key)]),
      ),
    }),
    [descriptors, fieldPermission],
  )

  const schema = useMemo(
    () => buildCustomFieldsSchema(descriptors, permissions, t),
    [descriptors, permissions, t],
  )

  const editValues = mode.type === 'edit' ? mode.customFields : undefined
  const defaultValues = useMemo<Record<string, CustomFieldValue>>(
    () => editValues ?? EMPTY_VALUES,
    [editValues],
  )

  const errorPaths = useMemo(() => customFieldErrorPaths(descriptors), [descriptors])

  return { descriptors, schema, defaultValues, errorPaths }
}
