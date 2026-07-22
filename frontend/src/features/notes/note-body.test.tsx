import { describe, expect, it } from 'vitest'
import { render, screen } from '@testing-library/react'
import { NoteBody } from '@/features/notes/note-body'

/**
 * Spec 0052 D-12 (token -> chip) / react-security.md (never
 * `dangerouslySetInnerHTML` on untrusted, user-authored text).
 */
describe('NoteBody', () => {
  it('renders plain text and mention chips at the right positions, with the literal text intact', () => {
    render(<NoteBody body="Hey @[Mario Rossi](user:12), can you check this?" />)

    const paragraph = screen.getByText(/Hey/)
    expect(paragraph).toHaveTextContent('Hey @Mario Rossi, can you check this?')
    expect(screen.getByText('@Mario Rossi')).toBeInTheDocument()
  })

  it('renders a body with multiple tokens interleaved with text, each chip separate', () => {
    render(<NoteBody body="cc @[Mario Rossi](user:12) and @[Anna Bianchi](user:7) please" />)

    expect(screen.getByText('@Mario Rossi')).toBeInTheDocument()
    expect(screen.getByText('@Anna Bianchi')).toBeInTheDocument()
    expect(screen.getByText(/cc/)).toHaveTextContent('cc @Mario Rossi and @Anna Bianchi please')
  })

  it('preserves line breaks', () => {
    render(<NoteBody body={'First line\nSecond line'} />)

    const paragraph = screen.getByText(/First line/)
    expect(paragraph).toHaveClass('whitespace-pre-wrap')
    expect(paragraph.textContent).toBe('First line\nSecond line')
  })

  it('never injects markup: an <img onerror> body renders as literal text, no DOM element is created', () => {
    const payload = '<img src=x onerror=alert(1)>'
    const { container } = render(<NoteBody body={payload} />)

    expect(container.querySelector('img')).not.toBeInTheDocument()
    expect(screen.getByText(payload)).toBeInTheDocument()
  })

  it('never injects markup: a <script> body renders as literal text, no script element is created', () => {
    const payload = '<script>alert(1)</script>'
    const { container } = render(<NoteBody body={payload} />)

    expect(container.querySelector('script')).not.toBeInTheDocument()
    expect(screen.getByText(payload)).toBeInTheDocument()
  })

  it('does not use dangerouslySetInnerHTML on the body', () => {
    // Belt-and-braces static check alongside the behavioural ones above: the
    // rendered paragraph must own its own text nodes, not innerHTML.
    const { container } = render(<NoteBody body="<b>bold?</b> no." />)
    const paragraph = container.querySelector('p')
    expect(paragraph?.innerHTML).not.toContain('<b>')
    expect(screen.getByText('<b>bold?</b> no.')).toBeInTheDocument()
  })

  it('does not crash on a malformed token (non-numeric id) and renders it as literal text', () => {
    expect(() => render(<NoteBody body="Hey @[Nome](user:abc) there" />)).not.toThrow()
    expect(screen.getByText('Hey @[Nome](user:abc) there')).toBeInTheDocument()
    expect(screen.queryByText('@Nome')).not.toBeInTheDocument()
  })

  it('does not crash on an empty body', () => {
    expect(() => render(<NoteBody body="" />)).not.toThrow()
  })
})
