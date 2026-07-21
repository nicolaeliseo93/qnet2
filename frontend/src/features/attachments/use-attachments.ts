import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import {
  attachmentsQueryKey,
  deleteAttachment,
  listAttachments,
  uploadAttachment,
} from '@/features/attachments/api'
import type { Attachment } from '@/features/attachments/types'

/** Stable empty array: avoids a new reference (and a rerender trigger) while the list query has no data yet. */
const EMPTY_DOCUMENTS: Attachment[] = []

/**
 * Self-fetching document list for a polymorphic owner (`resource`/`id`),
 * scoped to a single `collection`. Upload and delete invalidate the same
 * list query key on success, so the grid stays in sync without any manual
 * cache bookkeeping.
 */
export function useAttachments(resource: string, id: number, collection: string) {
  const queryClient = useQueryClient()
  const queryKey = attachmentsQueryKey(resource, id, collection)

  const listQuery = useQuery<Attachment[], AxiosError>({
    queryKey,
    queryFn: () => listAttachments(resource, id, collection),
  })

  const uploadMutation = useMutation<Attachment, AxiosError, File>({
    mutationFn: (file) => uploadAttachment({ resource, id, collection, file }),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey })
    },
  })

  const deleteMutation = useMutation<void, AxiosError, number>({
    mutationFn: (attachmentId) => deleteAttachment(attachmentId),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey })
    },
  })

  return {
    documents: listQuery.data ?? EMPTY_DOCUMENTS,
    isLoading: listQuery.isLoading,
    isError: listQuery.isError,
    refetch: listQuery.refetch,
    upload: uploadMutation.mutateAsync,
    isUploading: uploadMutation.isPending,
    remove: deleteMutation.mutateAsync,
  }
}
