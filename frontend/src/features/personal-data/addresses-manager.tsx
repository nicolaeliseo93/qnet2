import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Pencil, Plus, Trash2 } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
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
export function AddressesManager({ value, onChange, fieldPermission }: AddressesManagerProps) {
  const { t } = useTranslation()
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

  const handleDelete = (key: string) => {
    if (!window.confirm(t('personalData.addresses.deleteConfirm'))) {
      return
    }
    onChange(normalizePrimary(value.filter((address) => address._key !== key)))
  }

  return (
    <section className="flex flex-col gap-2">
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
              className="flex items-center justify-between gap-2 rounded-md border p-2"
            >
              <div className="flex min-w-0 flex-col">
                <span className="flex items-center gap-2">
                  <span className="truncate text-sm">{address.line1}</span>
                  {address.is_primary && (
                    <Badge variant="secondary">
                      {t('personalData.addresses.primaryBadge')}
                    </Badge>
                  )}
                </span>
                <span className="text-xs text-muted-foreground">
                  {[address.label, address.postal_code]
                    .filter(Boolean)
                    .join(' · ')}
                </span>
              </div>
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
    </section>
  )
}
