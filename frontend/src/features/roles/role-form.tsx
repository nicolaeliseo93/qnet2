import { useMemo, useState } from 'react'
import { useForm } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { toast } from 'sonner'
import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import {
  Form,
  FormControl,
  FormField,
  FormItem,
  FormLabel,
  FormMessage,
} from '@/components/ui/form'
import { AsyncPaginatedMultiSelect } from '@/components/ui/async-paginated-multi-select'
import { USERS_FOR_SELECT_RESOURCE } from '@/features/users/for-select-api'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { createRole, updateRole } from '@/features/roles/api'
import {
  buildCreateRoleSchema,
  buildUpdateRoleSchema,
  type CreateRoleFormValues,
  type UpdateRoleFormValues,
} from '@/features/roles/role-schema'
import { groupPermissions } from '@/features/roles/permission-groups'
import {
  abilityLabel,
  resourceLabel,
} from '@/features/roles/permission-labels'
import type {
  CreateRolePayload,
  RoleDetail,
  UpdateRolePayload,
} from '@/features/roles/types'

/** Server-side field names mapped onto the form for 422 handling. */
const SERVER_ERROR_FIELDS = ['name', 'permissions', 'users'] as const

export type RoleFormMode =
  | { type: 'create' }
  | { type: 'edit'; role: RoleDetail }

interface RoleFormProps {
  mode: RoleFormMode
  /** Full permission catalogue, sourced from the table config. */
  permissionOptions: string[]
  /** Called after a successful create/update so the caller can close + refresh. */
  onSuccess: (role: RoleDetail) => void
  /** Called when the user cancels the form. */
  onCancel: () => void
}

type RoleFormValues = CreateRoleFormValues & UpdateRoleFormValues

/**
 * Reusable RHF + Zod form used for both creating and editing a role. The role's
 * permissions are edited inline as a grouped checkbox matrix (the core of this
 * module). In edit mode it pre-populates from the passed role and sends a
 * partial PATCH carrying only changed fields. Handles server 422 mapping and
 * success toasts.
 */
export function RoleForm({
  mode,
  permissionOptions,
  onSuccess,
  onCancel,
}: RoleFormProps) {
  const { t, i18n } = useTranslation()
  const [serverError, setServerError] = useState<string | null>(null)

  const isEdit = mode.type === 'edit'

  const schema = useMemo(
    () => (isEdit ? buildUpdateRoleSchema(t) : buildCreateRoleSchema(t)),
    [isEdit, t],
  )

  const groups = useMemo(
    () => groupPermissions(permissionOptions),
    [permissionOptions],
  )

  const defaultValues = useMemo<RoleFormValues>(() => {
    if (mode.type === 'edit') {
      return {
        name: mode.role.name,
        permissions: mode.role.permissions,
        // Hydrate current members from the role detail (`RoleResource.users`);
        // fall back to an empty selection if the field is absent.
        users: mode.role.users ?? [],
      }
    }
    return { name: '', permissions: [], users: [] }
  }, [mode])

  const form = useForm<RoleFormValues>({
    resolver: zodResolver(schema),
    defaultValues,
  })

  const togglePermission = (
    permission: string,
    checked: boolean,
    current: string[],
  ) =>
    checked
      ? [...current, permission]
      : current.filter((value) => value !== permission)

  const toggleGroup = (
    groupPermissionNames: string[],
    checked: boolean,
    current: string[],
  ) => {
    if (checked) {
      const set = new Set(current)
      groupPermissionNames.forEach((permission) => set.add(permission))
      return Array.from(set)
    }
    const removed = new Set(groupPermissionNames)
    return current.filter((permission) => !removed.has(permission))
  }

  const onSubmit = async (values: RoleFormValues) => {
    setServerError(null)
    try {
      const saved =
        mode.type === 'edit'
          ? await updateRole(mode.role.id, buildUpdatePayload(values, mode.role))
          : await createRole(buildCreatePayload(values))

      toast.success(
        t(mode.type === 'edit' ? 'roles.form.updated' : 'roles.form.created'),
      )
      onSuccess(saved)
    } catch (error) {
      if (
        !applyServerValidationErrors(error, form.setError, [...SERVER_ERROR_FIELDS])
      ) {
        setServerError(t('roles.form.genericError'))
      }
    }
  }

  return (
    <Form {...form}>
      <form
        onSubmit={form.handleSubmit(onSubmit)}
        className="flex flex-1 flex-col gap-4 overflow-y-auto p-4"
        noValidate
      >
        <FormField
          control={form.control}
          name="name"
          render={({ field }) => (
            <FormItem>
              <FormLabel required>{t('roles.form.name')}</FormLabel>
              <FormControl>
                <Input autoComplete="off" {...field} />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />

        <FormField
          control={form.control}
          name="permissions"
          render={({ field }) => (
            <FormItem>
              <FormLabel>{t('roles.form.permissions')}</FormLabel>
              {groups.length === 0 ? (
                <p className="text-sm text-muted-foreground">
                  {t('roles.form.noPermissions')}
                </p>
              ) : (
                <div className="flex flex-col gap-4 rounded-md border p-3">
                  <label className="flex items-center gap-2 border-b pb-3 text-sm font-medium">
                    <input
                      type="checkbox"
                      className="size-4 accent-primary"
                      checked={
                        permissionOptions.length > 0 &&
                        permissionOptions.every((permission) =>
                          field.value.includes(permission),
                        )
                      }
                      onChange={(event) =>
                        field.onChange(
                          event.target.checked ? [...permissionOptions] : [],
                        )
                      }
                    />
                    {t('roles.form.selectAllGlobal')}
                  </label>
                  {groups.map((group) => {
                    const allChecked = group.permissions.every((permission) =>
                      field.value.includes(permission),
                    )
                    return (
                      <fieldset key={group.resource} className="flex flex-col gap-2">
                        <legend className="flex w-full items-center justify-between">
                          <span className="text-sm font-medium">
                            {resourceLabel(group.resource, i18n)}
                          </span>
                          <label className="flex items-center gap-1.5 text-xs font-normal text-muted-foreground">
                            <input
                              type="checkbox"
                              className="size-3.5 accent-primary"
                              checked={allChecked}
                              onChange={(event) =>
                                field.onChange(
                                  toggleGroup(
                                    group.permissions,
                                    event.target.checked,
                                    field.value,
                                  ),
                                )
                              }
                            />
                            {t('roles.form.selectAll')}
                          </label>
                        </legend>
                        <div className="grid grid-cols-2 gap-2 pl-1">
                          {group.permissions.map((permission) => {
                            const checked = field.value.includes(permission)
                            return (
                              <label
                                key={permission}
                                className="flex items-center gap-2 text-sm font-normal"
                              >
                                <input
                                  type="checkbox"
                                  className="size-4 accent-primary"
                                  checked={checked}
                                  onChange={(event) =>
                                    field.onChange(
                                      togglePermission(
                                        permission,
                                        event.target.checked,
                                        field.value,
                                      ),
                                    )
                                  }
                                />
                                <span>{abilityLabel(permission, i18n)}</span>
                              </label>
                            )
                          })}
                        </div>
                      </fieldset>
                    )
                  })}
                </div>
              )}
              <FormMessage />
            </FormItem>
          )}
        />

        <FormField
          control={form.control}
          name="users"
          render={({ field }) => (
            <FormItem>
              <FormLabel>{t('roles.form.users')}</FormLabel>
              <FormControl>
                <AsyncPaginatedMultiSelect
                  resource={USERS_FOR_SELECT_RESOURCE}
                  value={field.value}
                  onChange={field.onChange}
                  showAvatar
                  labels={{
                    placeholder: t('roles.form.usersPlaceholder'),
                    searchPlaceholder: t('roles.form.usersSearch'),
                    empty: t('roles.form.usersEmpty'),
                    error: t('roles.form.usersError'),
                    retry: t('common.retry'),
                    removeLabel: t('roles.form.usersRemove'),
                    triggerLabel: t('roles.form.users'),
                  }}
                />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />

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
            {t('roles.form.cancel')}
          </Button>
          <Button type="submit" disabled={form.formState.isSubmitting}>
            {form.formState.isSubmitting
              ? t('roles.form.saving')
              : t('roles.form.save')}
          </Button>
        </div>
      </form>
    </Form>
  )
}

/** Builds the create payload. */
function buildCreatePayload(values: RoleFormValues): CreateRolePayload {
  return {
    name: values.name,
    permissions: values.permissions,
    users: values.users,
  }
}

/**
 * Builds a partial PATCH payload carrying only fields that changed from the
 * original role.
 */
function buildUpdatePayload(
  values: RoleFormValues,
  original: RoleDetail,
): UpdateRolePayload {
  const payload: UpdateRolePayload = {}

  if (values.name !== original.name) {
    payload.name = values.name
  }
  if (!samePermissions(values.permissions, original.permissions)) {
    payload.permissions = values.permissions
  }
  // Only send `users` when it changed from the original membership
  // (`RoleResource.users`); an unchanged selection is omitted from the PATCH.
  if (!sameIdSet(values.users, original.users ?? [])) {
    payload.users = values.users
  }

  return payload
}

/** Order-insensitive comparison of two permission lists. */
function samePermissions(a: string[], b: string[]): boolean {
  return sameSet(a, b)
}

/** Order-insensitive comparison of two id lists. */
function sameIdSet(a: number[], b: number[]): boolean {
  return sameSet(a, b)
}

/** Order-insensitive equality of two primitive lists. */
function sameSet<T>(a: T[], b: T[]): boolean {
  if (a.length !== b.length) {
    return false
  }
  const set = new Set(b)
  return a.every((value) => set.has(value))
}
