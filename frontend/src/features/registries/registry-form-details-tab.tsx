import { Building2, Info } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import type { Control } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { FormControl } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import { Switch } from '@/components/ui/switch'
import { Textarea } from '@/components/ui/textarea'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { AsyncPaginatedMultiSelect } from '@/components/ui/async-paginated-multi-select'
import type { ForSelectItem } from '@/features/for-select/types'
import { MetaField } from '@/features/authorization/MetaField'
import { useEnumOptions } from '@/features/config/use-config'
import { SOURCES_FOR_SELECT_RESOURCE } from '@/features/sources/for-select-api'
import { SECTORS_FOR_SELECT_RESOURCE } from '@/features/sectors/for-select-api'
import { REFERENTS_FOR_SELECT_RESOURCE } from '@/features/referents/for-select-api'
import { USERS_FOR_SELECT_RESOURCE } from '@/features/users/for-select-api'
import { ManagerSlotsField } from '@/features/registries/manager-slots-field'
import type { RegistryFormValues } from '@/features/registries/use-registry-form'

/** Every relation picker's edit-mode hydration, resolved once by the form hook. */
export interface RegistrySelectedItems {
  source: ForSelectItem | null
  sectors: ForSelectItem[]
  referents: ForSelectItem[]
  managers: ForSelectItem[]
  supervisor: ForSelectItem | null
  commercial: ForSelectItem | null
  reporter: ForSelectItem | null
}

interface DetailsTabContentProps {
  control: Control<RegistryFormValues>
  selectedItems: RegistrySelectedItems
  /** Read from `form.watch('is_supplier')`: gates the qualified-supplier toggle. */
  isSupplier: boolean
}

/** Formats a raw numeric field's RHF value for a controlled `<input type="number">`. */
function numberInputValue(value: number | null): string {
  return value === null ? '' : String(value)
}

/**
 * "Registry details" section: relations (source, sectors, referents,
 * managers, supervisor/commercial/reporter) plus the "ATECO codes"
 * placeholder — a disabled control reserved for a future spec, never
 * persisted and never part of the meta/schema — followed by the
 * "Business details" section (VAT group, supplier flags, convention status/
 * notes, size class, employee count). Spec 0020.
 */
export function DetailsTabContent({ control, selectedItems, isSupplier }: DetailsTabContentProps) {
  const { t } = useTranslation()
  const agreementStatusOptions = useEnumOptions('agreement_status')
  const sizeClassOptions = useEnumOptions('size_class')

  return (
    <>
      <FormSection
        icon={Info}
        title={t('registries.form.sections.relations.title')}
        description={t('registries.form.sections.relations.description')}
      >
        <MetaField control={control} name="source_id" metaKey="source_id" label={t('registries.form.source')}>
          {({ field, disabled }) => (
            <FormControl>
              <AsyncPaginatedSelect
                resource={SOURCES_FOR_SELECT_RESOURCE}
                value={field.value}
                onChange={field.onChange}
                selectedItem={selectedItems.source}
                disabled={disabled}
                labels={{
                  placeholder: t('registries.form.sourcePlaceholder'),
                  searchPlaceholder: t('registries.form.sourceSearch'),
                  empty: t('registries.form.sourceEmpty'),
                  error: t('registries.form.sourceError'),
                  clearLabel: t('common.clear'),
                  triggerLabel: t('registries.form.source'),
                  retry: t('common.retry'),
                }}
              />
            </FormControl>
          )}
        </MetaField>

        <MetaField control={control} name="sector_ids" metaKey="sector_ids" label={t('registries.form.sectors')}>
          {({ field, disabled }) => (
            <FormControl>
              <AsyncPaginatedMultiSelect
                resource={SECTORS_FOR_SELECT_RESOURCE}
                value={field.value}
                onChange={field.onChange}
                selectedItems={selectedItems.sectors}
                disabled={disabled}
                labels={{
                  placeholder: t('registries.form.sectorsPlaceholder'),
                  searchPlaceholder: t('registries.form.sectorsSearch'),
                  empty: t('registries.form.sectorsEmpty'),
                  error: t('registries.form.sectorsError'),
                  removeLabel: t('registries.form.sectorsRemove'),
                  triggerLabel: t('registries.form.sectors'),
                  retry: t('common.retry'),
                }}
              />
            </FormControl>
          )}
        </MetaField>

        <MetaField control={control} name="referent_ids" metaKey="referent_ids" label={t('registries.form.referents')}>
          {({ field, disabled }) => (
            <FormControl>
              <AsyncPaginatedMultiSelect
                resource={REFERENTS_FOR_SELECT_RESOURCE}
                value={field.value}
                onChange={field.onChange}
                selectedItems={selectedItems.referents}
                disabled={disabled}
                labels={{
                  placeholder: t('registries.form.referentsPlaceholder'),
                  searchPlaceholder: t('registries.form.referentsSearch'),
                  empty: t('registries.form.referentsEmpty'),
                  error: t('registries.form.referentsError'),
                  removeLabel: t('registries.form.referentsRemove'),
                  triggerLabel: t('registries.form.referents'),
                  retry: t('common.retry'),
                }}
              />
            </FormControl>
          )}
        </MetaField>

        <MetaField control={control} name="manager_slots" metaKey="manager_slots" label={t('registries.form.managers')}>
          {({ field, disabled }) => (
            <ManagerSlotsField
              value={field.value}
              onChange={field.onChange}
              selectedItems={selectedItems.managers}
              disabled={disabled}
            />
          )}
        </MetaField>

        <MetaField control={control} name="supervisor_id" metaKey="supervisor_id" label={t('registries.form.supervisor')}>
          {({ field, disabled }) => (
            <FormControl>
              <AsyncPaginatedSelect
                resource={USERS_FOR_SELECT_RESOURCE}
                value={field.value}
                onChange={field.onChange}
                selectedItem={selectedItems.supervisor}
                showAvatar
                disabled={disabled}
                labels={{
                  placeholder: t('registries.form.supervisorPlaceholder'),
                  searchPlaceholder: t('registries.form.managersSearch'),
                  empty: t('registries.form.managersEmpty'),
                  error: t('registries.form.managersError'),
                  clearLabel: t('common.clear'),
                  triggerLabel: t('registries.form.supervisor'),
                  retry: t('common.retry'),
                }}
              />
            </FormControl>
          )}
        </MetaField>

        <MetaField control={control} name="commercial_id" metaKey="commercial_id" label={t('registries.form.commercial')}>
          {({ field, disabled }) => (
            <FormControl>
              <AsyncPaginatedSelect
                resource={REFERENTS_FOR_SELECT_RESOURCE}
                value={field.value}
                onChange={field.onChange}
                selectedItem={selectedItems.commercial}
                disabled={disabled}
                labels={{
                  placeholder: t('registries.form.commercialPlaceholder'),
                  searchPlaceholder: t('registries.form.referentsSearch'),
                  empty: t('registries.form.referentsEmpty'),
                  error: t('registries.form.referentsError'),
                  clearLabel: t('common.clear'),
                  triggerLabel: t('registries.form.commercial'),
                  retry: t('common.retry'),
                }}
              />
            </FormControl>
          )}
        </MetaField>

        <MetaField control={control} name="reporter_id" metaKey="reporter_id" label={t('registries.form.reporter')}>
          {({ field, disabled }) => (
            <FormControl>
              <AsyncPaginatedSelect
                resource={REFERENTS_FOR_SELECT_RESOURCE}
                value={field.value}
                onChange={field.onChange}
                selectedItem={selectedItems.reporter}
                disabled={disabled}
                labels={{
                  placeholder: t('registries.form.reporterPlaceholder'),
                  searchPlaceholder: t('registries.form.referentsSearch'),
                  empty: t('registries.form.referentsEmpty'),
                  error: t('registries.form.referentsError'),
                  clearLabel: t('common.clear'),
                  triggerLabel: t('registries.form.reporter'),
                  retry: t('common.retry'),
                }}
              />
            </FormControl>
          )}
        </MetaField>

        <div className="flex flex-col gap-2">
          <span className="text-sm font-medium">{t('registries.form.atecoCodes')}</span>
          <Select disabled>
            <SelectTrigger className="w-full">
              <SelectValue placeholder={t('registries.form.atecoCodesComingSoon')} />
            </SelectTrigger>
            <SelectContent />
          </Select>
        </div>
      </FormSection>

      <FormSection
        icon={Building2}
        title={t('registries.form.sections.business.title')}
        description={t('registries.form.sections.business.description')}
      >
        <MetaField control={control} name="vat_group" metaKey="vat_group" label={t('registries.form.vatGroup')}>
          {({ field, disabled, readOnly }) => (
            <FormControl>
              <Input autoComplete="off" disabled={disabled} readOnly={readOnly} {...field} />
            </FormControl>
          )}
        </MetaField>

        <MetaField control={control} name="is_supplier" metaKey="is_supplier" label={t('registries.form.isSupplier')}>
          {({ field, disabled }) => (
            <FormControl>
              <Switch checked={field.value} onCheckedChange={field.onChange} disabled={disabled} />
            </FormControl>
          )}
        </MetaField>

        {isSupplier && (
          <MetaField
            control={control}
            name="is_qualified_supplier"
            metaKey="is_qualified_supplier"
            label={t('registries.form.isQualifiedSupplier')}
          >
            {({ field, disabled }) => (
              <FormControl>
                <Switch checked={field.value} onCheckedChange={field.onChange} disabled={disabled} />
              </FormControl>
            )}
          </MetaField>
        )}

        <MetaField control={control} name="agreement_status" metaKey="agreement_status" label={t('registries.form.agreementStatus')}>
          {({ field, disabled }) => (
            <Select value={field.value ?? undefined} onValueChange={field.onChange} disabled={disabled}>
              <FormControl>
                <SelectTrigger className="w-full">
                  <SelectValue placeholder={t('registries.form.agreementStatusPlaceholder')} />
                </SelectTrigger>
              </FormControl>
              <SelectContent>
                {agreementStatusOptions.map((option) => (
                  <SelectItem key={option.value} value={option.value}>
                    {option.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          )}
        </MetaField>

        <MetaField control={control} name="size_class" metaKey="size_class" label={t('registries.form.sizeClass')}>
          {({ field, disabled }) => (
            <Select value={field.value ?? undefined} onValueChange={field.onChange} disabled={disabled}>
              <FormControl>
                <SelectTrigger className="w-full">
                  <SelectValue placeholder={t('registries.form.sizeClassPlaceholder')} />
                </SelectTrigger>
              </FormControl>
              <SelectContent>
                {sizeClassOptions.map((option) => (
                  <SelectItem key={option.value} value={option.value}>
                    {option.label}
                  </SelectItem>
                ))}
              </SelectContent>
            </Select>
          )}
        </MetaField>

        <MetaField control={control} name="agreement_notes" metaKey="agreement_notes" label={t('registries.form.agreementNotes')}>
          {({ field, disabled, readOnly }) => (
            <FormControl>
              <Textarea disabled={disabled} readOnly={readOnly} {...field} />
            </FormControl>
          )}
        </MetaField>

        <MetaField control={control} name="employee_count" metaKey="employee_count" label={t('registries.form.employeeCount')}>
          {({ field, disabled, readOnly }) => (
            <FormControl>
              <Input
                type="number"
                min={0}
                step="1"
                disabled={disabled}
                readOnly={readOnly}
                value={numberInputValue(field.value)}
                onChange={(event) =>
                  field.onChange(event.target.value === '' ? null : Number(event.target.value))
                }
                onBlur={field.onBlur}
                name={field.name}
                ref={field.ref}
              />
            </FormControl>
          )}
        </MetaField>
      </FormSection>
    </>
  )
}
