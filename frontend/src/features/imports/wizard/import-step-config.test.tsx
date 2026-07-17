import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ImportStepConfig } from '@/features/imports/wizard/import-step-config'
import '@/features/imports/wizard/i18n'
import type { ImportGlobalFieldDescriptor } from '@/features/imports/wizard/types'

/**
 * Spec 0033 AC-021: the config step renders one control per `global_fields`
 * entry, blocks the "next" submit while a required field is unset, and
 * forwards the filled values once valid. `use-for-select` (the relation
 * select's data hook) is mocked, mirroring `relation-select-field.test.tsx`.
 */

const useForSelectMock = vi.fn()
vi.mock('@/features/for-select/use-for-select', async () => {
  const actual =
    await vi.importActual<typeof import('@/features/for-select/use-for-select')>(
      '@/features/for-select/use-for-select',
    )
  return {
    flattenForSelectPages: actual.flattenForSelectPages,
    useForSelect: (args: unknown) => useForSelectMock(args),
    useForSelectLabels: () => new Map(),
  }
})

const GLOBAL_FIELDS: ImportGlobalFieldDescriptor[] = [
  { id: 'campaign_id', label: 'Campaign', required: true, for_select_resource: 'campaigns', default: null },
  { id: 'source_id', label: 'Source', required: false, for_select_resource: 'sources', default: null },
]

function renderStep(onNext = vi.fn()) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  render(
    <QueryClientProvider client={client}>
      <ImportStepConfig globalFields={GLOBAL_FIELDS} initialValues={{}} onNext={onNext} />
    </QueryClientProvider>,
  )
  return onNext
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  useForSelectMock.mockReset()
  useForSelectMock.mockReturnValue({
    data: { pages: [{ items: [{ id: 7, label: 'Spring campaign' }] }] },
    isPending: false,
    isError: false,
    fetchNextPage: vi.fn(),
    hasNextPage: false,
    isFetchingNextPage: false,
    refetch: vi.fn(),
  })
})

describe('ImportStepConfig', () => {
  it('renders a control for every global field, marking the required one', () => {
    renderStep()

    expect(screen.getByRole('combobox', { name: 'Campaign' })).toBeInTheDocument()
    expect(screen.getByRole('combobox', { name: 'Source' })).toBeInTheDocument()
    expect(screen.getByText('Campaign').parentElement).toHaveTextContent('*')
  })

  it('blocks submit and does not call onNext while the required field is unset', async () => {
    const onNext = renderStep()

    fireEvent.click(screen.getByRole('button', { name: 'Continue to mapping' }))

    expect(await screen.findByRole('alert')).toHaveTextContent('This field is required.')
    expect(onNext).not.toHaveBeenCalled()
  })

  it('submits the filled values once the required field is picked', async () => {
    const onNext = renderStep()

    fireEvent.click(screen.getByRole('combobox', { name: 'Campaign' }))
    fireEvent.click(await screen.findByRole('option', { name: 'Spring campaign' }))
    fireEvent.click(screen.getByRole('button', { name: 'Continue to mapping' }))

    await waitFor(() => expect(onNext).toHaveBeenCalledWith({ campaign_id: 7, source_id: null }))
  })
})
