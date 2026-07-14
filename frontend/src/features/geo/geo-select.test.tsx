import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen, waitFor } from '@testing-library/react'
import i18n from '@/i18n'
import { GeoSelect, type GeoValue } from '@/features/geo/geo-select'

const useCountriesMock = vi.fn()
const useStatesMock = vi.fn()
const useProvincesMock = vi.fn()
const useCitiesMock = vi.fn()

vi.mock('@/features/geo/use-geo', () => ({
  useCountries: () => useCountriesMock(),
  useStates: (countryId: number | null) => useStatesMock(countryId),
  useProvinces: (stateId: number | null) => useProvincesMock(stateId),
  useCities: (
    stateId: number | null,
    provinceId?: number | null,
    search?: string,
  ) => useCitiesMock(stateId, provinceId, search),
}))

function query<T>(data: T) {
  return { data, isPending: false, isError: false, refetch: vi.fn() }
}

// Cities are an infinite query: the hook exposes paged data plus the paging
// controls the select drives for infinite scroll.
function cityQuery(
  items: unknown[],
  overrides: Record<string, unknown> = {},
) {
  return {
    data: { pages: [items] },
    isPending: false,
    isError: false,
    hasNextPage: false,
    isFetchingNextPage: false,
    fetchNextPage: vi.fn(),
    refetch: vi.fn(),
    ...overrides,
  }
}

const pending = {
  data: undefined,
  isPending: true,
  isError: false,
  refetch: vi.fn(),
}
const errored = {
  data: undefined,
  isPending: false,
  isError: true,
  refetch: vi.fn(),
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  useCountriesMock.mockReset()
  useStatesMock.mockReset()
  useProvincesMock.mockReset()
  useCitiesMock.mockReset()

  useCountriesMock.mockReturnValue(
    query([
      { id: 1, name: 'Italy', iso2: 'IT' },
      { id: 2, name: 'France', iso2: 'FR' },
    ]),
  )
  useStatesMock.mockReturnValue(
    query([
      { id: 10, name: 'Campania', country_id: 1 },
      { id: 11, name: 'Tuscany', country_id: 1 },
    ]),
  )
  useProvincesMock.mockReturnValue(
    query([
      { id: 50, name: 'Naples', state_id: 10 },
      { id: 51, name: 'Caserta', state_id: 10 },
    ]),
  )
  useCitiesMock.mockReturnValue(
    cityQuery([{ id: 100, name: 'Grumo Nevano', state_id: 10, province_id: 50 }]),
  )
})

const empty: GeoValue = {
  country_id: null,
  state_id: null,
  province_id: null,
  city_id: null,
}

describe('GeoSelect', () => {
  it('gates state/province/city queries until their parent is chosen', () => {
    render(<GeoSelect value={empty} onChange={() => {}} />)

    expect(useStatesMock).toHaveBeenCalledWith(null)
    expect(useProvincesMock).toHaveBeenCalledWith(null)
    expect(useCitiesMock).toHaveBeenCalledWith(null, null, '')
  })

  it('disables the state/province/city selects until a country is chosen', () => {
    render(<GeoSelect value={empty} onChange={() => {}} />)

    // Order: country, state, province, city.
    const selects = screen.getAllByRole('combobox')
    expect(selects[0]).not.toBeDisabled()
    expect(selects[1]).toBeDisabled()
    expect(selects[2]).toBeDisabled()
    expect(selects[3]).toBeDisabled()
  })

  it('enables province and city once a state is chosen', () => {
    render(
      <GeoSelect
        value={{ ...empty, country_id: 1, state_id: 10 }}
        onChange={() => {}}
      />,
    )

    const selects = screen.getAllByRole('combobox')
    expect(selects[2]).not.toBeDisabled() // province
    expect(selects[3]).not.toBeDisabled() // city
  })

  it('filters cities by the chosen province', () => {
    render(
      <GeoSelect
        value={{ country_id: 1, state_id: 10, province_id: 50, city_id: null }}
        onChange={() => {}}
      />,
    )

    expect(useCitiesMock).toHaveBeenCalledWith(10, 50, '')
  })

  it('resets state, province and city when the country changes', () => {
    const onChange = vi.fn()
    render(
      <GeoSelect
        value={{ country_id: 1, state_id: 10, province_id: 50, city_id: 100 }}
        onChange={onChange}
      />,
    )

    fireEvent.click(screen.getAllByRole('combobox')[0])
    fireEvent.click(screen.getByRole('option', { name: 'France' }))

    expect(onChange).toHaveBeenCalledWith({
      country_id: 2,
      state_id: null,
      province_id: null,
      city_id: null,
    })
  })

  it('resets province and city when the state changes but keeps the country', () => {
    const onChange = vi.fn()
    render(
      <GeoSelect
        value={{ country_id: 1, state_id: 10, province_id: 50, city_id: 100 }}
        onChange={onChange}
      />,
    )

    fireEvent.click(screen.getAllByRole('combobox')[1])
    fireEvent.click(screen.getByRole('option', { name: 'Tuscany' }))

    expect(onChange).toHaveBeenCalledWith({
      country_id: 1,
      state_id: 11,
      province_id: null,
      city_id: null,
    })
  })

  it('resets only the city when the province changes', () => {
    const onChange = vi.fn()
    render(
      <GeoSelect
        value={{ country_id: 1, state_id: 10, province_id: 50, city_id: 100 }}
        onChange={onChange}
      />,
    )

    fireEvent.click(screen.getAllByRole('combobox')[2])
    fireEvent.click(screen.getByRole('option', { name: 'Caserta' }))

    expect(onChange).toHaveBeenCalledWith({
      country_id: 1,
      state_id: 10,
      province_id: 51,
      city_id: null,
    })
  })

  it('shows a skeleton inside the popup while the countries load', () => {
    useCountriesMock.mockReturnValue(pending)
    render(<GeoSelect value={empty} onChange={() => {}} />)

    fireEvent.click(screen.getAllByRole('combobox')[0])
    expect(
      screen.getByTestId('searchable-select-skeleton'),
    ).toBeInTheDocument()
  })

  it('shows an inline error with a retry inside the popup on load failure', () => {
    const refetch = vi.fn()
    useCountriesMock.mockReturnValue({ ...errored, refetch })
    render(<GeoSelect value={empty} onChange={() => {}} />)

    fireEvent.click(screen.getAllByRole('combobox')[0])
    expect(screen.getByText('Failed to load options.')).toBeInTheDocument()
    fireEvent.click(screen.getByRole('button', { name: 'Retry' }))
    expect(refetch).toHaveBeenCalled()
  })

  it('filters the country options client-side as the user types', () => {
    render(<GeoSelect value={empty} onChange={() => {}} />)

    fireEvent.click(screen.getAllByRole('combobox')[0])
    fireEvent.change(screen.getByLabelText('Search'), {
      target: { value: 'fra' },
    })

    expect(screen.getByRole('option', { name: 'France' })).toBeInTheDocument()
    expect(
      screen.queryByRole('option', { name: 'Italy' }),
    ).not.toBeInTheDocument()
  })

  it('narrows the city list server-side via a debounced search term', async () => {
    render(
      <GeoSelect
        value={{ country_id: 1, state_id: 10, province_id: null, city_id: null }}
        onChange={() => {}}
      />,
    )

    fireEvent.click(screen.getAllByRole('combobox')[3])
    fireEvent.change(screen.getByLabelText('Search'), {
      target: { value: 'grumo' },
    })

    await waitFor(() =>
      expect(useCitiesMock).toHaveBeenCalledWith(10, null, 'grumo'),
    )
  })

  it('keeps the city dropdown open (loading inside) while a search re-fetches', () => {
    // Regression: a re-search used to flip the field to a skeleton and unmount
    // the open popover, closing it and preventing selection.
    useCitiesMock.mockReturnValue(cityQuery([], { isPending: true }))
    render(
      <GeoSelect
        value={{ country_id: 1, state_id: 10, province_id: null, city_id: null }}
        onChange={() => {}}
      />,
    )

    fireEvent.click(screen.getAllByRole('combobox')[3])

    expect(screen.getByRole('listbox')).toBeInTheDocument()
    expect(screen.getByTestId('searchable-select-skeleton')).toBeInTheDocument()
  })

  it('loads the next city page when the sentinel intersects', async () => {
    const fetchNextPage = vi.fn()
    useCitiesMock.mockReturnValue(
      cityQuery(
        [{ id: 100, name: 'Aversa', state_id: 10, province_id: null }],
        { hasNextPage: true, fetchNextPage },
      ),
    )

    type ObserverCallback = (entries: { isIntersecting: boolean }[]) => void
    let trigger: ObserverCallback | null = null
    const observe = vi.fn()
    vi.stubGlobal(
      'IntersectionObserver',
      class {
        constructor(cb: ObserverCallback) {
          trigger = cb
        }
        observe = observe
        unobserve() {}
        disconnect() {}
        takeRecords() {
          return []
        }
      },
    )

    render(
      <GeoSelect
        value={{ country_id: 1, state_id: 10, province_id: null, city_id: null }}
        onChange={() => {}}
      />,
    )

    fireEvent.click(screen.getAllByRole('combobox')[3])
    await screen.findByRole('option', { name: 'Aversa' })
    await waitFor(() => expect(observe).toHaveBeenCalled())
    const fire = trigger as ObserverCallback | null
    fire?.([{ isIntersecting: true }])
    expect(fetchNextPage).toHaveBeenCalled()

    vi.unstubAllGlobals()
  })

  describe('lockedLevels (spec 0027)', () => {
    it('disables only the locked levels while leaving the rest editable', () => {
      render(
        <GeoSelect
          value={{ country_id: 1, state_id: 10, province_id: 50, city_id: 100 }}
          onChange={() => {}}
          lockedLevels={['country']}
        />,
      )

      const selects = screen.getAllByRole('combobox')
      expect(selects[0]).toBeDisabled() // country: locked
      expect(selects[1]).not.toBeDisabled() // state: not locked
      expect(selects[2]).not.toBeDisabled() // province: not locked
      expect(selects[3]).not.toBeDisabled() // city: not locked
    })

    it('disables every select when every level is locked', () => {
      render(
        <GeoSelect
          value={{ country_id: 1, state_id: 10, province_id: 50, city_id: 100 }}
          onChange={() => {}}
          lockedLevels={['country', 'state', 'province', 'city']}
        />,
      )

      for (const select of screen.getAllByRole('combobox')) {
        expect(select).toBeDisabled()
      }
    })

    it('keeps a locked descendant level disabled even before its parent is chosen', () => {
      render(<GeoSelect value={empty} onChange={() => {}} lockedLevels={['state']} />)

      expect(screen.getAllByRole('combobox')[1]).toBeDisabled()
    })

    it('defaults to no locked level, leaving every existing caller unaffected', () => {
      render(
        <GeoSelect
          value={{ country_id: 1, state_id: 10, province_id: 50, city_id: 100 }}
          onChange={() => {}}
        />,
      )

      for (const select of screen.getAllByRole('combobox')) {
        expect(select).not.toBeDisabled()
      }
    })
  })
})
