import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { useResourcePermissions } from '@/features/authorization/permissions'
import { useEnumOptions } from '@/features/config/use-config'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { cardToDraft, emptyPersonalDataDraft } from '@/features/personal-data/drafts'
import { buildPersonalDataSchema } from '@/features/personal-data/personal-data-schema'
import { usePersonalDataByOwner } from '@/features/personal-data/use-personal-data'
import type {
  PersonalDataDraft,
  PersonalDataFieldPermission,
} from '@/features/personal-data/types'
import {
  createUser,
  deleteUserAvatar,
  updateUser,
  uploadUserAvatar,
} from '@/features/users/api'
import { buildCreatePayload, buildUpdatePayload } from '@/features/users/user-form-payload'
import {
  buildCreateUserSchema,
  buildUpdateUserSchema,
  type CreateUserFormValues,
  type UpdateUserFormValues,
} from '@/features/users/user-schema'
import type { UserDetail, UserLocale } from '@/features/users/types'
import type { UserFormMode } from '@/features/users/user-form'

/**
 * Distinct "not yet seeded" marker for the profile buffer: `undefined` is the
 * query's loading state and `null` is a valid "user has no card" result, so a
 * dedicated sentinel is needed to detect the first resolved value.
 */
const SEED_PENDING = Symbol('seed-pending')

/**
 * Server-side field names mapped onto the form for 422 handling. `avatar` only
 * applies to the create flow's deferred upload; it is mapped to a form-level
 * error since the AvatarUpload control is not an RHF field.
 */
const SERVER_ERROR_FIELDS = ['email', 'locale', 'roles', 'password'] as const

export type UserFormValues = CreateUserFormValues & UpdateUserFormValues

interface UseUserFormArgs {
  mode: UserFormMode
  onSuccess: (user: UserDetail) => void
  onAvatarChange?: () => void
}

/**
 * Owns every non-render concern of `UserForm`: RHF/Zod wiring, the buffered
 * personal-data card, server 422 mapping and the avatar mutations. The
 * component stays UI-only; this hook is the orchestration point (`onSubmit`).
 */
export function useUserForm({ mode, onSuccess, onAvatarChange }: UseUserFormArgs) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const { canAction, field: fieldPermission } = useResourcePermissions()

  // Adapts the resolved authorization metadata to the personal-data domain's
  // own gating shape (spec 0008 D3): the shared PersonalDataSection/CardForm/
  // ContactsManager/AddressesManager stay decoupled from `@/features/authorization`
  // and only see `visible/editable/required/disabled/readonly` for a dot-path key.
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
  // Selectable locales come from the backend bootstrap config (GET /api/config),
  // never hardcoded on the frontend. Loaded before the app renders (ADR 0009), so
  // it is populated by the time this form mounts.
  const localeOptions = useEnumOptions('locale')
  const [serverError, setServerError] = useState<string | null>(null)
  // CREATE mode only: avatar chosen before the user exists, uploaded after save.
  const [pendingAvatar, setPendingAvatar] = useState<File | null>(null)
  // The buffered personal-data tree, owned here for both create and edit. The
  // card is always active (no add/remove): it starts blank and is submitted
  // inside the single user payload (ADR 0012).
  const [profileDraft, setProfileDraft] = useState<PersonalDataDraft>(
    emptyPersonalDataDraft,
  )
  // Tracks the loaded card we already seeded the buffer from, so the seed runs
  // once per fetch and the user's subsequent edits are never clobbered.
  const [seededFrom, setSeededFrom] = useState<unknown>(SEED_PENDING)

  const isEdit = mode.type === 'edit'

  // EDIT: load the user's card once and seed the buffer from it. The fetch is
  // disabled in create mode (no owner yet).
  const profileQuery = usePersonalDataByOwner(
    { type: 'user', id: mode.type === 'edit' ? mode.user.id : 0 },
    isEdit,
  )

  // Seed the buffer the first time the card resolves. Adjusting state during
  // render (React's recommended pattern for syncing to changing data) avoids an
  // effect that would re-render twice; the `seededFrom` guard makes it run once.
  if (isEdit && profileQuery.data !== undefined && seededFrom !== profileQuery.data) {
    setSeededFrom(profileQuery.data)
    setProfileDraft(
      profileQuery.data ? cardToDraft(profileQuery.data) : emptyPersonalDataDraft(),
    )
  }

  const schema = useMemo(
    () => (isEdit ? buildUpdateUserSchema(t) : buildCreateUserSchema(t)),
    [isEdit, t],
  )

  // The locale preselected on create: the backend's default option, falling back
  // to the first one (config is loaded before this mounts, so it is non-empty).
  const defaultLocale = (localeOptions.find((option) => option.is_default)?.value ??
    localeOptions[0]?.value ??
    'en') as UserLocale

  const defaultValues = useMemo<UserFormValues>(() => {
    if (mode.type === 'edit') {
      return {
        email: mode.user.email,
        locale: mode.user.locale,
        roles: mode.user.roles.map((role) => role.id),
        password: '',
        password_confirmation: '',
      }
    }
    return {
      email: '',
      locale: defaultLocale,
      roles: [],
      password: '',
      password_confirmation: '',
    }
  }, [mode, defaultLocale])

  // EDIT: pre-known {id, name} for the selected roles, so the picker shows their
  // labels immediately (no hydration round-trip) — the names come from the user
  // resource even for roles outside the actor's assignable set.
  const selectedRoleItems = useMemo(
    () =>
      mode.type === 'edit'
        ? mode.user.roles.map((role) => ({ id: role.id, label: role.name }))
        : [],
    [mode],
  )

  const form = useForm<UserFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
  })

  // The user's display name is derived from the personal-data card (single source
  // of truth) — there is no `name` field. Used here only for the avatar initials.
  const profileName =
    profileDraft.type === 'company'
      ? (profileDraft.company_name ?? '')
      : [profileDraft.first_name, profileDraft.last_name]
          .filter(Boolean)
          .join(' ')

  const onSubmit = async (values: UserFormValues) => {
    setServerError(null)

    // The personal-data card is mandatory: block the save until the required
    // identity fields (name + surname, or company name) are valid. The card form
    // shows the field-level messages inline; this is the gate before the request.
    const profileValid = buildPersonalDataSchema(t).safeParse({
      type: profileDraft.type,
      title: profileDraft.title ?? undefined,
      first_name: profileDraft.first_name ?? undefined,
      last_name: profileDraft.last_name ?? undefined,
      company_name: profileDraft.company_name ?? undefined,
      tax_code: profileDraft.tax_code ?? undefined,
      vat_number: profileDraft.vat_number ?? undefined,
      birth_date: profileDraft.birth_date ?? undefined,
    }).success

    if (!profileValid) {
      setServerError(t('personalData.section.incomplete'))
      return
    }

    try {
      if (mode.type === 'edit') {
        const saved = await updateUser(
          mode.user.id,
          buildUpdatePayload(values, mode.user, profileDraft, personalDataFieldPermission),
        )
        toast.success(t('users.form.updated'))
        onSuccess(saved)
        return
      }

      const created = await createUser(
        buildCreatePayload(values, profileDraft, personalDataFieldPermission),
      )
      toast.success(t('users.form.created'))

      // The user exists now; upload the deferred avatar before handing off.
      // A failed avatar upload must not lose the created user — surface a toast
      // and proceed, returning the freshest resource we have.
      if (pendingAvatar) {
        try {
          const withAvatar = await uploadUserAvatar(created.id, pendingAvatar)
          onSuccess(withAvatar)
          return
        } catch {
          toast.error(t('avatar.avatarUploadError'))
        }
      }

      onSuccess(created)
    } catch (error) {
      if (
        !applyServerValidationErrors(error, form.setError, [...SERVER_ERROR_FIELDS])
      ) {
        setServerError(t('users.form.genericError'))
      }
    }
  }

  // EDIT mode: avatar actions hit the backend immediately and refresh the
  // cached detail so the form (and any open detail view) reflects the change.
  const handleAvatarUpload = async (file: File) => {
    if (mode.type !== 'edit') {
      return
    }
    const updated = await uploadUserAvatar(mode.user.id, file)
    queryClient.setQueryData(['users', 'detail', mode.user.id], updated)
    onAvatarChange?.()
  }

  const handleAvatarRemove = async () => {
    if (mode.type !== 'edit') {
      return
    }
    const updated = await deleteUserAvatar(mode.user.id)
    queryClient.setQueryData(['users', 'detail', mode.user.id], updated)
    onAvatarChange?.()
  }

  return {
    form,
    isEdit,
    localeOptions,
    serverError,
    pendingAvatar,
    setPendingAvatar,
    profileDraft,
    setProfileDraft,
    profileQuery,
    profileName,
    selectedRoleItems,
    onSubmit,
    handleAvatarUpload,
    handleAvatarRemove,
    // Per-field gating for the personal-data section (spec 0008), adapted from
    // this resource's resolved `ResourcePermissions` and injected by prop.
    personalDataFieldPermission,
    // Metadata-gated (spec 0004): upload/remove-avatar actions, meaningful in
    // edit mode only (create mode defers the upload until the user exists).
    canUploadAvatar: canAction('upload_avatar'),
    canRemoveAvatar: canAction('delete_avatar'),
  }
}
