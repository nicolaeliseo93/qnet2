import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Pencil, Plus, Trash2 } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { ContactForm } from '@/features/personal-data/contact-form'
import { nextDraftKey } from '@/features/personal-data/drafts'
import { useEnumOptions } from '@/features/config/use-config'
import type { ContactDraft } from '@/features/personal-data/types'

interface ContactsManagerProps {
  /** The buffered contacts owned by the parent form. */
  value: ContactDraft[]
  /** Emits the next buffer after any add/edit/remove. */
  onChange: (next: ContactDraft[]) => void
}

/** `new` = the add form is open; a string = that contact `_key` is being edited. */
type EditingState = 'new' | string | null

/**
 * Reusable CRUD list for an owner's contacts, controlled/buffered: it never
 * touches the network — add/edit/remove mutate the buffer through `onChange`, and
 * the parent submits the buffer as part of the user payload (ADR 0012). Enforces
 * one primary contact per type in the buffer, mirroring the backend.
 */
export function ContactsManager({ value, onChange }: ContactsManagerProps) {
  const { t } = useTranslation()
  const [editing, setEditing] = useState<EditingState>(null)
  const typeOptions = useEnumOptions('contact_type')

  /** Resolves a contact `type` enum value to its localized label. */
  const typeLabelOf = (type: string) =>
    typeOptions.find((option) => option.value === type)?.label ?? type

  /** Demotes other contacts of the same type when one is marked primary. */
  const enforcePrimary = (
    contacts: ContactDraft[],
    primaryKey: string,
    type: string,
  ): ContactDraft[] =>
    contacts.map((contact) =>
      contact._key !== primaryKey && contact.type === type
        ? { ...contact, is_primary: false }
        : contact,
    )

  const handleAdd = (fields: Omit<ContactDraft, '_key'>) => {
    const draft: ContactDraft = { ...fields, _key: nextDraftKey() }
    const next = [...value, draft]
    onChange(draft.is_primary ? enforcePrimary(next, draft._key, draft.type) : next)
    setEditing(null)
  }

  const handleEdit = (key: string, fields: Omit<ContactDraft, '_key'>) => {
    const next = value.map((contact) =>
      contact._key === key ? { ...fields, _key: key } : contact,
    )
    onChange(
      fields.is_primary ? enforcePrimary(next, key, fields.type) : next,
    )
    setEditing(null)
  }

  const handleDelete = (key: string) => {
    if (!window.confirm(t('personalData.contacts.deleteConfirm'))) {
      return
    }
    onChange(value.filter((contact) => contact._key !== key))
  }

  return (
    <section className="flex flex-col gap-2">
      <div className="flex items-center justify-between">
        <h4 className="text-sm font-medium">{t('personalData.contacts.title')}</h4>
        <Button
          type="button"
          variant="outline"
          size="sm"
          onClick={() => setEditing('new')}
          disabled={editing === 'new'}
        >
          <Plus aria-hidden="true" />
          {t('personalData.contacts.add')}
        </Button>
      </div>

      {value.length === 0 && editing !== 'new' && (
        <p className="text-sm text-muted-foreground">
          {t('personalData.contacts.empty')}
        </p>
      )}

      <ul className="flex flex-col gap-2">
        {value.map((contact) =>
          editing === contact._key ? (
            <li key={contact._key}>
              <ContactForm
                contact={contact}
                onSubmit={(fields) => handleEdit(contact._key, fields)}
                onCancel={() => setEditing(null)}
              />
            </li>
          ) : (
            <li
              key={contact._key}
              className="flex items-center justify-between gap-2 rounded-md border p-2"
            >
              <div className="flex min-w-0 flex-col">
                <span className="flex items-center gap-2">
                  <span className="truncate text-sm">{contact.value}</span>
                  {contact.is_primary && (
                    <Badge variant="secondary">
                      {t('personalData.contacts.primaryBadge')}
                    </Badge>
                  )}
                </span>
                <span className="flex items-center gap-2 text-xs text-muted-foreground">
                  <Badge variant="outline" className="font-normal">
                    {typeLabelOf(contact.type)}
                  </Badge>
                  {contact.label && <span className="truncate">{contact.label}</span>}
                </span>
              </div>
              <div className="flex shrink-0 gap-1">
                <Button
                  type="button"
                  variant="ghost"
                  size="icon-sm"
                  aria-label={t('personalData.contacts.editAction')}
                  onClick={() => setEditing(contact._key)}
                >
                  <Pencil aria-hidden="true" />
                </Button>
                <Button
                  type="button"
                  variant="ghost"
                  size="icon-sm"
                  aria-label={t('personalData.contacts.deleteAction')}
                  onClick={() => handleDelete(contact._key)}
                >
                  <Trash2 aria-hidden="true" />
                </Button>
              </div>
            </li>
          ),
        )}
      </ul>

      {editing === 'new' && (
        <ContactForm onSubmit={handleAdd} onCancel={() => setEditing(null)} />
      )}
    </section>
  )
}
