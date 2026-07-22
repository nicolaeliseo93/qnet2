import {
  useEffect,
  useId,
  useMemo,
  useRef,
  useState,
  type ChangeEvent,
  type KeyboardEvent,
  type SyntheticEvent,
} from 'react'
import { useTranslation } from 'react-i18next'
import { Textarea } from '@/components/ui/textarea'
import { UserAvatar } from '@/components/user-avatar'
import { Skeleton } from '@/components/ui/skeleton'
import { cn } from '@/lib/utils'
import { useDebouncedValue } from '@/hooks/use-debounced-value'
import { flattenForSelectPages } from '@/features/for-select/use-for-select'
import { useMentionableUsers } from '@/features/notes/use-mentionable-users'
import type { ForSelectItem } from '@/features/for-select/types'

/** Mention token stored in the body (D-12), e.g. `@[Nome Cognome](user:12)`. */
const MENTION_TOKEN_PATTERN = /@\[([^\]]+)\]\(user:(\d+)\)/g

/**
 * Matches an active `@query` right before the caret: an `@` at the start of
 * the text or preceded by whitespace, followed by a run of non-space
 * characters up to the caret. No match = no picker (closed on space/newline
 * or once the caret leaves the token).
 */
const MENTION_TRIGGER_PATTERN = /(?:^|\s)@([^\s@]*)$/

export interface MentionTextareaProps {
  value: string
  /** Emits the updated body and the FULL set of mentioned ids currently present in the body. */
  onChange: (value: string, mentions: number[]) => void
  entityType: string
  entityId: number
  placeholder?: string
  disabled?: boolean
  id?: string
  rows?: number
  autoFocus?: boolean
  'aria-invalid'?: boolean
  'aria-describedby'?: string
}

/** Extracts the deduplicated mention ids from the body's tokens, in order of first appearance. */
function extractMentionIds(body: string): number[] {
  const ids: number[] = []
  const seen = new Set<number>()
  for (const match of body.matchAll(MENTION_TOKEN_PATTERN)) {
    const id = Number(match[2])
    if (!seen.has(id)) {
      seen.add(id)
      ids.push(id)
    }
  }
  return ids
}

/**
 * `Textarea` with an inline `@mention` autocomplete (AC-072), built on the
 * popup-less pattern of `searchable-select.tsx` / `async-paginated-select.tsx`
 * (no `popover`/`cmdk` dependency): a `role="combobox"` textarea drives a
 * `role="listbox"` panel positioned under it via `aria-activedescendant`,
 * rather than a Radix Popover, so arrow keys never leave the field.
 *
 * The candidate list comes from `useMentionableUsers` (D-10, contextual to
 * `entityType`/`entityId`), never `users/for-select`. Selecting a candidate
 * inserts the frozen token format (D-12) and `onChange` always re-derives
 * `mentions` from the tokens actually present in the body, so a token deleted
 * by hand drops out of the array on its own.
 */
export function MentionTextarea({
  value,
  onChange,
  entityType,
  entityId,
  placeholder,
  disabled,
  id,
  rows,
  autoFocus,
  'aria-invalid': ariaInvalid,
  'aria-describedby': ariaDescribedBy,
}: MentionTextareaProps) {
  const { t } = useTranslation()
  const [open, setOpen] = useState(false)
  const [query, setQuery] = useState('')
  const [triggerStart, setTriggerStart] = useState<number | null>(null)
  const [activeIndex, setActiveIndex] = useState(0)
  const listboxId = useId()
  const textareaRef = useRef<HTMLTextAreaElement | null>(null)
  // Caret position to restore once a token insertion's `value` prop round-trips
  // back from the parent (the DOM textarea only reflects the new text on the
  // next render, so the caret can't be set synchronously in the handler).
  const pendingCaretRef = useRef<number | null>(null)

  const debouncedQuery = useDebouncedValue(query)

  const { data, isPending } = useMentionableUsers({
    entityType,
    entityId,
    search: debouncedQuery,
    enabled: open,
  })

  const options = useMemo(() => flattenForSelectPages(data?.pages), [data?.pages])
  const activeOption = options[activeIndex] as ForSelectItem | undefined

  useEffect(() => {
    if (pendingCaretRef.current === null) {
      return
    }
    const caret = pendingCaretRef.current
    pendingCaretRef.current = null
    const textarea = textareaRef.current
    textarea?.focus()
    textarea?.setSelectionRange(caret, caret)
  }, [value])

  function closePicker() {
    setOpen(false)
    setTriggerStart(null)
    setActiveIndex(0)
  }

  /** Recomputes the active `@query` (if any) from the text up to the caret. */
  function updateTrigger(text: string, caret: number | null) {
    if (caret === null) {
      closePicker()
      return
    }
    const match = MENTION_TRIGGER_PATTERN.exec(text.slice(0, caret))
    if (!match) {
      closePicker()
      return
    }
    const nextQuery = match[1]
    // A genuinely new search term restarts highlighting at the top option;
    // set here (an event handler, not an effect) so it lands in the same
    // render pass as the query/open update instead of cascading a second one.
    if (nextQuery !== query) {
      setActiveIndex(0)
    }
    setTriggerStart(caret - nextQuery.length - 1)
    setQuery(nextQuery)
    setOpen(true)
  }

  function handleChange(event: ChangeEvent<HTMLTextAreaElement>) {
    const nextValue = event.target.value
    onChange(nextValue, extractMentionIds(nextValue))
    updateTrigger(nextValue, event.target.selectionStart)
  }

  // Covers caret moves that don't fire `onChange`: clicks and arrow-key
  // navigation outside the picker's own up/down handling below.
  function handleSelect(event: SyntheticEvent<HTMLTextAreaElement>) {
    updateTrigger(event.currentTarget.value, event.currentTarget.selectionStart)
  }

  function selectOption(item: ForSelectItem) {
    const textarea = textareaRef.current
    if (triggerStart === null || !textarea) {
      return
    }
    const caret = textarea.selectionStart ?? triggerStart
    const token = `@[${item.label}](user:${item.id}) `
    const nextValue = `${value.slice(0, triggerStart)}${token}${value.slice(caret)}`
    pendingCaretRef.current = triggerStart + token.length
    onChange(nextValue, extractMentionIds(nextValue))
    closePicker()
  }

  function handleKeyDown(event: KeyboardEvent<HTMLTextAreaElement>) {
    if (!open || options.length === 0) {
      return
    }
    switch (event.key) {
      case 'ArrowDown':
        event.preventDefault()
        setActiveIndex((index) => (index + 1) % options.length)
        break
      case 'ArrowUp':
        event.preventDefault()
        setActiveIndex((index) => (index - 1 + options.length) % options.length)
        break
      case 'Enter':
        event.preventDefault()
        selectOption(options[activeIndex] as ForSelectItem)
        break
      case 'Escape':
        event.preventDefault()
        closePicker()
        break
      case 'Tab':
        // Never trap Tab: close the picker but let focus move on as usual.
        closePicker()
        break
      default:
        break
    }
  }

  const optionId = (userId: number) => `${listboxId}-option-${userId}`

  return (
    <div className="relative">
      <Textarea
        ref={textareaRef}
        id={id}
        value={value}
        onChange={handleChange}
        onSelect={handleSelect}
        onKeyDown={handleKeyDown}
        onBlur={closePicker}
        placeholder={placeholder}
        disabled={disabled}
        rows={rows}
        autoFocus={autoFocus}
        aria-invalid={ariaInvalid}
        aria-describedby={ariaDescribedBy}
        role="combobox"
        aria-autocomplete="list"
        aria-haspopup="listbox"
        aria-expanded={open}
        aria-controls={listboxId}
        aria-activedescendant={open && activeOption ? optionId(activeOption.id) : undefined}
      />

      {open ? (
        <div
          id={listboxId}
          role="listbox"
          aria-label={t('notes.mentionPicker.label', { defaultValue: 'Mentionable users' })}
          className="absolute top-full left-0 z-50 mt-1 max-h-48 w-full min-w-48 overflow-y-auto rounded-md border bg-popover p-1 text-popover-foreground shadow-md"
        >
          {isPending ? (
            <MentionOptionsSkeleton />
          ) : options.length === 0 ? (
            <p className="px-2 py-3 text-center text-xs text-muted-foreground">
              {t('notes.mentionPicker.empty', { defaultValue: 'No matching users' })}
            </p>
          ) : (
            options.map((item, index) => (
              <div
                key={item.id}
                id={optionId(item.id)}
                role="option"
                aria-selected={index === activeIndex}
                onMouseEnter={() => setActiveIndex(index)}
                onClick={() => selectOption(item)}
                className={cn(
                  'flex cursor-pointer items-center gap-2 rounded-sm px-2 py-1 text-xs',
                  index === activeIndex && 'bg-accent',
                )}
              >
                <UserAvatar name={item.label} src={item.avatar_url} size="sm" className="text-[10px]" />
                <span className="flex min-w-0 flex-col">
                  <span className="truncate">{item.label}</span>
                  {item.subtitle ? (
                    <span className="truncate text-[10px] text-muted-foreground">{item.subtitle}</span>
                  ) : null}
                </span>
              </div>
            ))
          )}
        </div>
      ) : null}
    </div>
  )
}

/** Skeleton shaped like a short list of mention candidates. */
function MentionOptionsSkeleton() {
  return (
    <div className="space-y-1 p-1" data-testid="mention-options-skeleton">
      {Array.from({ length: 3 }).map((_, index) => (
        <div key={index} className="flex items-center gap-2 px-2 py-1">
          <Skeleton className="size-6 shrink-0 rounded-full" />
          <Skeleton className="h-3 w-[60%]" />
        </div>
      ))}
    </div>
  )
}
