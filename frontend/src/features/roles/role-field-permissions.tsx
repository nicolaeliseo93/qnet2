import { useTranslation } from 'react-i18next'
import { fieldPermissionLabel, resourceLabel } from '@/features/roles/permission-labels'
import type { FieldPermissionFlag } from '@/features/roles/field-permission-toggle'
import type { FieldCatalogueResource } from '@/features/roles/field-catalogue-api'
import type { RoleFieldPermission } from '@/features/roles/types'

interface RoleFieldPermissionsProps {
  resources: FieldCatalogueResource[]
  value: RoleFieldPermission[]
  onToggle: (resource: string, field: string, flag: FieldPermissionFlag, checked: boolean) => void
  disabled: boolean
}

/**
 * Per-role field-permission matrix (spec 0006): for every registered
 * resource's fields, three toggles (visible / editable / required)
 * expressing a DB-driven RESTRICTION within the 0004 code ceiling — never an
 * escalation, enforced server-side. Reuses the existing permission-matrix
 * checkbox styling (`RoleFormBody`'s permissions section) rather than a new
 * `components/ui` primitive. UI-only: value/toggling logic lives in the
 * caller (`useRoleForm` + `field-permission-toggle.ts`).
 */
export function RoleFieldPermissions({
  resources,
  value,
  onToggle,
  disabled,
}: RoleFieldPermissionsProps) {
  const { t, i18n } = useTranslation()

  if (resources.length === 0) {
    return <p className="text-sm text-muted-foreground">{t('roles.fieldPermissions.empty')}</p>
  }

  return (
    <div className="flex flex-col gap-4 rounded-md border p-3">
      {resources.map((entry) => (
        <fieldset key={entry.resource} className="flex flex-col gap-2">
          <legend className="text-sm font-medium">{resourceLabel(entry.resource, i18n)}</legend>
          <div className="grid grid-cols-[1fr_auto_auto_auto] items-center gap-x-3 gap-y-1.5 pl-1">
            <span />
            <span className="text-center text-xs text-muted-foreground">
              {t('roles.fieldPermissions.visible')}
            </span>
            <span className="text-center text-xs text-muted-foreground">
              {t('roles.fieldPermissions.editable')}
            </span>
            <span className="text-center text-xs text-muted-foreground">
              {t('roles.fieldPermissions.required')}
            </span>
            {entry.fields.map((field) => {
              const rowValue = value.find(
                (row) => row.resource === entry.resource && row.field === field.key,
              )
              return (
                <FieldRow
                  key={field.key}
                  resource={entry.resource}
                  fieldKey={field.key}
                  label={fieldPermissionLabel(entry.resource, field.key, i18n)}
                  visible={rowValue?.visible ?? true}
                  editable={rowValue?.editable ?? true}
                  required={rowValue?.required ?? false}
                  mandatory={field.mandatory}
                  disabled={disabled}
                  onToggle={onToggle}
                />
              )
            })}
          </div>
        </fieldset>
      ))}
    </div>
  )
}

interface FieldRowProps {
  resource: string
  fieldKey: string
  label: string
  visible: boolean
  editable: boolean
  required: boolean
  mandatory: boolean
  disabled: boolean
  onToggle: (resource: string, field: string, flag: FieldPermissionFlag, checked: boolean) => void
}

/** One field's label + three toggles, as grid cells (a fragment, not a row element). */
function FieldRow({
  resource,
  fieldKey,
  label,
  visible,
  editable,
  required,
  mandatory,
  disabled,
  onToggle,
}: FieldRowProps) {
  const { t } = useTranslation()

  // Mandatory fields (spec 0008) are vital to creating the resource: a role can
  // never restrict them, so all three checkboxes are forced on and disabled —
  // the client twin of the server-side merge that ignores their DB config.
  const locked = disabled || mandatory

  return (
    <>
      <span className="truncate text-sm" title={mandatory ? t('roles.fieldPermissions.mandatory') : undefined}>
        {label}
        {mandatory ? <span className="text-muted-foreground"> *</span> : null}
      </span>
      <FieldToggle
        checked={mandatory ? true : visible}
        disabled={locked}
        label={`${label} — ${t('roles.fieldPermissions.visible')}`}
        onChange={(checked) => onToggle(resource, fieldKey, 'visible', checked)}
      />
      <FieldToggle
        checked={mandatory ? true : editable}
        disabled={locked}
        label={`${label} — ${t('roles.fieldPermissions.editable')}`}
        onChange={(checked) => onToggle(resource, fieldKey, 'editable', checked)}
      />
      <FieldToggle
        // `required` is only meaningful when `editable` (spec 0006 merge rule).
        checked={mandatory ? true : required}
        disabled={locked || !editable}
        label={`${label} — ${t('roles.fieldPermissions.required')}`}
        onChange={(checked) => onToggle(resource, fieldKey, 'required', checked)}
      />
    </>
  )
}

interface FieldToggleProps {
  checked: boolean
  disabled: boolean
  label: string
  onChange: (checked: boolean) => void
}

function FieldToggle({ checked, disabled, label, onChange }: FieldToggleProps) {
  return (
    <label className="flex items-center justify-center">
      <span className="sr-only">{label}</span>
      <input
        type="checkbox"
        className="size-4 accent-primary"
        checked={checked}
        disabled={disabled}
        onChange={(event) => onChange(event.target.checked)}
      />
    </label>
  )
}
