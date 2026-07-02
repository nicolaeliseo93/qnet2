import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import { RoleForm } from '@/features/roles/role-form'
import type { RoleDetail } from '@/features/roles/types'

const createRole = vi.fn()
const updateRole = vi.fn()

vi.mock('@/features/roles/api', () => ({
  createRole: (...args: unknown[]) => createRole(...args),
  updateRole: (...args: unknown[]) => updateRole(...args),
}))

vi.mock('sonner', () => ({ toast: { success: vi.fn() } }))

// Replace the async users multi-select with a lightweight controllable stub so
// the integration test focuses on the form's payload/diffing logic, not the
// network-backed select (covered by its own test).
vi.mock('@/components/ui/async-paginated-multi-select', () => ({
  AsyncPaginatedMultiSelect: ({
    value,
    onChange,
  }: {
    value: number[]
    onChange: (value: number[]) => void
  }) => (
    <div>
      <span data-testid="users-value">{value.join(',')}</span>
      <button type="button" onClick={() => onChange([...value, 7])}>
        add-user-7
      </button>
      <button type="button" onClick={() => onChange([])}>
        clear-users
      </button>
    </div>
  ),
}))

const PERMISSIONS = ['users.viewAny', 'users.create']

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  createRole.mockReset()
  updateRole.mockReset()
})

function editRole(overrides: Partial<RoleDetail> = {}): RoleDetail {
  return {
    id: 3,
    name: 'Editor',
    permissions: ['users.viewAny'],
    created_at: null,
    ...overrides,
  }
}

describe('RoleForm — users integration', () => {
  it('submits the selected users when creating a role', async () => {
    createRole.mockResolvedValue(editRole())
    const onSuccess = vi.fn()

    render(
      <RoleForm
        mode={{ type: 'create' }}
        permissionOptions={PERMISSIONS}
        onSuccess={onSuccess}
        onCancel={vi.fn()}
      />,
    )

    fireEvent.change(screen.getByLabelText(/^Name/), {
      target: { value: 'Support' },
    })
    fireEvent.click(screen.getByText('add-user-7'))
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(createRole).toHaveBeenCalled())
    expect(createRole).toHaveBeenCalledWith(
      expect.objectContaining({ name: 'Support', users: [7] }),
    )
    await waitFor(() => expect(onSuccess).toHaveBeenCalled())
  })

  it('hydrates current members in edit mode from role.users', () => {
    render(
      <RoleForm
        mode={{ type: 'edit', role: editRole({ users: [11, 22] }) }}
        permissionOptions={PERMISSIONS}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
    )
    expect(screen.getByTestId('users-value')).toHaveTextContent('11,22')
  })

  it('omits users from the PATCH payload when membership is unchanged', async () => {
    updateRole.mockResolvedValue(editRole({ users: [11] }))

    render(
      <RoleForm
        mode={{ type: 'edit', role: editRole({ users: [11] }) }}
        permissionOptions={PERMISSIONS}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
    )

    // Change only the name; leave members as the hydrated [11].
    fireEvent.change(screen.getByLabelText(/^Name/), {
      target: { value: 'Editor 2' },
    })
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateRole).toHaveBeenCalled())
    const [, payload] = updateRole.mock.calls[0]
    expect(payload).toEqual({ name: 'Editor 2' })
    expect('users' in payload).toBe(false)
  })

  it('includes users in the PATCH payload only when membership changed', async () => {
    updateRole.mockResolvedValue(editRole({ users: [] }))

    render(
      <RoleForm
        mode={{ type: 'edit', role: editRole({ users: [11] }) }}
        permissionOptions={PERMISSIONS}
        onSuccess={vi.fn()}
        onCancel={vi.fn()}
      />,
    )

    fireEvent.click(screen.getByText('clear-users'))
    fireEvent.click(screen.getByRole('button', { name: 'Save' }))

    await waitFor(() => expect(updateRole).toHaveBeenCalled())
    const [, payload] = updateRole.mock.calls[0]
    expect(payload).toEqual({ users: [] })
  })
})
