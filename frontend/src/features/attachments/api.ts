import { apiClient } from '@/api/client'
import type { ApiResponse } from '@/api/types'
import type { Attachment } from '@/features/attachments/types'

/**
 * TanStack Query key for an attachable owner's document list, scoped by
 * collection so different collections on the same owner never collide.
 */
export function attachmentsQueryKey(resource: string, id: number, collection: string) {
  return ['attachments', resource, id, collection] as const
}

/**
 * Lists the attachments of a polymorphic owner (`resource` is the
 * `attachable_type` alias, e.g. `'opportunity'`), optionally narrowed to a
 * named collection.
 */
export async function listAttachments(
  resource: string,
  id: number,
  collection: string,
): Promise<Attachment[]> {
  const { data } = await apiClient.get<ApiResponse<Attachment[]>>('/attachments', {
    params: { attachable_type: resource, attachable_id: id, collection },
  })
  return data.data
}

export interface UploadAttachmentPayload {
  resource: string
  id: number
  collection: string
  file: File
}

/**
 * Uploads a file to a polymorphic owner's collection.
 *
 * Multipart body: axios infers the `multipart/form-data` boundary from the
 * `FormData` instance, so no `Content-Type` is set here (never force one).
 */
export async function uploadAttachment({
  resource,
  id,
  collection,
  file,
}: UploadAttachmentPayload): Promise<Attachment> {
  const formData = new FormData()
  formData.append('file', file)
  formData.append('collection', collection)
  formData.append('attachable_type', resource)
  formData.append('attachable_id', String(id))

  const { data } = await apiClient.post<ApiResponse<Attachment>>('/attachments', formData)
  return data.data
}

/** Deletes an attachment (metadata + binary) by id. */
export async function deleteAttachment(id: number): Promise<void> {
  await apiClient.delete(`/attachments/${id}`)
}
