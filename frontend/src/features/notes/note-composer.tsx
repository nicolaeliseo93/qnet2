import { useState } from 'react'
import { useForm, useWatch } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import type { TFunction } from 'i18next'
import { Loader2, Send } from 'lucide-react'
import { z } from 'zod'
import { Button } from '@/components/ui/button'
import { Form, FormControl, FormField, FormItem, FormMessage } from '@/components/ui/form'
import { applyServerValidationErrors } from '@/features/auth/form-errors'
import { MentionTextarea } from '@/features/notes/mention-textarea'
import { useCreateNote, useUpdateNote } from '@/features/notes/use-note-mutations'
import type { Note } from '@/features/notes/types'

/** Mirrors the server-side `body: string (1..5000)` rule (D-12/data_contract). */
const BODY_MAX_LENGTH = 5000

/** Below this many characters left the hint gives way to a countdown. */
const CHARACTER_COUNTER_THRESHOLD = 500

function buildNoteComposerSchema(t: TFunction) {
  return z.object({
    body: z
      .string()
      .trim()
      .min(1, t('notes.composer.bodyRequired', { defaultValue: 'Scrivi qualcosa prima di inviare.' }))
      .max(
        BODY_MAX_LENGTH,
        t('notes.composer.bodyTooLong', {
          defaultValue: `La nota puo' contenere al massimo ${BODY_MAX_LENGTH} caratteri.`,
          count: BODY_MAX_LENGTH,
        }),
      ),
  })
}

type NoteComposerFormValues = z.infer<ReturnType<typeof buildNoteComposerSchema>>

export interface NoteComposerProps {
  entityType: string
  entityId: number
  /** Root note id to reply under. Omit to compose a new root note. */
  parentId?: number
  /** Note being edited; when set the composer is pre-filled and PATCHes on submit. */
  editingNote?: Note
  /** Called after a successful reply/edit submit, so the caller closes the inline composer. */
  onDone?: () => void
  /** Cancels an inline reply/edit composer. The root composer has none. */
  onCancel?: () => void
  autoFocus?: boolean
}

/**
 * Single write surface for the three note-authoring flows (new root, reply,
 * edit — data_contract `POST/PATCH /api/notes`): a `MentionTextarea` plus a
 * submit button. `mentions` is tracked separately from the RHF-validated
 * `body` field because it is derived from the body's tokens by
 * `MentionTextarea` itself (D-12), not something the user types directly.
 */
export function NoteComposer({
  entityType,
  entityId,
  parentId,
  editingNote,
  onDone,
  onCancel,
  autoFocus,
}: NoteComposerProps) {
  const { t } = useTranslation()
  const [mentions, setMentions] = useState<number[]>(
    () => editingNote?.mentions.map((mention) => mention.id) ?? [],
  )
  const createNote = useCreateNote(entityType, entityId)
  const updateNote = useUpdateNote(entityType, entityId)
  const pending = createNote.isPending || updateNote.isPending

  const form = useForm<NoteComposerFormValues>({
    resolver: zodResolver(buildNoteComposerSchema(t)),
    defaultValues: { body: editingNote?.body ?? '' },
  })

  const handleSubmit = form.handleSubmit(async (values) => {
    // Step 1: dispatch create or update depending on the composer's mode
    // Step 2: on success, reset the draft (root only) and notify the caller
    // Step 3: on a 422, map it onto the body field with the accessible triad
    try {
      if (editingNote) {
        await updateNote.mutateAsync({
          noteId: editingNote.id,
          payload: { body: values.body, mentions },
        })
      } else {
        await createNote.mutateAsync({
          entity_type: entityType,
          entity_id: entityId,
          body: values.body,
          parent_id: parentId,
          mentions,
        })
        form.reset({ body: '' })
        setMentions([])
      }
      onDone?.()
    } catch (error) {
      const handled = applyServerValidationErrors(error, form.setError, ['body'])
      if (!handled) {
        form.setError('body', {
          message: t('notes.composer.genericError', { defaultValue: "Invio non riuscito. Riprova." }),
        })
      }
    }
  })

  const bodyValue = useWatch({ control: form.control, name: 'body' })
  const remainingCharacters = BODY_MAX_LENGTH - bodyValue.length

  return (
    <Form {...form}>
      <form onSubmit={handleSubmit} className="flex flex-col gap-2">
        <FormField
          control={form.control}
          name="body"
          render={({ field }) => (
            <FormItem>
              <FormControl>
                <MentionTextarea
                  value={field.value}
                  onChange={(value, nextMentions) => {
                    field.onChange(value)
                    setMentions(nextMentions)
                  }}
                  entityType={entityType}
                  entityId={entityId}
                  placeholder={t('notes.composer.placeholder', {
                    defaultValue: 'Scrivi una nota, usa @ per menzionare un collega…',
                  })}
                  disabled={pending}
                  rows={editingNote || parentId ? 2 : 3}
                  autoFocus={autoFocus}
                />
              </FormControl>
              <FormMessage />
            </FormItem>
          )}
        />
        <div className="flex flex-wrap items-center justify-end gap-2">
          <p className="mr-auto text-[11px] text-muted-foreground">
            {remainingCharacters <= CHARACTER_COUNTER_THRESHOLD
              ? t('notes.composer.charactersLeft', {
                  defaultValue: '{{count}} caratteri rimasti',
                  count: remainingCharacters,
                })
              : t('notes.composer.hint', {
                  defaultValue: 'Digita @ per menzionare un collega',
                })}
          </p>
          {onCancel ? (
            <Button type="button" variant="ghost" size="sm" onClick={onCancel} disabled={pending}>
              {t('common.cancel')}
            </Button>
          ) : null}
          <Button type="submit" size="sm" disabled={pending || bodyValue.trim().length === 0}>
            {pending ? (
              <Loader2 className="size-3.5 animate-spin" aria-hidden="true" />
            ) : (
              <Send className="size-3.5" aria-hidden="true" />
            )}
            {editingNote
              ? t('notes.composer.save', { defaultValue: 'Salva' })
              : t('notes.composer.send', { defaultValue: 'Invia' })}
          </Button>
        </div>
      </form>
    </Form>
  )
}
