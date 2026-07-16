import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import type { ICellRendererParams } from 'ag-grid-community'
import { describe, expect, it } from 'vitest'
import i18n from '@/i18n'
import { businessFunctionColumnRenderers } from '@/features/business-functions/column-renderers'
import type {
  BusinessFunctionMember,
  BusinessFunctionOperationalSite,
  BusinessFunctionParent,
} from '@/features/business-functions/types'

const MANAGER: BusinessFunctionMember = { id: 1, name: 'Ada Lovelace', avatar_url: null }
const USERS: BusinessFunctionMember[] = [
  { id: 1, name: 'Ada Lovelace', avatar_url: null },
  { id: 2, name: 'Grace Hopper', avatar_url: null },
  { id: 3, name: 'Katherine Johnson', avatar_url: null },
]

function renderCell(columnId: string, value: unknown) {
  const renderer = businessFunctionColumnRenderers[columnId]
  if (!renderer) {
    throw new Error(`Missing renderer for column "${columnId}"`)
  }
  const params = { value } as unknown as ICellRendererParams
  return render(<>{renderer(params)}</>)
}

describe('businessFunctionColumnRenderers.manager', () => {
  it('renders an avatar for the responsabile', () => {
    renderCell('manager', MANAGER)
    expect(screen.getByText('AL')).toBeInTheDocument()
  })

  it('reveals the name in an accessible tooltip on hover', async () => {
    renderCell('manager', MANAGER)
    // Radix Tooltip opens on `pointermove` (not `mouseenter`, see TooltipTrigger)
    // via a zero-delay timer on first open, hence the `waitFor`.
    fireEvent.pointerMove(screen.getByLabelText('Ada Lovelace'))
    await waitFor(() => {
      expect(screen.getByRole('tooltip')).toHaveTextContent('Ada Lovelace')
    })
  })

  it('reveals the name on keyboard focus (accessible without a mouse)', () => {
    renderCell('manager', MANAGER)
    fireEvent.focus(screen.getByLabelText('Ada Lovelace'))
    expect(screen.getByRole('tooltip')).toHaveTextContent('Ada Lovelace')
  })

  it('renders an em dash when there is no responsabile', () => {
    renderCell('manager', null)
    expect(screen.getByText('—')).toBeInTheDocument()
  })
})

describe('businessFunctionColumnRenderers.users', () => {
  it('renders one avatar per associated user', () => {
    renderCell('users', USERS)
    expect(screen.getByText('AL')).toBeInTheDocument()
    expect(screen.getByText('GH')).toBeInTheDocument()
    expect(screen.getByText('KJ')).toBeInTheDocument()
  })

  it("exposes each user's name via its own tooltip", async () => {
    renderCell('users', USERS)
    fireEvent.pointerMove(screen.getByLabelText('Grace Hopper'))
    await waitFor(() => {
      expect(screen.getByRole('tooltip')).toHaveTextContent('Grace Hopper')
    })
  })

  it('collapses avatars beyond the visible cap into a "+N" chip', async () => {
    const many = Array.from({ length: 7 }, (_, index) => ({
      id: index + 1,
      name: `User ${index + 1}`,
      avatar_url: null,
    }))
    renderCell('users', many)

    expect(screen.getByText('+2')).toBeInTheDocument()
    fireEvent.pointerMove(screen.getByText('+2'))
    await waitFor(() => {
      expect(screen.getByRole('tooltip')).toHaveTextContent('User 6, User 7')
    })
  })

  it('renders an em dash when there is no associated user', () => {
    renderCell('users', [])
    expect(screen.getByText('—')).toBeInTheDocument()
  })
})

describe('businessFunctionColumnRenderers.parent', () => {
  const PARENT: BusinessFunctionParent = { id: 4, name: 'Operations' }

  it("renders the parent function's name", () => {
    renderCell('parent', PARENT)
    expect(screen.getByText('Operations')).toBeInTheDocument()
  })

  it('renders an em dash for a top-level function', () => {
    renderCell('parent', null)
    expect(screen.getByText('—')).toBeInTheDocument()
  })
})

describe('businessFunctionColumnRenderers.operational_sites', () => {
  const SITES: BusinessFunctionOperationalSite[] = [
    { id: 1, label: 'Via Roma 1 - Milano' },
    { id: 2, label: 'Via Torino 2 - Torino' },
  ]

  it('renders the sites count', () => {
    renderCell('operational_sites', SITES)
    expect(screen.getByText('2')).toBeInTheDocument()
  })

  it('reveals every site label in an accessible tooltip', async () => {
    renderCell('operational_sites', SITES)
    fireEvent.pointerMove(screen.getByText('2'))
    await waitFor(() => {
      expect(screen.getByRole('tooltip')).toHaveTextContent('Via Roma 1 - Milano')
      expect(screen.getByRole('tooltip')).toHaveTextContent('Via Torino 2 - Torino')
    })
  })

  it('renders an em dash when no operational site is assigned', () => {
    renderCell('operational_sites', [])
    expect(screen.getByText('—')).toBeInTheDocument()
  })
})

describe('businessFunctionColumnRenderers boolean cells', () => {
  it('shows Yes for a true value', () => {
    renderCell('is_business_unit', true)
    expect(screen.getByText(i18n.t('common.yes'))).toBeInTheDocument()
  })

  it('shows No for a false value', () => {
    renderCell('is_business_service', false)
    expect(screen.getByText(i18n.t('common.no'))).toBeInTheDocument()
  })
})
