import { useTranslation } from 'react-i18next'
import { Separator } from '@/components/ui/separator'
import { AddressesManager } from '@/features/personal-data/addresses-manager'
import { ContactsManager } from '@/features/personal-data/contacts-manager'
import { PersonalDataCardForm } from '@/features/personal-data/personal-data-card-form'
import type { PersonalDataDraft } from '@/features/personal-data/types'

interface PersonalDataSectionProps {
  /** The buffered personal-data draft owned by the parent (always present). */
  value: PersonalDataDraft
  /** Emits the next draft. */
  onChange: (next: PersonalDataDraft) => void
}

/**
 * Reusable, owner-agnostic personal-data section. Controlled/buffered: the parent
 * owns the draft tree and submits it inside the single user payload (ADR 0012) —
 * the section performs no network call. The card is ALWAYS active (no add/remove
 * affordance): the section renders the card form plus the contacts/addresses
 * managers, all wired to the same buffer, in both create and edit.
 */
export function PersonalDataSection({ value, onChange }: PersonalDataSectionProps) {
  const { t } = useTranslation()

  return (
    <section className="flex flex-col gap-3">
      <div>
        <h3 className="text-base font-semibold">
          {t('personalData.section.title')}
        </h3>
        <p className="text-sm text-muted-foreground">
          {t('personalData.section.subtitle')}
        </p>
      </div>

      <PersonalDataCardForm value={value} onChange={onChange} />
      <Separator />
      <ContactsManager
        value={value.contacts}
        onChange={(contacts) => onChange({ ...value, contacts })}
      />
      <Separator />
      <AddressesManager
        value={value.addresses}
        onChange={(addresses) => onChange({ ...value, addresses })}
      />
    </section>
  )
}
