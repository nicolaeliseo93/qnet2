import type { i18n as I18nInstance } from 'i18next'
import { ChevronDown, KeySquare, ListChecks, ShieldCheck } from 'lucide-react'
import { cn } from '@/lib/utils'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Checkbox } from '@/components/ui/checkbox'
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible'
import { Input } from '@/components/ui/input'
import { Skeleton } from '@/components/ui/skeleton'
import { Form, FormControl } from '@/components/ui/form'
import { AsyncPaginatedMultiSelect } from '@/components/ui/async-paginated-multi-select'
import { FormSection } from '@/components/form-section'
import { USERS_FOR_SELECT_RESOURCE } from '@/features/users/for-select-api'
import { MetaField } from '@/features/authorization/MetaField'
import { abilityLabel, resourceLabel } from '@/features/roles/permission-labels'
import { permissionAbility, type PermissionGroup } from '@/features/roles/permission-groups'
import { toggleFieldPermission } from '@/features/roles/field-permission-toggle'
import { RoleFieldPermissions } from '@/features/roles/role-field-permissions'
import { useRoleForm } from '@/features/roles/use-role-form'
import { CustomFieldsSection } from '@/features/custom-fields/CustomFieldsSection'
import type { RoleFormMode } from '@/features/roles/role-form'
import type { RoleDetail } from '@/features/roles/types'

interface RoleFormBodyProps {
  mode: RoleFormMode
  permissionOptions: string[]
  onSuccess: (role: RoleDetail) => void
  onCancel: () => void
}

/**
 * Ability suffixes rendered inline in every domain card. Anything else
 * (`export`, `import`, …) collapses into that domain's "advanced" disclosure
 * so the common CRUD abilities stay scannable at a glance.
 */
const PRIMARY_ABILITIES: readonly string[] = ['viewAny', 'view', 'create', 'update', 'delete']

/**
 * The role create/edit form UI. Every field is wrapped in `MetaField` (spec
 * 0004): hidden fields are absent, non-editable fields render disabled,
 * `required` comes from the resolved `ResourcePermissions` — no hardcoded
 * permission logic lives here. All non-render logic lives in `useRoleForm`.
 * Presentation is grouped into three `FormSection` cards: role details,
 * the permission matrix (by domain, primary abilities inline / advanced
 * collapsed), and — when the actor may manage it — the field-permission
 * matrix (spec 0006/0008), rendered by `RoleFieldPermissions`.
 * `<CustomFieldsSection>` (spec 0021) mounts the resource's admin-defined
 * custom fields with zero roles-specific rendering/validation logic.
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

  const allPermissionsSelected = (value: string[]) =>
    permissionOptions.length > 0 &&
    permissionOptions.every((permission) => value.includes(permission))

  return (
    <Form {...form}>
      <form
        onSubmit={form.handleSubmit(onSubmit)}
        className="flex flex-1 flex-col gap-4 overflow-y-auto p-4"
        noValidate
      >
        <FormSection
          icon={ShieldCheck}
          title={t('roles.form.sections.details.title')}
          description={t('roles.form.sections.details.description')}
        >
          <MetaField control={form.control} name="name" metaKey="name" label={t('roles.form.name')}>
            {({ field, disabled, readOnly }) => (
              <FormControl>
                <Input autoComplete="off" disabled={disabled} readOnly={readOnly} {...field} />
              </FormControl>
            )}
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
        </FormSection>

        {/* `label=""`: `FormSection` below already carries the visible
            section title — an extra `FormLabel` with the same text would
            just duplicate it in the accessibility tree. */}
        <MetaField control={form.control} name="permissions" metaKey="permissions" label="">
          {({ field, disabled }) => (
            <FormSection
              icon={KeySquare}
              title={t('roles.form.sections.permissions.title')}
              description={t('roles.form.sections.permissions.description')}
              aside={
                groups.length > 0 ? (
                  <LabeledCheckbox
                    checked={allPermissionsSelected(field.value)}
                    disabled={disabled}
                    label={t('roles.form.selectAllGlobal')}
                    className="text-xs font-medium text-muted-foreground"
                    onChange={(checked) =>
                      field.onChange(checked ? [...permissionOptions] : [])
                    }
                  />
                ) : null
              }
            >
              {groups.length === 0 ? (
                <p className="text-sm text-muted-foreground">{t('roles.form.noPermissions')}</p>
              ) : (
                groups.map((group) => (
                  <PermissionDomainCard
                    key={group.resource}
                    group={group}
                    value={field.value}
                    disabled={disabled}
                    t={t}
                    i18n={i18n}
                    onToggleGroup={(checked) =>
                      field.onChange(toggleGroup(group.permissions, checked, field.value))
                    }
                    onTogglePermission={(permission, checked) =>
                      field.onChange(togglePermission(permission, checked, field.value))
                    }
                  />
                ))
              )}
            </FormSection>
          )}
        </MetaField>

        {canManageFieldPermissions && (
          <MetaField control={form.control} name="field_permissions" metaKey="field_permissions" label="">
            {({ field, disabled }) => (
              <FormSection icon={ListChecks} title={t('roles.fieldPermissions.title')}>
                {fieldCatalogueQuery.isPending ? (
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
                )}
              </FormSection>
            )}
          </MetaField>
        )}

        <CustomFieldsSection resource="roles" control={form.control} />

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

interface PermissionDomainCardProps {
  group: PermissionGroup
  value: string[]
  disabled: boolean
  t: (key: string) => string
  i18n: I18nInstance
  onToggleGroup: (checked: boolean) => void
  onTogglePermission: (permission: string, checked: boolean) => void
}

/**
 * One resource's permission domain: a header (resource label + selected/total
 * count + per-domain select-all), the primary CRUD abilities as pills, and —
 * only when the domain has any — an "advanced configuration" disclosure for
 * the rest (`export`, `import`, …).
 */
function PermissionDomainCard({
  group,
  value,
  disabled,
  t,
  i18n,
  onToggleGroup,
  onTogglePermission,
}: PermissionDomainCardProps) {
  const selectedCount = group.permissions.filter((permission) => value.includes(permission)).length
  const primary = group.permissions.filter((permission) =>
    PRIMARY_ABILITIES.includes(permissionAbility(permission)),
  )
  const advanced = group.permissions.filter(
    (permission) => !PRIMARY_ABILITIES.includes(permissionAbility(permission)),
  )

  return (
    <div className="rounded-lg border p-3">
      <div className="flex flex-wrap items-center justify-between gap-2">
        <div className="flex items-center gap-2">
          <span className="text-sm font-medium">{resourceLabel(group.resource, i18n)}</span>
          <Badge variant="secondary">
            {selectedCount}/{group.permissions.length}
          </Badge>
        </div>
        <LabeledCheckbox
          checked={selectedCount === group.permissions.length}
          disabled={disabled}
          label={t('roles.form.selectAll')}
          className="text-xs font-normal text-muted-foreground"
          onChange={onToggleGroup}
        />
      </div>

      {primary.length > 0 && (
        <div className="mt-3 flex flex-wrap gap-2">
          {primary.map((permission) => (
            <AbilityPill
              key={permission}
              checked={value.includes(permission)}
              disabled={disabled}
              label={abilityLabel(permission, i18n)}
              onChange={(checked) => onTogglePermission(permission, checked)}
            />
          ))}
        </div>
      )}

      {advanced.length > 0 && (
        <Collapsible className="mt-3">
          <CollapsibleTrigger className="flex items-center gap-1.5 text-xs font-medium text-muted-foreground transition-colors hover:text-foreground [&[data-state=open]>svg]:rotate-180">
            <ChevronDown className="size-3.5 transition-transform" aria-hidden="true" />
            {t('roles.form.advanced')}
          </CollapsibleTrigger>
          <CollapsibleContent className="mt-2 flex flex-col gap-1.5 border-t pt-2">
            <span className="text-xs font-medium text-muted-foreground">
              {t('roles.form.advancedActions')}
            </span>
            <div className="flex flex-wrap gap-2">
              {advanced.map((permission) => (
                <AbilityPill
                  key={permission}
                  checked={value.includes(permission)}
                  disabled={disabled}
                  label={abilityLabel(permission, i18n)}
                  onChange={(checked) => onTogglePermission(permission, checked)}
                />
              ))}
            </div>
          </CollapsibleContent>
        </Collapsible>
      )}
    </div>
  )
}

interface AbilityPillProps {
  checked: boolean
  disabled: boolean
  label: string
  onChange: (checked: boolean) => void
}

/** One ability toggle rendered as a checkbox pill, visually distinct when granted. */
function AbilityPill({ checked, disabled, label, onChange }: AbilityPillProps) {
  return (
    <LabeledCheckbox
      checked={checked}
      disabled={disabled}
      label={label}
      onChange={onChange}
      className={cn(
        'rounded-md border px-2 py-1 text-xs font-normal',
        checked ? 'border-primary/40 bg-primary/5 text-foreground' : 'border-border text-muted-foreground',
      )}
    />
  )
}

interface LabeledCheckboxProps {
  checked: boolean
  disabled: boolean
  label: string
  className?: string
  onChange: (checked: boolean) => void
}

/** A `Checkbox` plus its visible text label, sharing one clickable target. */
function LabeledCheckbox({ checked, disabled, label, className, onChange }: LabeledCheckboxProps) {
  return (
    <label className={cn('flex items-center gap-1.5', className)}>
      <Checkbox
        checked={checked}
        disabled={disabled}
        onCheckedChange={(next) => onChange(next === true)}
      />
      {label}
    </label>
  )
}
