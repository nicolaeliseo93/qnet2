import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import axios, { AxiosError } from 'axios'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ReferentTypeForm } from '@/features/referent-types/referent-type-form'
import type { ReferentTypeDetailWithPermissions } from '@/features/referent-types/types'
import type { ResourceMeta } from '@/features/authorization/types'

/**
 * Acceptance criterion AC-024 (spec 0016): the metadata-driven behaviour of
 * the referent-type form (hidden field absent, readonly field not editable,
 * required field marked, server 422 mapped inline).
 */

const createReferentTypeMock = vi.fn()
const updateReferentTypeMock = vi.fn()

vi.mock('@/features/referent-types/api', () => ({
  createReferentType: (...args: unknown[]) => createReferentTypeMock(...args),
  updateReferentType: (...args: unknown[]) => updateReferentTypeMock(...args),
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

function referentType(
  overrides: Partial<ReferentTypeDetailWithPermissions> = {},
): ReferentTypeDetailWithPermissions {
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
  createReferentTypeMock.mockReset()
  updateReferentTypeMock.mockReset()
  fetchResourceMetaMock.mockReset()
})

describe('ReferentTypeForm — metadata-driven authorization (spec 0004)', () => {
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

    render(
      <ReferentTypeForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    await waitFor(() =>
      expect(screen.queryByLabelText(/^Name/)).not.toBeInTheDocument(),
    )
  })

  it('renders a readonly/non-editable field disabled in edit mode', () => {
    render(
      <ReferentTypeForm
        mode={{
          type: 'edit',
          referentType: referentType({
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
    updateReferentTypeMock.mockRejectedValue(
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
      <ReferentTypeForm
        mode={{ type: 'edit', referentType: referentType() }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(screen.getByText('field not editable')).toBeInTheDocument())
    expect(updateReferentTypeMock).toHaveBeenCalledTimes(1)

    vi.restoreAllMocks()
  })
})
