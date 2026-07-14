import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { lazy } from 'react'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import { useForm } from 'react-hook-form'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { Form } from '@/components/ui/form'
import { RelationSelectField } from '@/components/form/relation-select-field'
import { QuickCreateDepthContext } from '@/components/form/quick-create-depth-context'
import type { QuickCreateEntry, QuickCreateFormProps } from '@/features/quick-create/types'

/**
 * Spec 0028 lane C — `RelationSelectField` composes `AsyncPaginatedSelect`
 * with the quick-create "+" (`QuickCreateButton`, lane B). This suite mocks
 * the for-select data hook (own component test already covers the picker
 * itself) and the quick-create registry (own suite already covers
 * `resolveQuickCreate`), so it isolates the wiring this lane owns:
 * AC-006/AC-007/AC-008 and the nesting guard.
 */

const useForSelectMock = vi.fn()
vi.mock('@/features/for-select/use-for-select', async () => {
  const actual = await vi.importActual<
    typeof import('@/features/for-select/use-for-select')
  >('@/features/for-select/use-for-select')
  return {
    flattenForSelectPages: actual.flattenForSelectPages,
    useForSelect: (args: unknown) => useForSelectMock(args),
  }
})

const canMock = vi.fn<(permission: string) => boolean>()
vi.mock('@/features/auth/use-abilities', () => ({
  useAbilities: () => ({
    can: (permission: string) => canMock(permission),
    hasRole: () => false,
    roles: [],
    isLoading: false,
  }),
}))

const resolveQuickCreateMock = vi.fn<(resource: string) => QuickCreateEntry | null>()
vi.mock('@/features/quick-create/quick-create-registry', () => ({
  resolveQuickCreate: (resource: string) => resolveQuickCreateMock(resource),
}))

function fakeEntry(ref: { id: number; name: string }): QuickCreateEntry {
  return {
    titleKey: 'sources.form.createTitle',
    descriptionKey: 'sources.form.createSubtitle',
    permission: 'sources.create',
    form: lazy(async () => ({
      default: ({ onSuccess, onCancel }: QuickCreateFormProps) => (
        <div>
          <button type="button" onClick={() => onSuccess(ref)}>
            fake-submit
          </button>
          <button type="button" onClick={onCancel}>
            fake-cancel
          </button>
        </div>
      ),
    })),
  }
}

interface FormValues {
  source_id: number | null
  name: string
}

function Harness({
  defaultValues = { source_id: null, name: '' },
  onSubmit = vi.fn(),
  depth,
}: {
  defaultValues?: FormValues
  onSubmit?: (values: FormValues) => void
  depth?: number
}) {
  const form = useForm<FormValues>({ defaultValues })
  const field = (
    <RelationSelectField
      control={form.control}
      name="source_id"
      metaKey="source_id"
      label="Source"
      resource="sources"
      searchPlaceholder="Search sources…"
      selected={null}
      placeholder="Select a source…"
      emptyLabel="No sources found."
      errorLabel="Unable to load sources."
      clearLabel="Clear source"
      retryLabel="Retry"
    />
  )

  return (
    <Form {...form}>
      <form onSubmit={form.handleSubmit(onSubmit)}>
        <input aria-label="Name" {...form.register('name')} />
        {depth !== undefined ? (
          <QuickCreateDepthContext.Provider value={depth}>{field}</QuickCreateDepthContext.Provider>
        ) : (
          field
        )}
        <button type="submit">save</button>
      </form>
    </Form>
  )
}

function renderHarness(props: Parameters<typeof Harness>[0] = {}) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <Harness {...props} />
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  useForSelectMock.mockReset()
  useForSelectMock.mockReturnValue({
    data: { pages: [{ items: [] }] },
    isPending: false,
    isError: false,
    fetchNextPage: vi.fn(),
    hasNextPage: false,
    isFetchingNextPage: false,
    refetch: vi.fn(),
  })
  canMock.mockReset()
  canMock.mockReturnValue(true)
  resolveQuickCreateMock.mockReset()
  resolveQuickCreateMock.mockReturnValue(fakeEntry({ id: 99, name: 'Nuova Fonte' }))
})

describe('RelationSelectField quick-create wiring', () => {
  it('sets the RHF field to the new id and shows it selected (AC-006)', async () => {
    renderHarness()

    fireEvent.click(screen.getByRole('button', { name: i18n.t('sources.form.createTitle') }))
    fireEvent.click(await screen.findByRole('button', { name: 'fake-submit' }))

    await waitFor(() => expect(screen.getByText('Nuova Fonte')).toBeInTheDocument())
    expect(screen.getByRole('combobox', { name: 'Source' })).toBeInTheDocument()
  })

  it('does not lose sibling field values when the dialog opens and cancels (AC-007)', async () => {
    renderHarness()
    fireEvent.change(screen.getByLabelText('Name'), { target: { value: 'kept value' } })

    fireEvent.click(screen.getByRole('button', { name: i18n.t('sources.form.createTitle') }))
    fireEvent.click(await screen.findByRole('button', { name: 'fake-cancel' }))

    await waitFor(() =>
      expect(screen.queryByRole('button', { name: 'fake-cancel' })).not.toBeInTheDocument(),
    )
    expect(screen.getByLabelText('Name')).toHaveValue('kept value')
  })

  it('does not submit the parent form when the "+" is clicked (AC-008)', () => {
    const onSubmit = vi.fn()
    renderHarness({ onSubmit })

    fireEvent.click(screen.getByRole('button', { name: i18n.t('sources.form.createTitle') }))

    expect(onSubmit).not.toHaveBeenCalled()
  })

  it('hides the "+" once already nested inside a quick-create dialog', () => {
    renderHarness({ depth: 1 })

    expect(
      screen.queryByRole('button', { name: i18n.t('sources.form.createTitle') }),
    ).not.toBeInTheDocument()
  })

  it('renders no "+" without the {domain}.create permission (AC-002 propagated)', () => {
    canMock.mockReturnValue(false)
    renderHarness()

    expect(
      screen.queryByRole('button', { name: i18n.t('sources.form.createTitle') }),
    ).not.toBeInTheDocument()
    expect(screen.getByRole('combobox', { name: 'Source' })).toBeInTheDocument()
  })
})
