import { Button } from '@/components/ui/button'
import { Input } from '@/components/ui/input'
import { Skeleton } from '@/components/ui/skeleton'
import { Form, FormControl } from '@/components/ui/form'
import { AsyncPaginatedMultiSelect } from '@/components/ui/async-paginated-multi-select'
import { USERS_FOR_SELECT_RESOURCE } from '@/features/users/for-select-api'
import { MetaField } from '@/features/authorization/MetaField'
import { abilityLabel, resourceLabel } from '@/features/roles/permission-labels'
import { toggleFieldPermission } from '@/features/roles/field-permission-toggle'
import { RoleFieldPermissions } from '@/features/roles/role-field-permissions'
import { useRoleForm } from '@/features/roles/use-role-form'
import type { RoleFormMode } from '@/features/roles/role-form'
import type { RoleDetail } from '@/features/roles/types'

interface RoleFormBodyProps {
  mode: RoleFormMode
  permissionOptions: string[]
  onSuccess: (role: RoleDetail) => void
  onCancel: () => void
}

/**
 * The role create/edit form UI. Every field is wrapped in `MetaField` (spec
 * 0004): hidden fields are absent, non-editable fields render disabled,
 * `required` comes from the resolved `ResourcePermissions` — no hardcoded
 * permission logic lives here. All non-render logic lives in `useRoleForm`.
 */
export function RoleFormBody({
  mode,
  permissionOptions,
  onSuccess,
  onCancel,
}: RoleFormBodyProps) {
  const {
    t,
    i18n,
    form,
    serverError,
    groups,
    onSubmit,
    togglePermission,
    toggleGroup,
    canManageFieldPermissions,
    fieldCatalogueQuery,
  } = useRoleForm({ mode, permissionOptions, onSuccess })

  return (
    <Form {...form}>
      <form
        onSubmit={form.handleSubmit(onSubmit)}
        className="flex flex-1 flex-col gap-4 overflow-y-auto p-4"
        noValidate
      >
        <MetaField control={form.control} name="name" metaKey="name" label={t('roles.form.name')}>
          {({ field, disabled, readOnly }) => (
            <FormControl>
              <Input autoComplete="off" disabled={disabled} readOnly={readOnly} {...field} />
            </FormControl>
          )}
        </MetaField>

        <MetaField
          control={form.control}
          name="permissions"
          metaKey="permissions"
          label={t('roles.form.permissions')}
        >
          {({ field, disabled }) =>
            groups.length === 0 ? (
              <p className="text-sm text-muted-foreground">
                {t('roles.form.noPermissions')}
              </p>
            ) : (
              <div className="flex flex-col gap-4 rounded-md border p-3">
                <label className="flex items-center gap-2 border-b pb-3 text-sm font-medium">
                  <input
                    type="checkbox"
                    className="size-4 accent-primary"
                    disabled={disabled}
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
                            disabled={disabled}
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
                                disabled={disabled}
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
            )
          }
        </MetaField>

        <MetaField control={form.control} name="users" metaKey="users" label={t('roles.form.users')}>
          {({ field, disabled }) => (
            <FormControl>
              <AsyncPaginatedMultiSelect
                resource={USERS_FOR_SELECT_RESOURCE}
                value={field.value}
                onChange={field.onChange}
                showAvatar
                disabled={disabled}
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
          )}
        </MetaField>

        {canManageFieldPermissions && (
          <MetaField
            control={form.control}
            name="field_permissions"
            metaKey="field_permissions"
            label={t('roles.fieldPermissions.title')}
          >
            {({ field, disabled }) =>
              fieldCatalogueQuery.isPending ? (
                <div className="flex flex-col gap-2" aria-hidden="true">
                  <Skeleton className="h-9 w-full" />
                  <Skeleton className="h-9 w-full" />
                </div>
              ) : fieldCatalogueQuery.isError ? (
                <div className="flex items-center justify-between gap-2">
                  <p className="text-sm text-destructive" role="alert">
                    {t('roles.fieldPermissions.loadError')}
                  </p>
                  <Button
                    type="button"
                    variant="outline"
                    size="sm"
                    onClick={() => fieldCatalogueQuery.refetch()}
                  >
                    {t('common.retry')}
                  </Button>
                </div>
              ) : (
                <RoleFieldPermissions
                  resources={fieldCatalogueQuery.data.resources}
                  value={field.value}
                  disabled={disabled}
                  onToggle={(resource, key, flag, checked) =>
                    field.onChange(
                      toggleFieldPermission(field.value, resource, key, flag, checked),
                    )
                  }
                />
              )
            }
          </MetaField>
        )}

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
