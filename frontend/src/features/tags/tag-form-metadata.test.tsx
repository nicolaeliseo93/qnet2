import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import axios, { AxiosError } from 'axios'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { TagForm } from '@/features/tags/tag-form'
import type { TagDetailWithPermissions } from '@/features/tags/types'
import type { ResourceMeta } from '@/features/authorization/types'

/**
 * Metadata-driven behaviour of the tag form (spec 0004, mirrors
 * `referent-type-form-metadata.test.tsx`): hidden field absent, readonly
 * field not editable, required field marked, server 422 mapped inline.
 */

const createTagMock = vi.fn()
const updateTagMock = vi.fn()

vi.mock('@/features/tags/api', () => ({
  createTag: (...args: unknown[]) => createTagMock(...args),
  updateTag: (...args: unknown[]) => updateTagMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn() } }))

const fetchResourceMetaMock = vi.fn<() => Promise<ResourceMeta>>()
vi.mock('@/features/authorization/api', () => ({
  fetchResourceMeta: () => fetchResourceMetaMock(),
}))

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function tag(overrides: Partial<TagDetailWithPermissions> = {}): TagDetailWithPermissions {
  return {
    id: 9,
    name: 'VIP',
    created_at: null as unknown as string,
    permissions: {
      resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
      fields: {},
      actions: {},
    },
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createTagMock.mockReset()
  updateTagMock.mockReset()
  fetchResourceMetaMock.mockReset()
})

describe('TagForm — metadata-driven authorization (spec 0004)', () => {
  it('hides a hidden field and marks a required field from create-context metadata', async () => {
    fetchResourceMetaMock.mockResolvedValue({
      fields: [],
      permissions: {
        resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
        fields: {
          name: {
            visible: false, hidden: true, editable: false, readonly: false, required: false, disabled: false,
          },
        },
        actions: {},
      },
    })

    render(<TagForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.queryByLabelText(/^Name/)).not.toBeInTheDocument())
  })

  it('renders a readonly/non-editable field disabled in edit mode', () => {
    render(
      <TagForm
        mode={{
          type: 'edit',
          tag: tag({
            permissions: {
              resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
              fields: {
                name: {
                  visible: true, hidden: false, editable: false, readonly: true, required: true, disabled: false,
                },
              },
              actions: {},
            },
          }),
        }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    const name = screen.getByLabelText(/^Name/)
    expect(name).toBeDisabled()
    expect(name).toHaveAttribute('readonly')
    expect(screen.getByText('Name').closest('label')?.textContent).toContain('*')
  })

  it('seeds permissions from the loaded detail and surfaces a 422 field error inline', async () => {
    updateTagMock.mockRejectedValue(
      new AxiosError(
        'Unprocessable',
        '422',
        undefined,
        undefined,
        {
          status: 422,
          data: { success: false, message: 'Validation failed', errors: { name: ['field not editable'] } },
        } as never,
      ),
    )
    vi.spyOn(axios, 'isAxiosError').mockReturnValue(true)

    render(
      <TagForm mode={{ type: 'edit', tag: tag() }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(screen.getByText('field not editable')).toBeInTheDocument())
    expect(updateTagMock).toHaveBeenCalledTimes(1)

    vi.restoreAllMocks()
  })
})
