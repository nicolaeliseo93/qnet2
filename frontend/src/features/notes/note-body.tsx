import { Fragment } from 'react'

/** Matches a mention token `@[Name Surname](user:12)` (D-12). */
const MENTION_TOKEN_PATTERN = /@\[([^\]]+)\]\(user:(\d+)\)/g

interface NoteBodySegment {
  key: string
  type: 'text' | 'mention'
  content: string
}

export interface NoteBodyProps {
  /** Raw note body, with mention tokens still inline (D-12). */
  body: string
}

/**
 * Renders a note's raw body as safe React nodes: plain text runs plus
 * highlighted mention chips. Security-critical (react-security.md): never
 * `dangerouslySetInnerHTML` on this untrusted, user-authored text — the body
 * is split into segments and each one rendered as a plain text node or a
 * `<span>` chip, so React's own escaping applies throughout.
 */
export function NoteBody({ body }: NoteBodyProps) {
  const segments = splitIntoSegments(body)

  return (
    <p className="whitespace-pre-wrap break-words text-sm text-foreground">
      {segments.map((segment) =>
        segment.type === 'mention' ? (
          <span
            key={segment.key}
            className="rounded bg-primary/10 px-1 py-0.5 font-medium text-primary"
          >
            {`@${segment.content}`}
          </span>
        ) : (
          <Fragment key={segment.key}>{segment.content}</Fragment>
        ),
      )}
    </p>
  )
}

/** Splits a raw body into alternating text/mention segments, in source order. */
function splitIntoSegments(body: string): NoteBodySegment[] {
  const segments: NoteBodySegment[] = []
  const pattern = new RegExp(MENTION_TOKEN_PATTERN)
  let lastIndex = 0
  let match: RegExpExecArray | null

  while ((match = pattern.exec(body)) !== null) {
    if (match.index > lastIndex) {
      segments.push({ key: `text-${lastIndex}`, type: 'text', content: body.slice(lastIndex, match.index) })
    }
    segments.push({ key: `mention-${match.index}`, type: 'mention', content: match[1] })
    lastIndex = match.index + match[0].length
  }
  if (lastIndex < body.length) {
    segments.push({ key: `text-${lastIndex}`, type: 'text', content: body.slice(lastIndex) })
  }

  return segments
}
