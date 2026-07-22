import type { ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { ChevronDown, IdCard, MapPin, Phone, UserRound } from 'lucide-react'
import type { LucideIcon } from 'lucide-react'
import type { Control } from 'react-hook-form'
import { useController } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { Collapsible, CollapsibleContent, CollapsibleTrigger } from '@/components/ui/collapsible'
import { AddressCreateField } from '@/features/personal-data/address-create-field'
import { ContactsManager } from '@/features/personal-data/contacts-manager'
import { PersonalDataCardForm } from '@/features/personal-data/personal-data-card-form'
import type { AddressDraft } from '@/features/personal-data/types'
import type { RequestWorkFormValues } from '@/features/request-management/request-work-schema'

interface RequestClientSectionProps {
  control: Control<RequestWorkFormValues>
}

interface ClientGroupProps {
  icon: LucideIcon
  title: string
  children: ReactNode
  /** Turns the title into a toggle for the fields below (default: false). */
  collapsible?: boolean
  /** One-line recap rendered next to the title while the group is CLOSED. */
  summary?: string
}

/** The group's own title row, shared by the plain and the collapsible variant. */
const GROUP_TITLE_CLASS =
  'flex items-center gap-1.5 text-xs font-medium tracking-wide text-muted-foreground uppercase'

/**
 * One labelled group inside the anagraphic card. Compact by design: the card
 * header already carries the section identity, these only say which part of it
 * the fields below belong to.
 *
 * A `collapsible` group starts CLOSED and shows its `summary` inline instead of
 * its fields, so a block the operator only verifies (the address) costs one
 * row of the panel rather than a full field grid.
 */
function ClientGroup({ icon: Icon, title, children, collapsible = false, summary }: ClientGroupProps) {
  if (!collapsible) {
    return (
      <div className="flex flex-col gap-2.5">
        <h4 className={GROUP_TITLE_CLASS}>
          <Icon className="size-3.5" aria-hidden="true" />
          {title}
        </h4>
        {children}
      </div>
    )
  }

  return (
    <Collapsible className="flex flex-col gap-2.5">
      <CollapsibleTrigger className={`${GROUP_TITLE_CLASS} group w-full text-left`}>
        <Icon className="size-3.5 shrink-0" aria-hidden="true" />
        {title}
        {summary ? (
          <span className="min-w-0 truncate text-xs font-normal normal-case group-data-[state=open]:hidden">
            {summary}
          </span>
        ) : null}
        <ChevronDown
          className="ml-auto size-3.5 shrink-0 transition-transform motion-safe:duration-200 group-data-[state=open]:rotate-180"
          aria-hidden="true"
        />
      </CollapsibleTrigger>
      {/* Same height animation as a collapsible `FormSection` (shared class,
          motion-safe): the group opens the way every other collapsible in the
          app does, instead of snapping. */}
      <CollapsibleContent className="form-section-collapsible-content">
        <div className="flex flex-col gap-2.5">{children}</div>
      </CollapsibleContent>
    </Collapsible>
  )
}

/**
 * The closed address group's recap: what the operator would read anyway
 * (street, postal code, city), or nothing when no address has been captured —
 * the caller then falls back to the "not set" hint.
 */
function addressSummary(address?: AddressDraft): string {
  if (!address) {
    return ''
  }

  return [address.line1, address.postal_code, address.city?.name].filter(Boolean).join(', ')
}

/**
 * The "anagrafica" section of the work panel: the client's identity, contacts
 * and address in a SINGLE card, split into labelled groups, all as
 * ALWAYS-ACTIVE inline inputs (the quick-create surface, not the
 * read-then-open-a-dialog one) so the operator types straight into them.
 *
 * The identity group reuses `PersonalDataCardForm` verbatim — the same
 * individual/company toggle and fiscal fields (tax code, VAT number, SDI,
 * birth date, gender) the Registries and Users forms render — so this panel
 * captures the client's data through the exact same surface, never a
 * look-alike. It is rendered only when the client HAS a card: without one
 * there is no write target (`client_identity` is then null on the wire).
 *
 * The address group is COLLAPSED by default with its value recapped in the
 * title row: on an already-qualified client it is verification, not data
 * entry, so it should not cost a field grid of vertical space.
 *
 * All three fields are buffered inside the panel's RHF form and travel with its
 * single submit — no per-field persistence — which is why `ContactsManager`
 * gets no `persistence` prop here: `createMode` (the four inline quick fields)
 * and immediate persistence do not compose, the quick fields write to the
 * buffer only. Extra channels beyond email/phone/pec/fax stay available
 * through the manager's own dialog and are saved by the same submit.
 */
export function RequestClientSection({ control }: RequestClientSectionProps) {
  const { t } = useTranslation()
  const identity = useController({ control, name: 'client_identity' })
  const contacts = useController({ control, name: 'client_contacts' })
  const address = useController({ control, name: 'client_address' })
  const identityDraft = identity.field.value

  return (
    <FormSection
      icon={IdCard}
      title={t('requestManagement.workPanel.client.title', { defaultValue: 'Client details' })}
      description={t('requestManagement.workPanel.client.description', {
        defaultValue: 'Contacts and address of the client.',
      })}
    >
      {identityDraft && (
        <>
          <ClientGroup
            icon={UserRound}
            title={t('requestManagement.workPanel.client.identityGroup', { defaultValue: 'Identity' })}
          >
            <PersonalDataCardForm value={identityDraft} onChange={identity.field.onChange} />
          </ClientGroup>

          <div className="border-t" />
        </>
      )}

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
        collapsible
        summary={
          addressSummary(address.field.value[0]) ||
          t('requestManagement.workPanel.client.addressEmpty', { defaultValue: 'Not set' })
        }
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
