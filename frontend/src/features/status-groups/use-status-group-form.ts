import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import type { Path, Resolver } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { createStatusGroup, updateStatusGroup } from '@/features/status-groups/api'
import {
  buildCreatePayload,
  buildUpdatePayload,
} from '@/features/status-groups/status-group-form-payload'
import {
  buildCreateStatusGroupSchema,
  buildUpdateStatusGroupSchema,
  type CreateStatusGroupFormValues,
  type UpdateStatusGroupFormValues,
} from '@/features/status-groups/status-group-schema'
import type { StatusGroupDetail, StatusGroupFormMode } from '@/features/status-groups/types'
import { useCustomFieldsForm } from '@/features/custom-fields/use-custom-fields-form'

/** Server-side field names mapped onto the form for 422 handling. */
const SERVER_ERROR_FIELDS = ['name', 'color', 'sort_order'] as const

export type StatusGroupFormValues = CreateStatusGroupFormValues & UpdateStatusGroupFormValues

interface UseStatusGroupFormArgs {
  mode: StatusGroupFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (statusGroup: StatusGroupDetail) => void
}

/**
 * Owns every non-render concern of `StatusGroupForm`: RHF/Zod wiring, default
 * values, server 422 mapping and the create/update submit. The component
 * stays UI-only; this hook is the orchestration point (`onSubmit`).
 */
export function useStatusGroupForm({ mode, onSuccess }: UseStatusGroupFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)

  const isEdit = mode.type === 'edit'

  // Custom fields (spec 0021): the single reusable integration — builds the
  // dynamic schema, defaults and 422 paths; `<CustomFieldsSection>` renders.
  const customFields = useCustomFieldsForm(
    'status-groups',
    mode.type === 'edit'
      ? { type: 'edit', customFields: mode.statusGroup.custom_fields }
      : { type: 'create' },
  )

  const schema = useMemo(
    () =>
      isEdit
        ? buildUpdateStatusGroupSchema(t, customFields.schema)
        : buildCreateStatusGroupSchema(t, customFields.schema),
    [isEdit, t, customFields.schema],
  )

  const defaultValues = useMemo<StatusGroupFormValues>(() => {
    if (mode.type === 'edit') {
      return {
        name: mode.statusGroup.name,
        color: mode.statusGroup.color ?? '',
        sort_order: mode.statusGroup.sort_order,
        custom_fields: customFields.defaultValues,
      }
    }
    return { name: '', color: '', sort_order: 0, custom_fields: customFields.defaultValues }
  }, [mode, customFields.defaultValues])

  const form = useForm<StatusGroupFormValues>({
    // `schema` is a UNION of the create/edit Zod object types (picked at
    // runtime by `isEdit`); `zodResolver` cannot infer its generics from a
    // union schema value and falls back to bare `FieldValues`, which then
    // fails to unify with `StatusGroupFormValues` everywhere `form.control`
    // is passed downstream. `sort_order`'s `z.coerce.number()` additionally
    // widens the schema's OWN inferred input to `unknown` (pre-coercion), so
    // even an explicit `<StatusGroupFormValues, unknown, StatusGroupFormValues>`
    // fails the structural check — asserting the resolver's type at this one
    // boundary (not `any`) is the correct fix: at runtime it still validates
    // through the same schema and yields `StatusGroupFormValues`.
    resolver: zodResolver(schema) as Resolver<StatusGroupFormValues>,
    defaultValues,
  })

  const onSubmit = async (values: StatusGroupFormValues) => {
    setServerError(null)
    const errorFields: Path<StatusGroupFormValues>[] = [
      ...SERVER_ERROR_FIELDS,
      ...(customFields.errorPaths as Path<StatusGroupFormValues>[]),
    ]
    try {
      if (mode.type === 'edit') {
        const saved = await updateStatusGroup(
          mode.statusGroup.id,
          buildUpdatePayload(values, mode.statusGroup),
        )
        queryClient.setQueryData(['status-groups', 'detail', mode.statusGroup.id], saved)
        toast.success(t('statusGroups.form.updated'))
        onSuccess(saved)
        return
      }

      const created = await createStatusGroup(buildCreatePayload(values))
      toast.success(t('statusGroups.form.created'))
      onSuccess(created)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, errorFields)) {
        setServerError(t('statusGroups.form.genericError'))
      }
    }
  }

  return {
    form,
    isEdit,
    serverError,
    onSubmit,
  }
}
