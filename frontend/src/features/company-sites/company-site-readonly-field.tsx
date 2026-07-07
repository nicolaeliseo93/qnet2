import { useId } from 'react'
import { Input } from '@/components/ui/input'
import { useResourcePermissions } from '@/features/authorization/permissions'

interface CompanySiteReadonlyFieldProps {
  /** Authorization metadata key gating this field's visibility. */
  metaKey: string
  label: string
  value: string | number | null | undefined
}

/**
 * A single always-read-only field (spec 0020: the "Altro" section and the
 * `quotation_*` settings, both forced `visible + !editable` by the backend's
 * authorization ceiling regardless of role). Unlike `MetaField`, this is not
 * bound to RHF: none of these values are ever submitted, so registering them
 * as controlled form fields would add width without a corresponding write
 * path. Visibility still derives from `useResourcePermissions()` — the same
 * context `MetaField` reads — so it stays metadata-driven.
 */
export function CompanySiteReadonlyField({
  metaKey,
  label,
  value,
}: CompanySiteReadonlyFieldProps) {
  const { field: fieldPermission } = useResourcePermissions()
  const id = useId()

  if (!fieldPermission(metaKey).visible) {
    return null
  }

  return (
    <div className="flex flex-col gap-1.5">
      <label htmlFor={id} className="text-sm font-medium text-muted-foreground">
        {label}
      </label>
      <Input id={id} value={value ?? ''} disabled readOnly />
    </div>
  )
}
