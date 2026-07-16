import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { SavedTemplatesMenu } from '@/features/imports/wizard/mapping-template-controls'
import '@/features/imports/wizard/i18n'
import type { ImportMappingTemplate } from '@/features/imports/wizard/types'
import type { User } from '@/features/auth/types'

/**
 * Spec 0035 AC-012: the management popover lists every team-shared mapping
 * template of the domain; only the actor's own expose a delete action, which
 * confirms then calls the DELETE endpoint and refreshes the list.
 */

const listMappingTemplatesMock = vi.fn()
const deleteMappingTemplateMock = vi.fn()

vi.mock('@/features/imports/wizard/api', () => ({
  listMappingTemplates: (...args: unknown[]) => listMappingTemplatesMock(...args),
  deleteMappingTemplate: (...args: unknown[]) => deleteMappingTemplateMock(...args),
}))

const currentUser = vi.fn<() => User | null>()
vi.mock('@/features/auth/use-auth', () => ({
  useAuth: () => ({ user: currentUser() }),
}))

function template(overrides: Partial<ImportMappingTemplate> = {}): ImportMappingTemplate {
  return {
    id: 1,
    name: 'Monthly leads',
    columns: ['Full name', 'Email'],
    column_mapping: { 'Full name': 'full_name', Email: 'email' },
    dedup_strategy: 'update_existing',
    created_by: { id: 1, name: 'Alice' },
    created_at: '2026-07-16T00:00:00Z',
    ...overrides,
  }
}

/**
 * Radix' DropdownMenu trigger opens on `pointerdown`, not `click`, so a plain
 * `fireEvent.click` leaves the panel closed in jsdom (mirrors `filter-views-control.test.tsx`).
 */
function openMenu() {
  fireEvent.pointerDown(screen.getByRole('button', { name: /Saved templates/ }), {
    button: 0,
    ctrlKey: false,
  })
}

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>{children}</ConfirmDialogProvider>
    </QueryClientProvider>
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  listMappingTemplatesMock.mockReset()
  deleteMappingTemplateMock.mockReset()
  currentUser.mockReset()
})

describe('SavedTemplatesMenu', () => {
  it('exposes delete only for the actor\'s own templates, confirms, deletes and refreshes the list', async () => {
    currentUser.mockReturnValue({ id: 1 } as User)
    listMappingTemplatesMock.mockResolvedValueOnce([
      template({ id: 1, name: 'Mine', created_by: { id: 1, name: 'Alice' } }),
      template({ id: 2, name: 'Theirs', created_by: { id: 2, name: 'Bob' } }),
    ])
    deleteMappingTemplateMock.mockResolvedValue(undefined)
    listMappingTemplatesMock.mockResolvedValueOnce([
      template({ id: 2, name: 'Theirs', created_by: { id: 2, name: 'Bob' } }),
    ])

    const Wrapper = wrapper()
    render(
      <Wrapper>
        <SavedTemplatesMenu domain="leads" />
      </Wrapper>,
    )

    openMenu()
    expect(await screen.findByText('Mine')).toBeInTheDocument()
    expect(screen.getByText('Theirs')).toBeInTheDocument()

    // Only the actor's own row exposes the delete action.
    expect(screen.getAllByRole('menuitem', { name: 'Delete template' })).toHaveLength(1)

    fireEvent.click(screen.getByRole('menuitem', { name: 'Delete template' }))
    fireEvent.click(await screen.findByRole('button', { name: 'Confirm' }))

    await waitFor(() => expect(deleteMappingTemplateMock).toHaveBeenCalledWith('leads', 1))
    await waitFor(() => expect(listMappingTemplatesMock).toHaveBeenCalledTimes(2))
  })

  it('shows no delete action for a template created by someone else', async () => {
    currentUser.mockReturnValue({ id: 1 } as User)
    listMappingTemplatesMock.mockResolvedValue([
      template({ id: 2, name: 'Theirs', created_by: { id: 2, name: 'Bob' } }),
    ])

    const Wrapper = wrapper()
    render(
      <Wrapper>
        <SavedTemplatesMenu domain="leads" />
      </Wrapper>,
    )

    openMenu()
    expect(await screen.findByText('Theirs')).toBeInTheDocument()
    expect(screen.queryByRole('menuitem', { name: 'Delete template' })).not.toBeInTheDocument()
  })
})
