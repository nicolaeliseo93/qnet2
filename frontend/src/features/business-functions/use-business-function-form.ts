import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { createBusinessFunction, updateBusinessFunction } from '@/features/business-functions/api'
import {
  buildCreatePayload,
  buildUpdatePayload,
} from '@/features/business-functions/business-function-form-payload'
import {
  buildCreateBusinessFunctionSchema,
  buildUpdateBusinessFunctionSchema,
  type CreateBusinessFunctionFormValues,
  type UpdateBusinessFunctionFormValues,
} from '@/features/business-functions/business-function-schema'
import type {
  BusinessFunctionDetail,
  BusinessFunctionFormMode,
} from '@/features/business-functions/types'

/** Server-side field names mapped onto the form for 422 handling. */
const SERVER_ERROR_FIELDS = ['name', 'type', 'manager_id', 'users'] as const

export type BusinessFunctionFormValues = CreateBusinessFunctionFormValues &
  UpdateBusinessFunctionFormValues

interface UseBusinessFunctionFormArgs {
  mode: BusinessFunctionFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (businessFunction: BusinessFunctionDetail) => void
}

/**
 * Owns every non-render concern of `BusinessFunctionForm`: RHF/Zod wiring,
 * default values, server 422 mapping and the create/update submit. The
 * component stays UI-only; this hook is the orchestration point (`onSubmit`).
 */
export function useBusinessFunctionForm({ mode, onSuccess }: UseBusinessFunctionFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)

  const isEdit = mode.type === 'edit'

  const schema = useMemo(
    () => (isEdit ? buildUpdateBusinessFunctionSchema(t) : buildCreateBusinessFunctionSchema(t)),
    [isEdit, t],
  )

  const defaultValues = useMemo<BusinessFunctionFormValues>(() => {
    if (mode.type === 'edit') {
      return {
        name: mode.businessFunction.name,
        type: mode.businessFunction.type,
        manager_id: mode.businessFunction.manager_id,
        users: mode.businessFunction.user_ids,
      }
    }
    return { name: '', type: null, manager_id: null, users: [] }
  }, [mode])

  // EDIT: pre-known {id, label} for the responsabile/associated-users pickers
  // so they show their current selection immediately (no hydration
  // round-trip) — the names come from the resource itself.
  const selectedManagerItem = useMemo(
    () =>
      mode.type === 'edit' && mode.businessFunction.manager
        ? { id: mode.businessFunction.manager.id, label: mode.businessFunction.manager.name }
        : null,
    [mode],
  )
  const selectedUserItems = useMemo(
    () =>
      mode.type === 'edit'
        ? mode.businessFunction.users.map((user) => ({ id: user.id, label: user.name }))
        : [],
    [mode],
  )

  const form = useForm<BusinessFunctionFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
  })

  const onSubmit = async (values: BusinessFunctionFormValues) => {
    setServerError(null)
    try {
      if (mode.type === 'edit') {
        const saved = await updateBusinessFunction(
          mode.businessFunction.id,
          buildUpdatePayload(values, mode.businessFunction),
        )
        queryClient.setQueryData(['business-functions', 'detail', mode.businessFunction.id], saved)
        toast.success(t('businessFunctions.form.updated'))
        onSuccess(saved)
        return
      }

      const created = await createBusinessFunction(buildCreatePayload(values))
      toast.success(t('businessFunctions.form.created'))
      onSuccess(created)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, [...SERVER_ERROR_FIELDS])) {
        setServerError(t('businessFunctions.form.genericError'))
      }
    }
  }

  return {
    form,
    isEdit,
    serverError,
    selectedManagerItem,
    selectedUserItems,
    onSubmit,
  }
}
