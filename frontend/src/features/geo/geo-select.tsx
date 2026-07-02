import { useTranslation } from 'react-i18next'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { Skeleton } from '@/components/ui/skeleton'
import {
  useCities,
  useCountries,
  useProvinces,
  useStates,
} from '@/features/geo/use-geo'

/** The cascading geo selection, all ids nullable until chosen. */
export interface GeoValue {
  country_id: number | null
  state_id: number | null
  province_id: number | null
  city_id: number | null
}

interface GeoSelectProps {
  value: GeoValue
  onChange: (value: GeoValue) => void
  disabled?: boolean
}

interface GeoOption {
  id: number
  name: string
}

interface GeoFieldProps {
  label: string
  placeholder: string
  value: number | null
  options: GeoOption[]
  isPending: boolean
  isError: boolean
  disabled: boolean
  emptyLabel: string
  errorLabel: string
  onChange: (id: number) => void
}

/**
 * A single dependent geo select. Renders a skeleton while its data loads, an
 * inline message on error/empty, and otherwise the option list. Disabled until
 * its parent has been chosen.
 */
function GeoField({
  label,
  placeholder,
  value,
  options,
  isPending,
  isError,
  disabled,
  emptyLabel,
  errorLabel,
  onChange,
}: GeoFieldProps) {
  const showSkeleton = !disabled && isPending

  return (
    <div className="flex flex-col gap-1.5">
      <span className="text-sm font-medium">{label}</span>
      {showSkeleton ? (
        <Skeleton className="h-9 w-full" />
      ) : isError && !disabled ? (
        <p className="text-sm text-destructive">{errorLabel}</p>
      ) : (
        <Select
          value={value != null ? String(value) : undefined}
          onValueChange={(next) => onChange(Number(next))}
          disabled={disabled || options.length === 0}
        >
          <SelectTrigger className="w-full">
            <SelectValue
              placeholder={
                !disabled && options.length === 0 ? emptyLabel : placeholder
              }
            />
          </SelectTrigger>
          <SelectContent>
            {options.map((option) => (
              <SelectItem key={option.id} value={String(option.id)}>
                {option.name}
              </SelectItem>
            ))}
          </SelectContent>
        </Select>
      )}
    </div>
  )
}

/**
 * Controlled, domain-agnostic country → state → province → city cascade
 * (ADR 0010). When a parent changes, the descendant values are reset and the
 * children stay disabled until their parent is chosen. Data comes from dependent
 * geo queries; each select shows a skeleton while loading and an inline
 * error/empty state.
 *
 * The province level is optional: many countries have none, so the province
 * select simply shows its empty state and the city select falls back to filter
 * by state (cities load as soon as a state is chosen, with or without a
 * province). This keeps every country reachable.
 *
 * This component must NOT depend on any feature domain (e.g. personal-data): it
 * only speaks the generic `GeoValue` contract.
 */
export function GeoSelect({ value, onChange, disabled = false }: GeoSelectProps) {
  const { t } = useTranslation()

  const countries = useCountries()
  const states = useStates(value.country_id)
  const provinces = useProvinces(value.state_id)
  const cities = useCities(value.state_id, value.province_id)

  const handleCountry = (countryId: number) => {
    onChange({
      country_id: countryId,
      state_id: null,
      province_id: null,
      city_id: null,
    })
  }

  const handleState = (stateId: number) => {
    onChange({ ...value, state_id: stateId, province_id: null, city_id: null })
  }

  const handleProvince = (provinceId: number) => {
    onChange({ ...value, province_id: provinceId, city_id: null })
  }

  const handleCity = (cityId: number) => {
    onChange({ ...value, city_id: cityId })
  }

  return (
    <div className="flex flex-col gap-3">
      <GeoField
        label={t('geo.country')}
        placeholder={t('geo.countryPlaceholder')}
        value={value.country_id}
        options={countries.data ?? []}
        isPending={countries.isPending}
        isError={countries.isError}
        disabled={disabled}
        emptyLabel={t('geo.empty')}
        errorLabel={t('geo.error')}
        onChange={handleCountry}
      />

      <GeoField
        label={t('geo.state')}
        placeholder={t('geo.statePlaceholder')}
        value={value.state_id}
        options={states.data ?? []}
        isPending={states.isPending}
        isError={states.isError}
        disabled={disabled || value.country_id == null}
        emptyLabel={t('geo.empty')}
        errorLabel={t('geo.error')}
        onChange={handleState}
      />

      <GeoField
        label={t('geo.province')}
        placeholder={t('geo.provincePlaceholder')}
        value={value.province_id}
        options={provinces.data ?? []}
        isPending={provinces.isPending}
        isError={provinces.isError}
        disabled={disabled || value.state_id == null}
        emptyLabel={t('geo.empty')}
        errorLabel={t('geo.error')}
        onChange={handleProvince}
      />

      <GeoField
        label={t('geo.city')}
        placeholder={t('geo.cityPlaceholder')}
        value={value.city_id}
        options={cities.data ?? []}
        isPending={cities.isPending}
        isError={cities.isError}
        disabled={disabled || value.state_id == null}
        emptyLabel={t('geo.empty')}
        errorLabel={t('geo.error')}
        onChange={handleCity}
      />
    </div>
  )
}
