import { beforeAll, describe, expect, it, vi } from 'vitest'
import { render, screen } from '@testing-library/react'
import { useForm } from 'react-hook-form'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { Form } from '@/components/ui/form'
import { OpportunityTeamSection } from '@/features/opportunities/opportunity-team-section'
import type { OpportunityFormValues } from '@/features/opportunities/use-opportunity-form'

vi.mock('@/components/ui/async-paginated-select', () => ({
  AsyncPaginatedSelect: () => <div />,
}))

vi.mock('@/components/form/manager-slots-field', () => ({
  ManagerSlotsField: () => <div />,
}))

const EMPTY_SELECTED_ITEMS = {
  registry: null,
  opportunityStatus: null,
  referent: null,
  commercial: null,
  reporter: null,
  source: null,
  supervisor: null,
  managers: [],
}

function TeamSectionHarness({ supervisorRequired }: { supervisorRequired: boolean }) {
  const form = useForm<OpportunityFormValues>({
    defaultValues: {
      name: '',
      registry_id: null,
      opportunity_status_id: null,
      referent_id: null,
      commercial_id: null,
      reporter_id: null,
      supervisor_id: null,
      source_id: null,
      product_lines: [],
      manager_slots: [],
      start_date: null,
      expected_close_date: null,
      estimated_value: null,
      success_probability: 0,
    },
  })
  const queryClient = new QueryClient({ defaultOptions: { queries: { retry: false } } })

  return (
    <QueryClientProvider client={queryClient}>
      <Form {...form}>
        <OpportunityTeamSection
          control={form.control}
          selectedItems={EMPTY_SELECTED_ITEMS}
          supervisorRequired={supervisorRequired}
        />
      </Form>
    </QueryClientProvider>
  )
}

function supervisorLabel(): HTMLElement {
  return screen.getByText(
    (_, element) => element?.tagName === 'LABEL' && element.textContent?.startsWith('Supervisor') === true,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('OpportunityTeamSection', () => {
  it('marks supervisor as required in create mode', () => {
    render(<TeamSectionHarness supervisorRequired />)

    expect(supervisorLabel()).toHaveTextContent('Supervisor*')
  })

  it('does not mark supervisor as required in edit mode', () => {
    render(<TeamSectionHarness supervisorRequired={false} />)

    expect(supervisorLabel()).toHaveTextContent('Supervisor')
    expect(supervisorLabel()).not.toHaveTextContent('*')
  })
})
