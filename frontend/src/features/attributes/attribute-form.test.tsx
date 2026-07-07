import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { AttributeForm } from '@/features/attributes/attribute-form'
import type { AttributeDetailWithPermissions } from '@/features/attributes/types'
import type { ResourcePermissions } from '@/features/authorization/types'

const createAttributeMock = vi.fn()
const updateAttributeMock = vi.fn()

vi.mock('@/features/attributes/api', () => ({
  createAttribute: (...args: unknown[]) => createAttributeMock(...args),
  updateAttribute: (...args: unknown[]) => updateAttributeMock(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn() } }))

/** Isolates the form from the real `GET /config` fetch behind `useEnumOptions`. */
vi.mock('@/features/config/use-config', () => ({
  useEnumOptions: () => [
    { value: 'STRING', label: 'Text', color: null, icon: null, is_default: true, hidden_on_form: false },
    { value: 'INTEGER', label: 'Integer number', color: null, icon: null, is_default: false, hidden_on_form: false },
    { value: 'DECIMAL', label: 'Decimal number', color: null, icon: null, is_default: false, hidden_on_form: false },
    { value: 'BOOLEAN', label: 'Yes/No', color: null, icon: null, is_default: false, hidden_on_form: false },
    { value: 'ENUM', label: 'List of options', color: null, icon: null, is_default: false, hidden_on_form: false },
  ],
}))

const FULL_ACCESS_PERMISSIONS: ResourcePermissions = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

vi.mock('@/features/attributes/use-attribute-form-meta', () => ({
  useAttributeFormMeta: () => ({ status: 'ready', permissions: FULL_ACCESS_PERMISSIONS }),
}))

function wrapper() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return ({ children }: { children: ReactNode }) => (
    <QueryClientProvider client={client}>{children}</QueryClientProvider>
  )
}

function attribute(
  overrides: Partial<AttributeDetailWithPermissions> = {},
): AttributeDetailWithPermissions {
  return {
    id: 3,
    code: 'color',
    name: 'Color',
    data_type: 'ENUM',
    options: [
      { id: 1, value: 'red', label: 'Red', sort_order: 0 },
      { id: 2, value: 'blue', label: 'Blue', sort_order: 1 },
    ],
    created_at: '2026-01-01T00:00:00Z',
    permissions: FULL_ACCESS_PERMISSIONS,
    ...overrides,
  }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createAttributeMock.mockReset()
  updateAttributeMock.mockReset()
})

describe('AttributeForm — ENUM-conditional options editor (AC-021)', () => {
  it('hides the options editor when data_type is not ENUM', () => {
    render(
      <AttributeForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    expect(screen.queryByRole('button', { name: 'Add option' })).not.toBeInTheDocument()
  })

  it('shows the options editor once ENUM is selected', () => {
    render(
      <AttributeForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.click(screen.getByRole('combobox', { name: 'Data type' }))
    fireEvent.click(screen.getByRole('option', { name: 'List of options' }))

    expect(screen.getByRole('button', { name: 'Add option' })).toBeInTheDocument()
  })

  it('submits the create payload with options only for an ENUM attribute', async () => {
    createAttributeMock.mockResolvedValue(attribute())

    render(
      <AttributeForm mode={{ type: 'create' }} onSuccess={vi.fn()} onCancel={vi.fn()} />,
      { wrapper: wrapper() },
    )

    fireEvent.change(screen.getByLabelText(/^Code/), { target: { value: 'color' } })
    fireEvent.change(screen.getByLabelText(/^Name/), { target: { value: 'Color' } })
    fireEvent.click(screen.getByRole('combobox', { name: 'Data type' }))
    fireEvent.click(screen.getByRole('option', { name: 'List of options' }))
    fireEvent.click(screen.getByRole('button', { name: 'Add option' }))
    fireEvent.change(screen.getByLabelText('Value'), { target: { value: 'red' } })
    fireEvent.change(screen.getByLabelText('Label'), { target: { value: 'Red' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createAttributeMock).toHaveBeenCalledTimes(1))
    expect(createAttributeMock).toHaveBeenCalledWith({
      code: 'color',
      name: 'Color',
      data_type: 'ENUM',
      options: [{ value: 'red', label: 'Red', sort_order: 0 }],
    })
  })

  it('hydrates the ENUM options in edit mode', () => {
    render(
      <AttributeForm
        mode={{ type: 'edit', attribute: attribute() }}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
      { wrapper: wrapper() },
    )

    expect(screen.getAllByLabelText('Value').map((input) => (input as HTMLInputElement).value)).toEqual([
      'red',
      'blue',
    ])
  })
})
