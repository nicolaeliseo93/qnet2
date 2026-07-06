import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import axios from 'axios'
import { toast } from 'sonner'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { cardToDraft, emptyPersonalDataDraft } from '@/features/personal-data/drafts'
import { buildPersonalDataSchema } from '@/features/personal-data/personal-data-schema'
import type {
  PersonalDataDraft,
  PersonalDataFieldPermission,
} from '@/features/personal-data/types'
import type { ForSelectItem } from '@/features/for-select/types'
import { createReferent, updateReferent } from '@/features/referents/api'
import { buildCreatePayload, buildUpdatePayload } from '@/features/referents/referent-form-payload'
import {
  buildCreateReferentSchema,
  buildUpdateReferentSchema,
  type CreateReferentFormValues,
  type UpdateReferentFormValues,
} from '@/features/referents/referent-schema'
import type { ReferentDetail, ReferentFormMode } from '@/features/referents/types'

/**
 * Server-side field names mapped onto the form for 422 handling. The nested
 * `personal_data.*` paths are NOT here — that buffer lives outside RHF (see
 * `personalDataServerErrorMessage` below) — mirroring why `users` does not map
 * them onto its own outer form either.
 */
const SERVER_ERROR_FIELDS = ['referent_type_id', 'contact_scope', 'notes'] as const

/** Form pre-selects 'internal' (spec 0016, user-approved decision). */
const DEFAULT_CONTACT_SCOPE = 'internal' as const

export type ReferentFormValues = CreateReferentFormValues & UpdateReferentFormValues

interface UseReferentFormArgs {
  mode: ReferentFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (referent: ReferentDetail) => void
}

/**
 * Collects every `personal_data.*` (or bare `personal_data`) message from a
 * 422 response into a single banner string. The buffered anagraphic draft is
 * NOT an RHF field (mirroring `users`), so its server errors cannot be routed
 * inline into `PersonalDataCardForm`/`ContactsManager`/`AddressesManager`
 * (owner-agnostic, reused unchanged) — they surface here instead, alongside
 * the per-field mapped scalar errors.
 */
function personalDataServerErrorMessage(error: unknown): string | null {
  if (!axios.isAxiosError(error) || error.response?.status !== 422) {
    return null
  }
  const errors = error.response.data?.errors as Record<string, string[]> | undefined
  if (!errors) {
    return null
  }
  const messages = Object.entries(errors)
    .filter(([key]) => key === 'personal_data' || key.startsWith('personal_data.'))
    .flatMap(([, fieldMessages]) => fieldMessages)
  return messages.length > 0 ? messages.join(' ') : null
}

/**
 * Owns every non-render concern of `ReferentForm`: RHF/Zod wiring, the
 * buffered personal-data card and server 422 mapping. The component stays
 * UI-only; this hook is the orchestration point (`onSubmit`). Unlike `users`,
 * the card is NOT fetched separately: the referent `show` endpoint already
 * embeds `personal_data`, so edit mode seeds the buffer straight from
 * `mode.referent.personal_data` (spec 0016).
 */
export function useReferentForm({ mode, onSuccess }: UseReferentFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const { field: fieldPermission } = useResourcePermissions()

  // Adapts the resolved authorization metadata to the personal-data domain's
  // own gating shape (spec 0008 D3): the shared PersonalDataCardForm/
  // ContactsManager/AddressesManager stay decoupled from `@/features/authorization`.
  const personalDataFieldPermission = (key: string): PersonalDataFieldPermission => {
    const permission = fieldPermission(key)
    return {
      visible: permission.visible,
      editable: permission.editable,
      required: permission.required,
      disabled: permission.disabled,
      readonly: permission.readonly,
    }
  }

  const [serverError, setServerError] = useState<string | null>(null)
  const [profileDraft, setProfileDraft] = useState<PersonalDataDraft>(() =>
    mode.type === 'edit' && mode.referent.personal_data
      ? cardToDraft(mode.referent.personal_data)
      : emptyPersonalDataDraft(),
  )

  const isEdit = mode.type === 'edit'

  const schema = useMemo(
    () => (isEdit ? buildUpdateReferentSchema(t) : buildCreateReferentSchema(t)),
    [isEdit, t],
  )

  const defaultValues = useMemo<ReferentFormValues>(() => {
    if (mode.type === 'edit') {
      return {
        referent_type_id: mode.referent.referent_type_id,
        contact_scope: mode.referent.contact_scope,
        notes: mode.referent.notes ?? '',
      }
    }
    return {
      referent_type_id: null,
      contact_scope: DEFAULT_CONTACT_SCOPE,
      notes: '',
    }
  }, [mode])

  // EDIT: pre-known {id, label} for the "Referent type" picker, so it shows
  // its current selection immediately (no hydration round-trip).
  const selectedReferentTypeItem = useMemo<ForSelectItem | null>(
    () =>
      mode.type === 'edit' && mode.referent.referent_type
        ? { id: mode.referent.referent_type.id, label: mode.referent.referent_type.name }
        : null,
    [mode],
  )

  const form = useForm<ReferentFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
  })

  // The anagraphic card is mandatory (create requires it; edit always shows
  // one): block the save until the required identity fields are valid. The
  // card form shows the field-level messages inline.
  const profileValid = useMemo(
    () =>
      buildPersonalDataSchema(t).safeParse({
        type: profileDraft.type,
        title: profileDraft.title ?? undefined,
        first_name: profileDraft.first_name ?? undefined,
        last_name: profileDraft.last_name ?? undefined,
        company_name: profileDraft.company_name ?? undefined,
        tax_code: profileDraft.tax_code ?? undefined,
        vat_number: profileDraft.vat_number ?? undefined,
        birth_date: profileDraft.birth_date ?? undefined,
      }).success,
    [profileDraft, t],
  )

  const onSubmit = async (values: ReferentFormValues) => {
    setServerError(null)

    if (!profileValid) {
      setServerError(t('personalData.section.incomplete'))
      return
    }

    try {
      if (mode.type === 'edit') {
        const saved = await updateReferent(
          mode.referent.id,
          buildUpdatePayload(values, mode.referent, profileDraft, personalDataFieldPermission),
        )
        queryClient.setQueryData(['referents', 'detail', mode.referent.id], saved)
        toast.success(t('referents.form.updated'))
        onSuccess(saved)
        return
      }

      const created = await createReferent(
        buildCreatePayload(values, profileDraft, personalDataFieldPermission),
      )
      toast.success(t('referents.form.created'))
      onSuccess(created)
    } catch (error) {
      const mappedScalar = applyServerValidationErrors(error, form.setError, [
        ...SERVER_ERROR_FIELDS,
      ])
      const personalDataMessage = personalDataServerErrorMessage(error)
      if (personalDataMessage) {
        setServerError(personalDataMessage)
      } else if (!mappedScalar) {
        setServerError(t('referents.form.genericError'))
      }
    }
  }

  return {
    form,
    isEdit,
    serverError,
    profileDraft,
    setProfileDraft,
    profileValid,
    selectedReferentTypeItem,
    onSubmit,
    personalDataFieldPermission,
  }
}
