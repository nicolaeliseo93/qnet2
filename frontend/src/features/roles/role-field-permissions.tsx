import { useTranslation } from 'react-i18next'
import { ChevronDown } from 'lucide-react'
import { Checkbox } from '@/components/ui/checkbox'
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible'
import { fieldPermissionLabel, resourceLabel } from '@/features/roles/permission-labels'
import type { FieldPermissionFlag } from '@/features/roles/field-permission-toggle'
import type { FieldCatalogueResource } from '@/features/roles/field-catalogue-api'
import type { FieldDescriptor } from '@/features/authorization/types'
import type { RoleFieldPermission } from '@/features/roles/types'

type ToggleFieldPermission = (
  resource: string,
  field: string,
  flag: FieldPermissionFlag,
  checked: boolean,
) => void

interface RoleFieldPermissionsProps {
  resources: FieldCatalogueResource[]
  value: RoleFieldPermission[]
  onToggle: ToggleFieldPermission
  disabled: boolean
}

/**
 * Per-role field-permission matrix (spec 0006): for every registered
 * resource's fields, three toggles (visible / editable / required)
 * expressing a DB-driven RESTRICTION within the 0004 code ceiling — never an
 * escalation, enforced server-side. Each resource collapses into its own
 * disclosure (collapsed by default) so a role touching several resources
 * stays scannable. UI-only: value/toggling logic lives in the caller
 * (`useRoleForm` + `field-permission-toggle.ts`).
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
    <div className="flex flex-col gap-2">
      {resources.map((entry) => (
        <Collapsible key={entry.resource} className="rounded-lg border">
          <CollapsibleTrigger className="flex w-full items-center gap-2 px-3 py-2 text-left text-sm font-medium transition-colors hover:bg-muted/50 [&[data-state=open]>svg]:rotate-180">
            <ChevronDown className="size-4 shrink-0 text-muted-foreground transition-transform" aria-hidden="true" />
            {resourceLabel(entry.resource, i18n)}
          </CollapsibleTrigger>
          <CollapsibleContent className="border-t px-3 pb-3 pt-2">
            <FieldMatrix
              resource={entry.resource}
              fields={entry.fields}
              value={value}
              disabled={disabled}
              onToggle={onToggle}
            />
          </CollapsibleContent>
        </Collapsible>
      ))}
    </div>
  )
}

interface FieldMatrixProps {
  resource: string
  fields: FieldDescriptor[]
  value: RoleFieldPermission[]
  disabled: boolean
  onToggle: ToggleFieldPermission
}

/** One resource's visible/editable/required grid, scrollable on its own so a long field list never forces page-level horizontal scroll. */
function FieldMatrix({ resource, fields, value, disabled, onToggle }: FieldMatrixProps) {
  const { t, i18n } = useTranslation()

  return (
    <div className="overflow-x-auto">
      <div className="grid min-w-[420px] grid-cols-[1fr_auto_auto_auto] items-center gap-x-3 gap-y-1.5">
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
        {fields.map((field) => {
          const rowValue = value.find(
            (row) => row.resource === resource && row.field === field.key,
          )
          return (
            <FieldRow
              key={field.key}
              resource={resource}
              fieldKey={field.key}
              label={fieldPermissionLabel(resource, field.key, i18n)}
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
  onToggle: ToggleFieldPermission
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
    <span className="flex items-center justify-center">
      <Checkbox
        checked={checked}
        disabled={disabled}
        aria-label={label}
        onCheckedChange={(next) => onChange(next === true)}
      />
    </span>
  )
}
