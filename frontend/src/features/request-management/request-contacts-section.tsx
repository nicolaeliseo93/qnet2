import { useState } from 'react'
import { useTranslation } from 'react-i18next'
import { Building2, User } from 'lucide-react'
import type { LucideIcon } from 'lucide-react'
import { FormSection } from '@/components/form-section'
import { ContactsManager } from '@/features/personal-data/contacts-manager'
import type { ContactDraft } from '@/features/personal-data/types'
import type { RequestContact, RequestContactsBlock } from '@/features/request-management/types'

/** Maps one panel contact projection to `ContactsManager`'s buffered draft shape. */
function toContactDraft(contact: RequestContact): ContactDraft {
  return {
    _key: `contact-${contact.id}`,
    id: contact.id,
    type: contact.type,
    value: contact.value,
    label: contact.label,
    is_primary: contact.is_primary,
  }
}

interface RequestContactsBlockSectionProps {
  icon: LucideIcon
  titleKey: string
  defaultTitle: string
  block: RequestContactsBlock
}

/**
 * One owner's contacts block. The backend already resolves `block.owner` to
 * the PersonalData CARD ref (`{ type: 'personal_data', id }`) — the only
 * `contactable_type` the backend's allowlist accepts — so it is passed
 * straight through as `ContactsManager`'s `persistence`, no extra fetch.
 * Renders nothing when the opportunity has no linked owner of this kind yet
 * (no card to persist against).
 */
function RequestContactsBlockSection({ icon, titleKey, defaultTitle, block }: RequestContactsBlockSectionProps) {
  const { t } = useTranslation()
  const [contacts, setContacts] = useState<ContactDraft[]>(() => block.items.map(toContactDraft))

  if (!block.owner) {
    return null
  }

  return (
    <FormSection icon={icon} title={t(titleKey, { defaultValue: defaultTitle })}>
      <ContactsManager value={contacts} onChange={setContacts} persistence={block.owner} showHeader={false} />
    </FormSection>
  )
}

interface RequestContactsSectionProps {
  /** The Registry (client anagrafica)'s contacts block, `owner: null` when the opportunity has no Registry yet. */
  registry: RequestContactsBlock
  /** The Referent (contact person)'s contacts block, `owner: null` when the opportunity has no Referent yet. */
  referent: RequestContactsBlock
}

/** Verify/complete the record's real contacts: the Registry (client) and the Referent (spec 0049 AC-061). */
export function RequestContactsSection({ registry, referent }: RequestContactsSectionProps) {
  return (
    <>
      <RequestContactsBlockSection
        icon={Building2}
        titleKey="requestManagement.workPanel.contacts.registryTitle"
        defaultTitle="Registry contacts"
        block={registry}
      />
      <RequestContactsBlockSection
        icon={User}
        titleKey="requestManagement.workPanel.contacts.referentTitle"
        defaultTitle="Referent contacts"
        block={referent}
      />
    </>
  )
}
