import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
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
  useCities: (stateId: number | null, provinceId?: number | null) =>
    useCitiesMock(stateId, provinceId),
}))

function query<T>(data: T) {
  return { data, isPending: false, isError: false }
}

const pending = { data: undefined, isPending: true, isError: false }
const errored = { data: undefined, isPending: false, isError: true }

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
    query([{ id: 100, name: 'Grumo Nevano', state_id: 10, province_id: 50 }]),
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
    expect(useCitiesMock).toHaveBeenCalledWith(null, null)
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

    expect(useCitiesMock).toHaveBeenCalledWith(10, 50)
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

  it('shows a skeleton while the countries load', () => {
    useCountriesMock.mockReturnValue(pending)
    const { container } = render(<GeoSelect value={empty} onChange={() => {}} />)

    expect(
      container.querySelector('[data-slot="skeleton"]'),
    ).toBeInTheDocument()
  })

  it('shows an inline error when the countries fail to load', () => {
    useCountriesMock.mockReturnValue(errored)
    render(<GeoSelect value={empty} onChange={() => {}} />)

    expect(screen.getByText('Failed to load options.')).toBeInTheDocument()
  })
})
