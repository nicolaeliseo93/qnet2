/**
 * Conversion between the frozen wire format of a note body (D-12,
 * `@[Nome Cognome](user:12)`) and the readable text the user actually types in
 * the composer (`@Nome Cognome`). The wire format never changes — it is the
 * contract with the API — but it must never reach the screen either.
 */

/** Mention token stored in the body (D-12), e.g. `@[Nome Cognome](user:12)`. */
export const MENTION_TOKEN_PATTERN = /@\[([^\]]+)\]\(user:(\d+)\)/g

export interface MentionRef {
  id: number
  name: string
}

export interface NoteBodySegment {
  key: string
  type: 'text' | 'mention'
  content: string
}

/** Extracts the deduplicated mention ids from a wire body, in order of first appearance. */
export function extractMentionIds(body: string): number[] {
  return parseMentionRefs(body).map((ref) => ref.id)
}

/** Extracts the deduplicated `{id, name}` pairs from a wire body, in order of first appearance. */
export function parseMentionRefs(body: string): MentionRef[] {
  const refs: MentionRef[] = []
  const seen = new Set<number>()
  for (const match of body.matchAll(new RegExp(MENTION_TOKEN_PATTERN))) {
    const id = Number(match[2])
    if (!seen.has(id)) {
      seen.add(id)
      refs.push({ id, name: match[1] as string })
    }
  }
  return refs
}

/** Turns a wire body into the readable text shown in the composer (`@Name`). */
export function toDisplayText(body: string): string {
  return body.replace(new RegExp(MENTION_TOKEN_PATTERN), (_match, name: string) => `@${name}`)
}

/**
 * Rebuilds the wire body from the text the user edited, re-tokenizing every
 * `@Name` still present for the mentions the body carried before the edit.
 * Longest names first, so `@Anna` is never consumed by a shorter `@Ann`; a name
 * the user partially deleted simply stops matching and its id drops out on its
 * own, which is exactly the behaviour expected when a mention is rubbed out.
 */
export function toWireBody(text: string, refs: MentionRef[]): string {
  const ordered = [...refs].sort((a, b) => b.name.length - a.name.length)
  return ordered.reduce(
    (body, ref) => body.replaceAll(`@${ref.name}`, `@[${ref.name}](user:${ref.id})`),
    text,
  )
}

/** Splits a wire body into alternating text/mention segments, in source order. */
export function splitIntoSegments(body: string): NoteBodySegment[] {
  const segments: NoteBodySegment[] = []
  const pattern = new RegExp(MENTION_TOKEN_PATTERN)
  let lastIndex = 0
  let match: RegExpExecArray | null

  while ((match = pattern.exec(body)) !== null) {
    if (match.index > lastIndex) {
      segments.push({ key: `text-${lastIndex}`, type: 'text', content: body.slice(lastIndex, match.index) })
    }
    segments.push({ key: `mention-${match.index}`, type: 'mention', content: match[1] as string })
    lastIndex = match.index + match[0].length
  }
  if (lastIndex < body.length) {
    segments.push({ key: `text-${lastIndex}`, type: 'text', content: body.slice(lastIndex) })
  }

  return segments
}

/** Removes every token of one user from a wire body, collapsing the leftover double spaces. */
export function removeMention(body: string, userId: number): string {
  return body
    .replace(new RegExp(MENTION_TOKEN_PATTERN), (match, _name: string, id: string) =>
      Number(id) === userId ? '' : match,
    )
    .replace(/ {2,}/g, ' ')
    .trimStart()
}
