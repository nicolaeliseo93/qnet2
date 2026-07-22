import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import axios, { AxiosError } from 'axios'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { RequestWorkPanelScreen } from '@/features/request-management/request-work-panel'
import type { RequestWorkPanelWithPermissions } from '@/features/request-management/types'

/**
 * Spec 0049 AC-061/062/063: the work panel renders the read-only context, a
 * control per applicable Attribute, the working-state select limited to the
 * resolved set, and mounts `ContactsManager` for both Registry and Referent.
 */

const fetchRequestWorkPanelMock = vi.fn()
const updateRequestWorkMock = vi.fn()
vi.mock('@/features/request-management/api', () => ({
  fetchRequestWorkPanel: (...args: unknown[]) => fetchRequestWorkPanelMock(...args),
  updateRequestWork: (...args: unknown[]) => updateRequestWorkMock(...args),
}))

const createContactMock = vi.fn()
const updateContactMock = vi.fn()
const deleteContactMock = vi.fn()
vi.mock('@/features/personal-data/api', () => ({
  createContact: (...args: unknown[]) => createContactMock(...args),
  updateContact: (...args: unknown[]) => updateContactMock(...args),
  deleteContact: (...args: unknown[]) => deleteContactMock(...args),
}))

// Stubs the inline editor (mirrors `contacts-manager.test.tsx`) so the "add a
// contact" flow can be driven without the config (enum options) query the
// real type select needs: submits a fixed phone contact.
vi.mock('@/features/personal-data/contact-form', () => ({
  ContactForm: ({ onSubmit }: { onSubmit: (fields: Record<string, unknown>) => void }) => (
    <button
      type="button"
      data-testid="stub-submit"
      onClick={() => onSubmit({ type: 'phone', value: '+39 02 1234567', label: null, is_primary: false })}
    >
      stub-save
    </button>
  ),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn(), error: vi.fn() } }))

const FULL_PERMISSIONS = {
  resource: { view: true, create: true, update: true, delete: true, export: true, import: true },
  fields: {},
  actions: {},
}

function panel(overrides: Partial<RequestWorkPanelWithPermissions> = {}): RequestWorkPanelWithPermissions {
  return {
    id: 1,
    name: 'Enterprise deal',
    registry: { id: 10, name: 'Acme S.p.A.' },
    referent: { id: 20, name: 'Mario Rossi' },
    commercial: null,
    opportunity_status: { id: 5, name: 'New', color: 'slate' },
    workflow_status: { id: 100, name: 'Open', color: 'blue', system_key: 'open', description: null, requires_note: false },
    workflow_statuses: [
      { id: 100, name: 'Open', color: 'blue', system_key: 'open', description: null, requires_note: false },
      { id: 101, name: 'In progress', color: 'amber', system_key: null, description: null, requires_note: false },
    ],
    product_lines: [{ id: 1, business_function: { id: 40, name: 'Sales' }, product_category: { id: 500, name: 'Consulting' } }],
    client_contacts: {
      owner: { type: 'personal_data', id: 1000 },
      items: [{ id: 1, type: 'email', label: null, value: 'client@acme.test', is_primary: true }],
    },
    client_address: null,
    referent_contacts: { owner: { type: 'personal_data', id: 2000 }, items: [] },
    applicable_attributes: [
      {
        id: 1,
        code: 'notes',
        name: 'Notes',
        type: 'text',
        description: null,
        help_text: null,
        placeholder: null,
        icon: null,
        config: null,
        relation_target: null,
        is_required: true,
        sort_order: 1,
        options: [],
      },
      {
        id: 2,
        code: 'priority',
        name: 'Priority',
        type: 'enum',
        description: null,
        help_text: null,
        placeholder: null,
        icon: null,
        config: null,
        relation_target: null,
        is_required: false,
        sort_order: 2,
        options: [
          { value: 'low', label: 'Low', color: null },
          { value: 'high', label: 'High', color: null },
        ],
      },
    ],
    attribute_values: { notes: 'Some notes', priority: 'low' },
    next_callback_at: null,
    context: { estimated_value: 1234.5, expected_close_date: '2026-08-01', success_probability: null },
    permissions: FULL_PERMISSIONS,
    ...overrides,
  }
}

function renderPanel(id = 1) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>
        <RequestWorkPanelScreen id={id} />
      </ConfirmDialogProvider>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchRequestWorkPanelMock.mockReset()
  updateRequestWorkMock.mockReset()
  createContactMock.mockReset()
  updateContactMock.mockReset()
  deleteContactMock.mockReset()
})

describe('RequestWorkPanelScreen (spec 0049 AC-061)', () => {
  it('renders the compact context header, the always-active client fields, the dynamic fields and the working state', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(panel())

    renderPanel()

    await waitFor(() => expect(screen.getByText('Enterprise deal')).toBeInTheDocument())

    // Read-only context, now a compact header strip.
    expect(screen.getByText('Acme S.p.A.')).toBeInTheDocument()
    expect(screen.getByText('New')).toBeInTheDocument()
    expect(screen.getByText('Sales — Consulting')).toBeInTheDocument()

    // Anagrafica: the client's channels are ACTIVE, prefilled inputs — not a
    // read-only list behind an "edit" dialog.
    expect(screen.getByLabelText('Email')).toHaveValue('client@acme.test')
    expect(screen.getByLabelText('Phone')).toHaveValue('')
    expect(screen.getByLabelText('PEC')).toBeInTheDocument()
    expect(screen.getByLabelText('Fax')).toBeInTheDocument()
    // ...and so is the address, blank when the client has none yet.
    expect(screen.getByLabelText('Address')).toHaveValue('')

    // One control per applicable attribute, by type.
    expect(screen.getByRole('textbox', { name: 'Notes' })).toHaveValue('Some notes')
    expect(screen.getByRole('combobox', { name: 'Priority' })).toHaveTextContent('Low')

    // Workflow status select, limited to the resolved set.
    expect(screen.getByRole('combobox', { name: 'Working status' })).toHaveTextContent('Open')
  })

  it('sends a contact typed in the inline field with the single save, no per-field persistence', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(panel())
    updateRequestWorkMock.mockResolvedValue(panel())

    renderPanel()

    await waitFor(() => expect(screen.getByLabelText('Email')).toHaveValue('client@acme.test'))

    fireEvent.change(screen.getByLabelText('Phone'), { target: { value: '+39 02 1234567' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateRequestWorkMock).toHaveBeenCalledTimes(1))
    // The whole client set travels in the panel's own PATCH; the per-contact
    // endpoints are never touched from this screen.
    expect(updateRequestWorkMock.mock.calls[0][1]).toEqual({
      client_contacts: [
        { id: 1, type: 'email', value: 'client@acme.test', label: null, is_primary: true },
        { type: 'phone', value: '+39 02 1234567', label: null, is_primary: true },
      ],
    })
    expect(createContactMock).not.toHaveBeenCalled()
  })

  it('sends the client address created inline with the same save', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(panel())
    updateRequestWorkMock.mockResolvedValue(panel())

    renderPanel()

    await waitFor(() => expect(screen.getByLabelText('Address')).toBeInTheDocument())

    fireEvent.change(screen.getByLabelText('Address'), { target: { value: 'Via Roma 1' } })
    fireEvent.change(screen.getByLabelText('Postal code'), { target: { value: '20100' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateRequestWorkMock).toHaveBeenCalledTimes(1))
    expect(updateRequestWorkMock.mock.calls[0][1]).toMatchObject({
      client_address: { line1: 'Via Roma 1', postal_code: '20100' },
    })
  })

  it('shows the load error state with a retry action when the fetch fails', async () => {
    fetchRequestWorkPanelMock.mockRejectedValue(new Error('network down'))

    renderPanel()

    await waitFor(() => expect(screen.getByRole('alert')).toHaveTextContent('Could not load the record.'))
    expect(screen.getByRole('button', { name: 'Retry' })).toBeInTheDocument()
  })
})

describe('RequestWorkPanelScreen — sparse submit (spec 0049 AC-062)', () => {
  it('sends only the changed workflow status', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(panel())
    updateRequestWorkMock.mockResolvedValue(panel({ workflow_status: { id: 101, name: 'In progress', color: 'amber', system_key: null, description: null, requires_note: false } }))

    renderPanel()

    await waitFor(() =>
      expect(screen.getByRole('combobox', { name: 'Working status' })).toHaveTextContent('Open'),
    )

    fireEvent.click(screen.getByRole('combobox', { name: 'Working status' }))
    fireEvent.click(screen.getByRole('option', { name: 'In progress' }))
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateRequestWorkMock).toHaveBeenCalledTimes(1))
    const [id, payload] = updateRequestWorkMock.mock.calls[0]
    expect(id).toBe(1)
    expect(payload).toEqual({ opportunity_workflow_status_id: 101 })
  })

  it('maps a 422 on a dynamic field onto the field with the accessible-error triad', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(panel())
    updateRequestWorkMock.mockRejectedValue(
      new AxiosError('Unprocessable', '422', undefined, undefined, {
        status: 422,
        data: {
          success: false,
          message: 'Validation failed',
          errors: { 'attribute_values.notes': ['Notes is required.'] },
        },
      } as never),
    )
    vi.spyOn(axios, 'isAxiosError').mockReturnValue(true)

    renderPanel()

    await waitFor(() => expect(screen.getByRole('textbox', { name: 'Notes' })).toBeInTheDocument())

    const notesField = screen.getByRole('textbox', { name: 'Notes' })
    fireEvent.change(notesField, { target: { value: 'Updated notes' } })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(screen.getByText('Notes is required.')).toBeInTheDocument())
    const message = screen.getByText('Notes is required.')
    expect(message).toHaveAttribute('role', 'alert')
    expect(notesField).toHaveAttribute('aria-invalid', 'true')
    expect(notesField).toHaveAttribute('aria-describedby', expect.stringContaining(message.id))

    vi.restoreAllMocks()
  })
})

describe('RequestWorkPanelScreen — bounded controls (spec 0049 AC-063)', () => {
  it('limits the workflow status select to the resolved set', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(panel())

    renderPanel()

    await waitFor(() =>
      expect(screen.getByRole('combobox', { name: 'Working status' })).toHaveTextContent('Open'),
    )
    fireEvent.click(screen.getByRole('combobox', { name: 'Working status' }))

    const listbox = screen.getByRole('listbox')
    expect(within(listbox).getAllByRole('option')).toHaveLength(2)
    expect(within(listbox).getByRole('option', { name: 'Open' })).toBeInTheDocument()
    expect(within(listbox).getByRole('option', { name: 'In progress' })).toBeInTheDocument()
  })

  it('limits the enum attribute select to its own options', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(panel())

    renderPanel()

    await waitFor(() => expect(screen.getByRole('combobox', { name: 'Priority' })).toBeInTheDocument())
    fireEvent.click(screen.getByRole('combobox', { name: 'Priority' }))

    const listbox = screen.getByRole('listbox')
    expect(within(listbox).getAllByRole('option')).toHaveLength(2)
    expect(within(listbox).getByRole('option', { name: 'Low' })).toBeInTheDocument()
    expect(within(listbox).getByRole('option', { name: 'High' })).toBeInTheDocument()
  })

  it('flags the required dynamic field in its label', async () => {
    fetchRequestWorkPanelMock.mockResolvedValue(panel())

    renderPanel()

    await waitFor(() =>
      expect(screen.getByRole('textbox', { name: 'Notes' })).toBeInTheDocument(),
    )

    // The collaborative notes section renders its own "Notes" heading, so the
    // bare text query is ambiguous: keep the one that is a field label.
    const label = screen
      .getAllByText('Notes')
      .map((node) => node.closest('label'))
      .find((node): node is HTMLLabelElement => node !== null)

    expect(label).toBeDefined()
    expect(label).toHaveTextContent('*')
  })
})
