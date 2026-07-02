import { render, screen } from '@testing-library/react'
import type { ICellRendererParams } from 'ag-grid-community'
import { describe, expect, it } from 'vitest'
import { roleColumnRenderers } from '@/features/roles/column-renderers'

function renderPermissions(value: unknown) {
  const renderer = roleColumnRenderers.permissions
  if (!renderer) {
    throw new Error('Missing permissions renderer')
  }

  const params = { value } as unknown as ICellRendererParams
  return render(<>{renderer(params)}</>)
}

describe('roleColumnRenderers.permissions', () => {
  it('renders the number of permissions instead of individual badges', () => {
    renderPermissions(['users.viewAny', 'users.update', 'roles.viewAny'])

    expect(screen.getByText('3')).toBeInTheDocument()
    expect(screen.queryByText('users.viewAny')).toBeNull()
    expect(screen.queryByText('users.update')).toBeNull()
  })

  it('renders zero for a role without permissions', () => {
    renderPermissions([])

    expect(screen.getByText('0')).toBeInTheDocument()
  })
})
