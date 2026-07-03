import { useTranslation } from 'react-i18next'
import { AvatarUpload } from '@/components/avatar-upload'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Skeleton } from '@/components/ui/skeleton'
import { Form, FormControl, FormDescription } from '@/components/ui/form'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { AsyncPaginatedMultiSelect } from '@/components/ui/async-paginated-multi-select'
import { MetaField } from '@/features/authorization/MetaField'
import { ROLES_FOR_SELECT_RESOURCE } from '@/features/roles/for-select-api'
import { PersonalDataSection } from '@/features/personal-data/personal-data-section'
import { useUserForm } from '@/features/users/use-user-form'
import type { UserFormMode } from '@/features/users/user-form'
import type { UserDetail } from '@/features/users/types'

interface UserFormBodyProps {
  mode: UserFormMode
  onSuccess: (user: UserDetail) => void
  onCancel: () => void
  onAvatarChange?: () => void
}

/**
 * The user create/edit form UI. Every field is wrapped in `MetaField` (spec
 * 0004): hidden fields are absent, non-editable fields render disabled/
 * read-only, `required` comes from the resolved `ResourcePermissions` — no
 * hardcoded permission logic lives here. All non-render logic lives in
 * `useUserForm`.
 */
export function UserFormBody({
  mode,
  onSuccess,
  onCancel,
  onAvatarChange,
}: UserFormBodyProps) {
  const { t } = useTranslation()
  const {
    form,
    isEdit,
    localeOptions,
    serverError,
    profileDraft,
    setProfileDraft,
    profileQuery,
    profileName,
    selectedRoleItems,
    onSubmit,
    setPendingAvatar,
    handleAvatarUpload,
    handleAvatarRemove,
    canUploadAvatar,
    canRemoveAvatar,
  } = useUserForm({ mode, onSuccess, onAvatarChange })

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
              canUpload={canUploadAvatar}
              canRemove={canRemoveAvatar}
            />
          ) : (
            <AvatarUpload
              mode="deferred"
              label={t('users.form.avatarLabel')}
              name={profileName}
              onFileSelected={setPendingAvatar}
            />
          )}

          <MetaField
            control={form.control}
            name="email"
            metaKey="email"
            label={t('users.form.email')}
          >
            {({ field, disabled, readOnly }) => (
              <FormControl>
                <Input
                  type="email"
                  autoComplete="email"
                  disabled={disabled}
                  readOnly={readOnly}
                  {...field}
                />
              </FormControl>
            )}
          </MetaField>

          <MetaField
            control={form.control}
            name="locale"
            metaKey="locale"
            label={t('users.form.locale')}
          >
            {({ field, disabled }) => (
              <Select value={field.value} onValueChange={field.onChange} disabled={disabled}>
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
            )}
          </MetaField>

          <MetaField
            control={form.control}
            name="roles"
            metaKey="roles"
            label={t('users.form.roles')}
          >
            {({ field, disabled }) => (
              <FormControl>
                <AsyncPaginatedMultiSelect
                  resource={ROLES_FOR_SELECT_RESOURCE}
                  value={field.value}
                  onChange={field.onChange}
                  selectedItems={selectedRoleItems}
                  disabled={disabled}
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
            )}
          </MetaField>

          <MetaField
            control={form.control}
            name="password"
            metaKey="password"
            label={t(isEdit ? 'users.form.newPassword' : 'users.form.password')}
            description={
              isEdit ? <FormDescription>{t('users.form.passwordEditHint')}</FormDescription> : undefined
            }
          >
            {({ field, disabled, readOnly }) => (
              <FormControl>
                <Input
                  type="password"
                  autoComplete="new-password"
                  disabled={disabled}
                  readOnly={readOnly}
                  {...field}
                />
              </FormControl>
            )}
          </MetaField>

          <MetaField
            control={form.control}
            name="password_confirmation"
            metaKey="password"
            label={t('users.form.confirmPassword')}
          >
            {({ field, disabled, readOnly }) => (
              <FormControl>
                <Input
                  type="password"
                  autoComplete="new-password"
                  disabled={disabled}
                  readOnly={readOnly}
                  {...field}
                />
              </FormControl>
            )}
          </MetaField>

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
