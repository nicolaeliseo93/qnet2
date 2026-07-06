import { Info } from 'lucide-react'
import { useTranslation } from 'react-i18next'
import type { Control } from 'react-hook-form'
import { FormSection } from '@/components/form-section'
import { FormControl } from '@/components/ui/form'
import { Textarea } from '@/components/ui/textarea'
import {
  Select,
  SelectContent,
  SelectItem,
  SelectTrigger,
  SelectValue,
} from '@/components/ui/select'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import type { ForSelectItem } from '@/features/for-select/types'
import { MetaField } from '@/features/authorization/MetaField'
import { useEnumOptions } from '@/features/config/use-config'
import { REFERENT_TYPES_FOR_SELECT_RESOURCE } from '@/features/referent-types/for-select-api'
import type { ReferentFormValues } from '@/features/referents/use-referent-form'

interface DetailsTabContentProps {
  control: Control<ReferentFormValues>
  selectedReferentTypeItem: ForSelectItem | null
}

/**
 * "Referent details" section: the referent-specific scalar fields
 * (referent type, contact scope, notes) plus the "Activity sectors"
 * placeholder — a disabled control reserved for a future spec, never
 * persisted and never part of the meta/schema (spec 0016).
 */
export function DetailsTabContent({ control, selectedReferentTypeItem }: DetailsTabContentProps) {
  const { t } = useTranslation()
  const contactScopeOptions = useEnumOptions('referent_contact_scope')

  return (
    <FormSection
      icon={Info}
      title={t('referents.form.sections.details.title')}
      description={t('referents.form.sections.details.description')}
    >
      <MetaField
        control={control}
        name="referent_type_id"
        metaKey="referent_type_id"
        label={t('referents.form.referentType')}
      >
        {({ field, disabled }) => (
          <FormControl>
            <AsyncPaginatedSelect
              resource={REFERENT_TYPES_FOR_SELECT_RESOURCE}
              value={field.value}
              onChange={field.onChange}
              selectedItem={selectedReferentTypeItem}
              disabled={disabled}
              labels={{
                placeholder: t('referents.form.referentTypePlaceholder'),
                searchPlaceholder: t('referents.form.referentTypeSearch'),
                empty: t('referents.form.referentTypeEmpty'),
                error: t('referents.form.referentTypeError'),
                clearLabel: t('common.clear'),
                triggerLabel: t('referents.form.referentType'),
                retry: t('common.retry'),
              }}
            />
          </FormControl>
        )}
      </MetaField>

      <MetaField
        control={control}
        name="contact_scope"
        metaKey="contact_scope"
        label={t('referents.form.contactScope')}
      >
        {({ field, disabled }) => (
          <Select value={field.value} onValueChange={field.onChange} disabled={disabled}>
            <FormControl>
              <SelectTrigger className="w-full">
                <SelectValue />
              </SelectTrigger>
            </FormControl>
            <SelectContent>
              {contactScopeOptions.map((option) => (
                <SelectItem key={option.value} value={option.value}>
                  {option.label}
                </SelectItem>
              ))}
            </SelectContent>
          </Select>
        )}
      </MetaField>

      <div className="flex flex-col gap-2">
        <span className="text-sm font-medium">{t('referents.form.activitySectors')}</span>
        <Select disabled>
          <SelectTrigger className="w-full">
            <SelectValue placeholder={t('referents.form.activitySectorsComingSoon')} />
          </SelectTrigger>
          <SelectContent />
        </Select>
      </div>

      <MetaField control={control} name="notes" metaKey="notes" label={t('referents.form.notes')}>
        {({ field, disabled, readOnly }) => (
          <FormControl>
            <Textarea disabled={disabled} readOnly={readOnly} {...field} />
          </FormControl>
        )}
      </MetaField>
    </FormSection>
  )
}
