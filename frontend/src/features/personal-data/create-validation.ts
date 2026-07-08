/**
 * Save-gate for the quick-create UX (`createMode`): `ContactsManager` and
 * `AddressesManager` are fully controlled and never block typing, so an
 * invalid buffer has to be caught once, right before the parent form submits
 * (mirrors the existing `profileValid` gate on the anagraphic card).
 */
import type { TFunction } from 'i18next'
import { buildContactSchema } from '@/features/personal-data/contact-schema'
import type { AddressDraft, ContactDraft } from '@/features/personal-data/types'

/** Optional until touched: an empty buffer is valid; a started address needs `line1` and a city. */
export function isCreateAddressValid(addresses: AddressDraft[]): boolean {
  return addresses.every((address) => Boolean(address.line1) && address.city_id != null)
}

/** Every buffered contact must validate against the same per-type rules as the dialog form. */
export function areCreateContactsValid(contacts: ContactDraft[], t: TFunction): boolean {
  const schema = buildContactSchema(t)
  return contacts.every(
    (contact) =>
      schema.safeParse({
        type: contact.type,
        value: contact.value,
        label: contact.label ?? '',
        is_primary: contact.is_primary,
      }).success,
  )
}
