import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import type {
  Address,
  Contact,
  CreateAddressPayload,
  CreateContactPayload,
  CreatePersonalDataPayload,
  OwnerRef,
  PersonalDataCard,
  UpdateAddressPayload,
  UpdateContactPayload,
  UpdatePersonalDataPayload,
} from '@/features/personal-data/types'

/* -------------------------------------------------------------------------- */
/* Personal-data card                                                          */
/* -------------------------------------------------------------------------- */

/**
 * Loads the single card an owner holds (with its contacts + addresses), or null
 * when the owner has no card yet. Uses the by-owner index endpoint, so the
 * caller never needs to know the card id up front.
 */
export async function fetchCardByOwner(
  owner: OwnerRef,
): Promise<PersonalDataCard | null> {
  const { data } = await apiClient.get<ApiResponse<PersonalDataCard | null>>(
    '/personal-data',
    { params: { personable_type: owner.type, personable_id: owner.id } },
  )
  return data.data
}

/** Creates the card for an owner. */
export async function createCard(
  payload: CreatePersonalDataPayload,
): Promise<PersonalDataCard> {
  const { data } = await apiClient.post<ApiResponse<PersonalDataCard>>(
    '/personal-data',
    payload,
  )
  return data.data
}

/** Updates a card (full replacement of its registry fields). */
export async function updateCard(
  id: number,
  payload: UpdatePersonalDataPayload,
): Promise<PersonalDataCard> {
  const { data } = await apiClient.put<ApiResponse<PersonalDataCard>>(
    `/personal-data/${id}`,
    payload,
  )
  return data.data
}

/** Deletes a card (cascades its contacts + addresses). */
export async function deleteCard(id: number): Promise<void> {
  await apiClient.delete(`/personal-data/${id}`)
}

/* -------------------------------------------------------------------------- */
/* Contacts                                                                    */
/* -------------------------------------------------------------------------- */

export async function createContact(
  payload: CreateContactPayload,
): Promise<Contact> {
  const { data } = await apiClient.post<ApiResponse<Contact>>(
    '/contacts',
    payload,
  )
  return data.data
}

export async function updateContact(
  id: number,
  payload: UpdateContactPayload,
): Promise<Contact> {
  const { data } = await apiClient.put<ApiResponse<Contact>>(
    `/contacts/${id}`,
    payload,
  )
  return data.data
}

export async function deleteContact(id: number): Promise<void> {
  await apiClient.delete(`/contacts/${id}`)
}

/* -------------------------------------------------------------------------- */
/* Addresses                                                                   */
/* -------------------------------------------------------------------------- */

export async function createAddress(
  payload: CreateAddressPayload,
): Promise<Address> {
  const { data } = await apiClient.post<ApiResponse<Address>>(
    '/addresses',
    payload,
  )
  return data.data
}

export async function updateAddress(
  id: number,
  payload: UpdateAddressPayload,
): Promise<Address> {
  const { data } = await apiClient.put<ApiResponse<Address>>(
    `/addresses/${id}`,
    payload,
  )
  return data.data
}

export async function deleteAddress(id: number): Promise<void> {
  await apiClient.delete(`/addresses/${id}`)
}
