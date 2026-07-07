import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { createEaSector, updateEaSector } from '@/features/ea-sectors/api'
import { buildCreatePayload, buildUpdatePayload } from '@/features/ea-sectors/ea-sector-form-payload'
import {
  buildCreateEaSectorSchema,
  buildUpdateEaSectorSchema,
  type CreateEaSectorFormValues,
} from '@/features/ea-sectors/ea-sector-schema'
import { eaSectorKeys } from '@/features/ea-sectors/query-keys'
import type { EaSectorDetail, EaSectorFormMode } from '@/features/ea-sectors/types'
import type { ForSelectItem } from '@/features/for-select/types'

/** Server-side field names mapped onto the form for 422 handling. */
const SERVER_ERROR_FIELDS = ['name', 'parent_id', 'tag_ids'] as const

export type EaSectorFormValues = CreateEaSectorFormValues

interface UseEaSectorFormArgs {
  mode: EaSectorFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (sector: EaSectorDetail) => void
}

/**
 * Owns every non-render concern of `EaSectorForm`: RHF/Zod wiring, default
 * values, server 422 mapping and the create/update submit. The component
 * stays UI-only; this hook is the orchestration point (`onSubmit`).
 */
export function useEaSectorForm({ mode, onSuccess }: UseEaSectorFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const [serverError, setServerError] = useState<string | null>(null)

  const isEdit = mode.type === 'edit'

  const schema = useMemo(
    () => (isEdit ? buildUpdateEaSectorSchema(t) : buildCreateEaSectorSchema(t)),
    [isEdit, t],
  )

  const defaultValues = useMemo<EaSectorFormValues>(() => {
    if (mode.type === 'edit') {
      return {
        name: mode.sector.name,
        parent_id: mode.sector.parent_id,
        tag_ids: mode.sector.tag_ids,
      }
    }
    return { name: '', parent_id: mode.parentId, tag_ids: [] }
  }, [mode])

  // EDIT: pre-known {id, label} for the tags multi-select, so it shows its
  // current selection immediately (no hydration round-trip) — the names
  // come from the resource itself.
  const selectedTagItems = useMemo<ForSelectItem[]>(
    () =>
      mode.type === 'edit' ? mode.sector.tags.map((tag) => ({ id: tag.id, label: tag.name })) : [],
    [mode],
  )

  const form = useForm<EaSectorFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
  })

  const onSubmit = async (values: EaSectorFormValues) => {
    setServerError(null)
    try {
      if (mode.type === 'edit') {
        const saved = await updateEaSector(mode.sector.id, buildUpdatePayload(values, mode.sector))
        queryClient.setQueryData(eaSectorKeys.detail(mode.sector.id), saved)
        void queryClient.invalidateQueries({ queryKey: eaSectorKeys.tree })
        toast.success(t('eaSectors.form.updated'))
        onSuccess(saved)
        return
      }

      const created = await createEaSector(buildCreatePayload(values))
      void queryClient.invalidateQueries({ queryKey: eaSectorKeys.tree })
      toast.success(t('eaSectors.form.created'))
      onSuccess(created)
    } catch (error) {
      if (!applyServerValidationErrors(error, form.setError, [...SERVER_ERROR_FIELDS])) {
        setServerError(t('eaSectors.form.genericError'))
      }
    }
  }

  return {
    form,
    isEdit,
    serverError,
    selectedTagItems,
    onSubmit,
  }
}
