import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { IdCard, MapPin, Phone } from 'lucide-react'
import type { LucideIcon } from 'lucide-react'
import type { Control } from 'react-hook-form'
import { useController } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { AddressCreateField } from '@/features/personal-data/address-create-field'
import { ContactsManager } from '@/features/personal-data/contacts-manager'
import type { RequestWorkFormValues } from '@/features/request-management/request-work-schema'

interface RequestClientSectionProps {
  control: Control<RequestWorkFormValues>
}

/**
 * One labelled group inside the anagraphic card. Compact by design: the card
 * header already carries the section identity, these only say which part of it
 * the fields below belong to.
 */
function ClientGroup({ icon: Icon, title, children }: { icon: LucideIcon; title: string; children: ReactNode }) {
  return (
    <div className="flex flex-col gap-2.5">
      <h4 className="flex items-center gap-1.5 text-xs font-medium tracking-wide text-muted-foreground uppercase">
        <Icon className="size-3.5" aria-hidden="true" />
        {title}
      </h4>
      {children}
    </div>
  )
}

/**
 * The "anagrafica" section of the work panel: the client's contacts and
 * address in a SINGLE card, split into two labelled groups, both as
 * ALWAYS-ACTIVE inline inputs (the quick-create surface, not the
 * read-then-open-a-dialog one) so the operator types straight into them.
 *
 * Both fields are buffered inside the panel's RHF form and travel with its
 * single submit — no per-field persistence — which is why `ContactsManager`
 * gets no `persistence` prop here: `createMode` (the four inline quick fields)
 * and immediate persistence do not compose, the quick fields write to the
 * buffer only. Extra channels beyond email/phone/pec/fax stay available
 * through the manager's own dialog and are saved by the same submit.
 */
export function RequestClientSection({ control }: RequestClientSectionProps) {
  const { t } = useTranslation()
  const contacts = useController({ control, name: 'client_contacts' })
  const address = useController({ control, name: 'client_address' })

  return (
    <FormSection
      icon={IdCard}
      title={t('requestManagement.workPanel.client.title', { defaultValue: 'Client details' })}
      description={t('requestManagement.workPanel.client.description', {
        defaultValue: 'Contacts and address of the client.',
      })}
    >
      <ClientGroup
        icon={Phone}
        title={t('requestManagement.workPanel.client.contactsGroup', { defaultValue: 'Contacts' })}
      >
        <ContactsManager
          value={contacts.field.value}
          onChange={contacts.field.onChange}
          showHeader={false}
          createMode
        />
      </ClientGroup>

      <div className="border-t" />

      <ClientGroup
        icon={MapPin}
        title={t('requestManagement.workPanel.client.addressGroup', { defaultValue: 'Address' })}
      >
        <AddressCreateField
          value={address.field.value}
          onChange={address.field.onChange}
          cityRequired={false}
        />
      </ClientGroup>
    </FormSection>
  )
}
