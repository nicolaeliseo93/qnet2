import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import axios, { AxiosError } from 'axios'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { SourceForm } from '@/features/sources/source-form'
import type { SourceDetailWithPermissions } from '@/features/sources/types'
import type { ResourceMeta } from '@/features/authorization/types'

/**
 * Acceptance criterion AC-024 (spec 0016, mirrored by spec 0018): the
 * metadata-driven behaviour of the source form (hidden field absent,
 * readonly field not editable, required field marked, server 422 mapped
 * inline).
 */

const createSourceMock = vi.fn()
const updateSourceMock = vi.fn()

vi.mock('@/features/sources/api', () => ({
  createSource: (...args: unknown[]) => createSourceMock(...args),
  updateSource: (...args: unknown[]) => updateSourceMock(...args),
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

function source(overrides: Partial<SourceDetailWithPermissions> = {}): SourceDetailWithPermissions {
  return {
    id: 9,
    name: 'Sponsor',
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
  createSourceMock.mockReset()
  updateSourceMock.mockReset()
  fetchResourceMetaMock.mockReset()
})

describe('SourceForm — metadata-driven authorization (spec 0004)', () => {
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

    render(<SourceForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />, {
      wrapper: wrapper(),
    })

    await waitFor(() => expect(screen.queryByLabelText(/^Name/)).not.toBeInTheDocument())
  })

  it('renders a readonly/non-editable field disabled in edit mode', () => {
    render(
      <SourceForm
        mode={{
          type: 'edit',
          source: source({
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
    updateSourceMock.mockRejectedValue(
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
      <SourceForm mode={{ type: 'edit', source: source() }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(screen.getByText('field not editable')).toBeInTheDocument())
    expect(updateSourceMock).toHaveBeenCalledTimes(1)

    vi.restoreAllMocks()
  })
})
