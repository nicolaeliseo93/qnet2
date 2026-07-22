import { useMutation, useQueryClient } from '@tanstack/react-query'
import type { AxiosError } from 'axios'
import type { ApiErrorResponse } from '@/api/types'
import { createNote, deleteNote, updateNote } from '@/features/notes/api'
import { notesKeys } from '@/features/notes/query-keys'
import type { CreateNotePayload, Note, UpdateNotePayload } from '@/features/notes/types'

/**
 * Write side of a host record's notes thread (spec 0052). Each mutation
 * invalidates that record's root list on success, so `useNotes` refetches and
 * the UI updates without a manual reload (AC-071). The error generic is
 * `AxiosError<ApiErrorResponse>` so a 422 caught by the caller can be mapped
 * field-by-field with `applyServerValidationErrors` (`@/features/auth/form-errors`)
 * onto the composer's RHF fields for the accessible error triad (AC-073).
 */
export function useCreateNote(entityType: string, entityId: number) {
  const queryClient = useQueryClient()

  return useMutation<Note, AxiosError<ApiErrorResponse>, CreateNotePayload>({
    mutationFn: (payload) => createNote(payload),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: notesKeys.list(entityType, entityId) })
    },
  })
}

interface UpdateNoteVariables {
  noteId: number
  payload: UpdateNotePayload
}

export function useUpdateNote(entityType: string, entityId: number) {
  const queryClient = useQueryClient()

  return useMutation<Note, AxiosError<ApiErrorResponse>, UpdateNoteVariables>({
    mutationFn: ({ noteId, payload }) => updateNote(noteId, payload),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: notesKeys.list(entityType, entityId) })
    },
  })
}

export function useDeleteNote(entityType: string, entityId: number) {
  const queryClient = useQueryClient()

  return useMutation<void, AxiosError<ApiErrorResponse>, number>({
    mutationFn: (noteId) => deleteNote(noteId),
    onSuccess: () => {
      void queryClient.invalidateQueries({ queryKey: notesKeys.list(entityType, entityId) })
    },
  })
}
