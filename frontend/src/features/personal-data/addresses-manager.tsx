import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { MapPin, Pencil, Plus, Trash2 } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { useConfirm } from '@/components/confirm-dialog-context'
import { AddressForm } from '@/features/personal-data/address-form'
import { nextDraftKey } from '@/features/personal-data/drafts'
import type {
  AddressDraft,
  PersonalDataFieldPermissionResolver,
} from '@/features/personal-data/types'

interface AddressesManagerProps {
  /** The buffered addresses owned by the parent form. */
  value: AddressDraft[]
  /** Emits the next buffer after any add/edit/remove. */
  onChange: (next: AddressDraft[]) => void
  /**
   * Resolves gating for the whole section (`personal_data.addresses`, spec
   * 0008): `!visible` hides the section entirely; `!editable` makes it
   * read-only (no add/edit/remove). Optional: omitting it keeps today's
   * ungated behaviour (self-service profile, AC-013).
   */
  fieldPermission?: PersonalDataFieldPermissionResolver
  /**
   * Renders the built-in title + top "Add" affordance. Defaults to `true`
   * (today's standalone look). Pass `false` when an ancestor (e.g. a
   * `FormSection`) already renders the heading, so only the rows plus a
   * trailing "Add" action are rendered.
   */
  showHeader?: boolean
}

/** `new` = the add form is open; a string = that address `_key` is being edited. */
type EditingState = 'new' | string | null

/**
 * Reusable CRUD list for an owner's addresses, controlled/buffered: it never
 * touches the network — add/edit/remove mutate the buffer through `onChange`, and
 * the parent submits the buffer as part of the user payload (ADR 0012). Enforces
 * a single primary address per owner in the buffer, mirroring the backend
 * (ADR 0010); when none is primary, the first address becomes the default.
 */
export function AddressesManager({
  value,
  onChange,
  fieldPermission,
  showHeader = true,
}: AddressesManagerProps) {
  const { t } = useTranslation()
  const confirm = useConfirm()
  const [editing, setEditing] = useState<EditingState>(null)
  const permission = fieldPermission?.('personal_data.addresses')

  if (permission && !permission.visible) {
    return null
  }
  const readOnly = permission ? permission.disabled || !permission.editable : false

  /**
   * Normalizes the single-primary invariant: if a key is forced primary it wins
   * and the rest are demoted; otherwise, when no address is primary, the first
   * becomes the default.
   */
  const normalizePrimary = (
    addresses: AddressDraft[],
    primaryKey?: string,
  ): AddressDraft[] => {
    if (primaryKey) {
      return addresses.map((address) => ({
        ...address,
        is_primary: address._key === primaryKey,
      }))
    }
    if (addresses.length > 0 && !addresses.some((address) => address.is_primary)) {
      return addresses.map((address, index) => ({
        ...address,
        is_primary: index === 0,
      }))
    }
    return addresses
  }

  const handleAdd = (fields: Omit<AddressDraft, '_key'>) => {
    const draft: AddressDraft = { ...fields, _key: nextDraftKey() }
    const next = [...value, draft]
    onChange(normalizePrimary(next, draft.is_primary ? draft._key : undefined))
    setEditing(null)
  }

  const handleEdit = (key: string, fields: Omit<AddressDraft, '_key'>) => {
    const next = value.map((address) =>
      address._key === key ? { ...fields, _key: key } : address,
    )
    onChange(normalizePrimary(next, fields.is_primary ? key : undefined))
    setEditing(null)
  }

  const handleDelete = async (key: string) => {
    const confirmed = await confirm({
      tone: 'destructive',
      title: t('personalData.addresses.deleteAction'),
      description: t('personalData.addresses.deleteConfirm'),
      confirmLabel: t('personalData.addresses.deleteAction'),
    })
    if (!confirmed) {
      return
    }
    onChange(normalizePrimary(value.filter((address) => address._key !== key)))
  }

  return (
    <section className="flex flex-col gap-2">
      {showHeader && (
        <div className="flex items-center justify-between">
          <h4 className="text-sm font-medium">{t('personalData.addresses.title')}</h4>
          {!readOnly && (
            <Button
              type="button"
              variant="outline"
              size="sm"
              onClick={() => setEditing('new')}
              disabled={editing === 'new'}
            >
              <Plus aria-hidden="true" />
              {t('personalData.addresses.add')}
            </Button>
          )}
        </div>
      )}

      {value.length === 0 && editing !== 'new' && (
        <p className="text-sm text-muted-foreground">
          {t('personalData.addresses.empty')}
        </p>
      )}

      <ul className="flex flex-col gap-2">
        {value.map((address) =>
          !readOnly && editing === address._key ? (
            <li key={address._key}>
              <AddressForm
                address={address}
                onSubmit={(fields) => handleEdit(address._key, fields)}
                onCancel={() => setEditing(null)}
              />
            </li>
          ) : (
            <li
              key={address._key}
              className="flex flex-wrap items-center gap-2 rounded-lg border p-3"
            >
              <span className="flex size-8 shrink-0 items-center justify-center rounded-md bg-muted text-muted-foreground">
                <MapPin className="size-4" aria-hidden="true" />
              </span>
              <div className="flex min-w-0 flex-1 flex-col">
                <span className="truncate text-sm font-medium">{address.line1}</span>
                <span className="truncate text-xs text-muted-foreground">
                  {[address.line2, address.postal_code]
                    .filter(Boolean)
                    .join(' · ')}
                </span>
              </div>
              {address.is_primary && (
                <Badge variant="secondary">
                  {t('personalData.addresses.primaryBadge')}
                </Badge>
              )}
              {!readOnly && (
                <div className="flex shrink-0 gap-1">
                  <Button
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    aria-label={t('personalData.addresses.editAction')}
                    onClick={() => setEditing(address._key)}
                  >
                    <Pencil aria-hidden="true" />
                  </Button>
                  <Button
                    type="button"
                    variant="ghost"
                    size="icon-sm"
                    aria-label={t('personalData.addresses.deleteAction')}
                    onClick={() => handleDelete(address._key)}
                  >
                    <Trash2 aria-hidden="true" />
                  </Button>
                </div>
              )}
            </li>
          ),
        )}
      </ul>

      {!readOnly && editing === 'new' && (
        <AddressForm onSubmit={handleAdd} onCancel={() => setEditing(null)} />
      )}

      {!showHeader && !readOnly && (
        <Button
          type="button"
          variant="ghost"
          size="sm"
          onClick={() => setEditing('new')}
          disabled={editing === 'new'}
          className="self-start"
        >
          <Plus aria-hidden="true" />
          {t('personalData.addresses.add')}
        </Button>
      )}
    </section>
  )
}
