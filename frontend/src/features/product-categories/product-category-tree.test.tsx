import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactNode } from 'react'
import { render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { ProductCategoryTree } from '@/features/product-categories/product-category-tree'
import type { ProductCategoryTreeNode } from '@/features/product-categories/types'

/** Spec 0017 AC-022: the tree renders parent/child hierarchy at unlimited depth. */

const fetchProductCategoryTreeMock = vi.fn()

vi.mock('@/features/product-categories/api', () => ({
  fetchProductCategoryTree: () => fetchProductCategoryTreeMock(),
}))

vi.mock('@/features/product-categories/product-category-form', () => ({
  ProductCategoryForm: () => <div>category-form-stub</div>,
}))

vi.mock('@/components/page-header', () => ({
  PageHeader: ({ actions }: { actions?: ReactNode }) => <div>{actions}</div>,
}))

vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({
    can: () => true,
    hasRole: () => false,
    roles: [],
    isLoading: false,
  }),
}))

function tree(): ProductCategoryTreeNode[] {
  return [
    {
      id: 1,
      name: 'Electronics',
      parent_id: null,
      attributes_count: 1,
      products_count: 0,
      children: [
        {
          id: 2,
          name: 'Laptops',
          parent_id: 1,
          attributes_count: 2,
          products_count: 5,
          children: [],
        },
      ],
    },
  ]
}

function renderTree() {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>
        <ProductCategoryTree />
      </ConfirmDialogProvider>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  fetchProductCategoryTreeMock.mockReset()
})

describe('ProductCategoryTree — hierarchy render (AC-022)', () => {
  it('renders both a root category and its nested child', async () => {
    fetchProductCategoryTreeMock.mockResolvedValue(tree())

    renderTree()

    expect(await screen.findByText('Electronics')).toBeInTheDocument()
    expect(await screen.findByText('Laptops')).toBeInTheDocument()
  })

  it('shows the empty state when the tree has no categories', async () => {
    fetchProductCategoryTreeMock.mockResolvedValue([])

    renderTree()

    expect(await screen.findByText('No product categories yet.')).toBeInTheDocument()
  })

  it('opens the create sheet for a new root category', async () => {
    fetchProductCategoryTreeMock.mockResolvedValue(tree())

    renderTree()
    await screen.findByText('Electronics')

    await waitFor(() =>
      screen.getByRole('button', { name: 'New category' }).click(),
    )

    expect(await screen.findByText('category-form-stub')).toBeInTheDocument()
  })
})
