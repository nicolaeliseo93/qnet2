import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { AvatarUpload } from '@/components/avatar-upload'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Skeleton } from '@/components/ui/skeleton'
import {
  Form,
  FormControl,
  FormDescription,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from '@/components/ui/form'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { AsyncPaginatedMultiSelect } from '@/components/ui/async-paginated-multi-select'
import { useEnumOptions } from '@/features/config/use-config'
import { ROLES_FOR_SELECT_RESOURCE } from '@/features/roles/for-select-api'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { PersonalDataSection } from '@/features/personal-data/personal-data-section'
import {
  cardToDraft,
  draftToPayload,
  emptyPersonalDataDraft,
} from '@/features/personal-data/drafts'
import { buildPersonalDataSchema } from '@/features/personal-data/personal-data-schema'
import { usePersonalDataByOwner } from '@/features/personal-data/use-personal-data'
import type { PersonalDataDraft } from '@/features/personal-data/types'
import {
  createUser,
  deleteUserAvatar,
  updateUser,
  uploadUserAvatar,
} from '@/features/users/api'
import {
  buildCreateUserSchema,
  buildUpdateUserSchema,
  type CreateUserFormValues,
  type UpdateUserFormValues,
} from '@/features/users/user-schema'
import type {
  CreateUserPayload,
  UpdateUserPayload,
  UserDetail,
  UserLocale,
} from '@/features/users/types'

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

export type UserFormMode =
  | { type: 'create' }
  | { type: 'edit'; user: UserDetail }

interface UserFormProps {
  mode: UserFormMode
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (user: UserDetail) => void
  /** Called when the user cancels the form. */
  onCancel: () => void
  /**
   * EDIT mode only: called after an immediate avatar upload/remove succeeds, so
   * the caller can refresh the users grid without closing the form.
   */
  onAvatarChange?: () => void
}

type UserFormValues = CreateUserFormValues & UpdateUserFormValues

/**
 * Reusable RHF + Zod form used for both creating and editing a user. In edit
 * mode it pre-populates from the passed user and sends a partial PATCH carrying
 * only changed fields. Handles loading, server 422 mapping and success toasts.
 */
export function UserForm({
  mode,
  onSuccess,
  onCancel,
  onAvatarChange,
}: UserFormProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
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
          buildUpdatePayload(values, mode.user, profileDraft),
        )
        toast.success(t('users.form.updated'))
        onSuccess(saved)
        return
      }

      const created = await createUser(buildCreatePayload(values, profileDraft))
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

  return (
    <div className="flex flex-1 flex-col overflow-y-auto">
      <Form {...form}>
        <form
          onSubmit={form.handleSubmit(onSubmit)}
          className="flex flex-col gap-4 p-4"
          noValidate
        >
        {mode.type === 'edit' ? (
          <AvatarUpload
            mode="immediate"
            label={t('users.form.avatarLabel')}
            name={profileName}
            avatarUrl={mode.user.avatar_url}
            onUpload={handleAvatarUpload}
            onRemove={handleAvatarRemove}
          />
        ) : (
          <AvatarUpload
            mode="deferred"
            label={t('users.form.avatarLabel')}
            name={profileName}
            onFileSelected={setPendingAvatar}
          />
        )}

        <FormField
          control={form.control}
          name="email"
          render={({ field }) => (
            <FormItem>
              <FormLabel required>{t('users.form.email')}</FormLabel>
              <FormControl>
                <Input type="email" autoComplete="email" {...field} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />

        <FormField
          control={form.control}
          name="locale"
          render={({ field }) => (
            <FormItem>
              <FormLabel required>{t('users.form.locale')}</FormLabel>
              <Select value={field.value} onValueChange={field.onChange}>
                <FormControl>
                  <SelectTrigger className="w-full">
                    <SelectValue />
                  </SelectTrigger>
                </FormControl>
                <SelectContent>
                  {localeOptions.map((option) => (
                    <SelectItem key={option.value} value={option.value}>
                      {option.label}
                    </SelectItem>
                  ))}
                </SelectContent>
              </Select>
              <FormMessage />
            </FormItem>
          )}
        />

        <FormField
          control={form.control}
          name="roles"
          render={({ field }) => (
            <FormItem>
              <FormLabel>{t('users.form.roles')}</FormLabel>
              <FormControl>
                <AsyncPaginatedMultiSelect
                  resource={ROLES_FOR_SELECT_RESOURCE}
                  value={field.value}
                  onChange={field.onChange}
                  selectedItems={selectedRoleItems}
                  labels={{
                    placeholder: t('users.form.rolesPlaceholder'),
                    searchPlaceholder: t('users.form.rolesSearch'),
                    empty: t('users.form.rolesEmpty'),
                    error: t('users.form.rolesError'),
                    retry: t('common.retry'),
                    removeLabel: t('users.form.rolesRemove'),
                    triggerLabel: t('users.form.roles'),
                  }}
                />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />

        <FormField
          control={form.control}
          name="password"
          render={({ field }) => (
            <FormItem>
              <FormLabel required={!isEdit}>
                {t(isEdit ? 'users.form.newPassword' : 'users.form.password')}
              </FormLabel>
              <FormControl>
                <Input type="password" autoComplete="new-password" {...field} />
              </FormControl>
              {isEdit && (
                <FormDescription>
                  {t('users.form.passwordEditHint')}
                </FormDescription>
              )}
              <FormMessage />
            </FormItem>
          )}
        />

        <FormField
          control={form.control}
          name="password_confirmation"
          render={({ field }) => (
            <FormItem>
              <FormLabel required={!isEdit}>
                {t('users.form.confirmPassword')}
              </FormLabel>
              <FormControl>
                <Input type="password" autoComplete="new-password" {...field} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />

        <div className="border-t pt-4">
          {isEdit && profileQuery.isPending ? (
            <div className="flex flex-col gap-3" aria-hidden="true">
              <Skeleton className="h-5 w-32" />
              <div className="grid grid-cols-2 gap-3">
                <Skeleton className="h-9" />
                <Skeleton className="h-9" />
              </div>
              <Skeleton className="h-9" />
            </div>
          ) : isEdit && profileQuery.isError ? (
            <div className="flex items-center justify-between gap-2">
              <p className="text-sm text-destructive" role="alert">
                {t('personalData.section.loadError')}
              </p>
              <Button
                type="button"
                variant="outline"
                size="sm"
                onClick={() => profileQuery.refetch()}
              >
                {t('common.retry')}
              </Button>
            </div>
          ) : (
            <PersonalDataSection value={profileDraft} onChange={setProfileDraft} />
          )}
        </div>

        {serverError && (
          <p className="text-sm font-medium text-destructive" role="alert">
            {serverError}
          </p>
        )}

        <div className="mt-auto flex justify-end gap-2 pt-2">
          <Button
            type="button"
            variant="outline"
            onClick={onCancel}
            disabled={form.formState.isSubmitting}
          >
            {t('users.form.cancel')}
          </Button>
          <Button type="submit" disabled={form.formState.isSubmitting}>
            {form.formState.isSubmitting
              ? t('users.form.saving')
              : t('users.form.save')}
          </Button>
        </div>
        </form>
      </Form>
    </div>
  )
}

/**
 * Builds the create payload, always including password + confirmation, plus the
 * nested `personal_data` tree when a card was entered (ADR 0012).
 */
function buildCreatePayload(
  values: UserFormValues,
  profileDraft: PersonalDataDraft,
): CreateUserPayload {
  return {
    email: values.email,
    locale: values.locale,
    roles: values.roles,
    password: values.password,
    password_confirmation: values.password_confirmation,
    personal_data: draftToPayload(profileDraft),
  }
}

/**
 * Builds a partial PATCH payload carrying only fields that changed from the
 * original user. Password is included only when a new one was typed. The nested
 * `personal_data` tree is always sent when a card is present (authoritative sync),
 * so children added/edited/removed in the buffer persist in the one request.
 */
function buildUpdatePayload(
  values: UserFormValues,
  original: UserDetail,
  profileDraft: PersonalDataDraft,
): UpdateUserPayload {
  const payload: UpdateUserPayload = {}

  if (values.email !== original.email) {
    payload.email = values.email
  }
  if (values.locale !== original.locale) {
    payload.locale = values.locale
  }
  if (!sameRoles(values.roles, original.roles.map((role) => role.id))) {
    payload.roles = values.roles
  }
  if (values.password !== '') {
    payload.password = values.password
    payload.password_confirmation = values.password_confirmation
  }
  payload.personal_data = draftToPayload(profileDraft)

  return payload
}

/** Order-insensitive comparison of two role-id lists. */
function sameRoles(a: number[], b: number[]): boolean {
  if (a.length !== b.length) {
    return false
  }
  const set = new Set(b)
  return a.every((id) => set.has(id))
}
