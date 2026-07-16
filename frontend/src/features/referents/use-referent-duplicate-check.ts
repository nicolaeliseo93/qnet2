import { useMemo } from 'react'
import { useQuery } from '@tanstack/react-query'
import { useDebouncedValue } from '@/hooks/use-debounced-value'
import {
  checkReferentDuplicates,
  referentDuplicateCheckQueryKey,
  type ReferentDuplicateContact,
  type ReferentDuplicateContactType,
  type ReferentDuplicateMatch,
} from '@/features/referents/duplicate-check-api'
import type { ContactDraft, PersonalDataDraft } from '@/features/personal-data/types'
import type { ReferentFormMode } from '@/features/referents/types'

/** Contact types the check matches on — the rest of the type enum (pec, fax, website…) is out of scope (spec 0037). */
const MATCHED_CONTACT_TYPES = new Set<string>(['email', 'phone', 'mobile'])

interface UseReferentDuplicateCheckArgs {
  mode: ReferentFormMode
  /** The buffered anagraphic draft the form is editing (`use-referent-form.ts`). */
  profileDraft: PersonalDataDraft
}

interface UseReferentDuplicateCheckResult {
  matches: ReferentDuplicateMatch[]
}

/** Non-empty, trimmed email/phone/mobile contacts from the draft, shaped for the wire. */
function relevantContacts(contacts: ContactDraft[]): ReferentDuplicateContact[] {
  return contacts
    .filter((contact) => MATCHED_CONTACT_TYPES.has(contact.type) && contact.value.trim().length > 0)
    .map((contact) => ({
      type: contact.type as ReferentDuplicateContactType,
      value: contact.value.trim(),
    }))
}

/**
 * Debounced, non-blocking duplicate check (spec 0037): watches the buffered
 * tax_code + email/phone/mobile contacts of the CREATE form and asks the
 * backend for existing referents that already carry the same values. Never
 * runs in edit mode (out of scope) and never fires while every criterion is
 * empty (AC-008) — both are baked into `enabled`, not just hidden in the UI.
 */
export function useReferentDuplicateCheck({
  mode,
  profileDraft,
}: UseReferentDuplicateCheckArgs): UseReferentDuplicateCheckResult {
  const isCreate = mode.type === 'create'

  const taxCode = profileDraft.tax_code?.trim() ?? ''
  const contacts = useMemo(
    () => relevantContacts(profileDraft.contacts),
    [profileDraft.contacts],
  )

  const debouncedTaxCode = useDebouncedValue(taxCode)
  const debouncedContacts = useDebouncedValue(contacts)

  const hasCriteria = debouncedTaxCode.length > 0 || debouncedContacts.length > 0

  const query = useQuery({
    queryKey: referentDuplicateCheckQueryKey(debouncedTaxCode, debouncedContacts),
    queryFn: () =>
      checkReferentDuplicates({
        tax_code: debouncedTaxCode || undefined,
        contacts: debouncedContacts.length > 0 ? debouncedContacts : undefined,
      }),
    enabled: isCreate && hasCriteria,
  })

  return { matches: isCreate ? (query.data?.matches ?? []) : [] }
}
