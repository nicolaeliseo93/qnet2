import { useTranslation } from 'react-i18next'
import type { Control, UseFormSetValue } from 'react-hook-form'
import { useQueryClient } from '@tanstack/react-query'
import { FormControl } from '@/components/ui/form'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { MetaField } from '@/features/authorization/MetaField'
import { useQuickCreateAction } from '@/components/form/use-quick-create-action'
import type { RelationFieldRef } from '@/components/form/relation-select-field'
import type { ForSelectItem } from '@/features/for-select/types'
import { REGISTRIES_FOR_SELECT_RESOURCE } from '@/features/registries/for-select-api'
import { fetchOpportunityRegistryMeta } from '@/features/opportunities/opportunity-relation-meta'
import { managerSlotsFromRefs } from '@/features/opportunities/opportunity-form-payload'
import type { OpportunityFormValues } from '@/features/opportunities/use-opportunity-form'

interface OpportunityRegistryFieldProps {
  control: Control<OpportunityFormValues>
  setValue: UseFormSetValue<OpportunityFormValues>
  /** The loaded opportunity's linked registry, if any (edit mode hydration). */
  selected: RelationFieldRef | null
  /** BR-2: forced read-only when derived from a linked Lead (spec 0040 MT-6). */
  forceDisabled?: boolean
}

function toForSelectItem(ref: RelationFieldRef | null): ForSelectItem | null {
  return ref ? { id: ref.id, label: ref.name } : null
}

/**
 * The opportunity's required anagrafica (`registry_id`, D-4). Picking a
 * registry resets `referent_id` (BR-4: still anagrafica-scoped) and inherits
 * `manager_slots` from its account managers (`meta.managers`, spec 0040 A-5),
 * still freely editable afterwards. commercial/reporter (A-3) are the whole
 * platform list, INDEPENDENT of the anagrafica, so they are NOT touched here
 * (user directive 2026-07-17: selecting a registry must not auto-fill them).
 * A single one-shot fetch, run as a direct consequence of the user's
 * selection, never a render-time effect, so it never re-overwrites a later
 * edit.
 */
export function OpportunityRegistryField({
  control,
  setValue,
  selected,
  forceDisabled = false,
}: OpportunityRegistryFieldProps) {
  const { t } = useTranslation()
  const queryClient = useQueryClient()
  const { quickCreated, renderAction } = useQuickCreateAction(REGISTRIES_FOR_SELECT_RESOURCE)

  const applyRegistrySelection = async (registryId: number | null) => {
    // Referent is anagrafica-scoped (BR-4): a referent from the previous
    // registry is no longer valid, so reset it. commercial/reporter (A-3) are
    // independent of the anagrafica and are intentionally left untouched.
    setValue('referent_id', null, { shouldDirty: true })
    // A-5: account managers are inherited from the anagrafica — clear them too,
    // then repopulate below (empty when the registry has none).
    setValue('manager_slots', [], { shouldDirty: true })

    if (registryId === null) {
      return
    }
    const meta = await fetchOpportunityRegistryMeta(queryClient, registryId)
    if (!meta) {
      return
    }
    setValue('manager_slots', managerSlotsFromRefs(meta.managers ?? []), { shouldDirty: true })
  }

  const selectRegistry = (field: { onChange: (value: number | null) => void }, registryId: number | null) => {
    field.onChange(registryId)
    void applyRegistrySelection(registryId)
  }

  return (
    <MetaField control={control} name="registry_id" metaKey="registry_id" label={t('opportunities.form.registry')}>
      {({ field, disabled }) => {
        const isDisabled = disabled || forceDisabled
        const quickCreatedMatch = quickCreated.find((ref) => ref.id === field.value) ?? null
        return (
          <FormControl>
            <AsyncPaginatedSelect
              resource={REGISTRIES_FOR_SELECT_RESOURCE}
              value={field.value}
              onChange={(registryId) => selectRegistry(field, registryId)}
              selectedItem={toForSelectItem(quickCreatedMatch) ?? toForSelectItem(selected)}
              disabled={isDisabled}
              labels={{
                placeholder: t('opportunities.form.selectPlaceholder'),
                searchPlaceholder: t('opportunities.form.registrySearch'),
                empty: t('opportunities.form.selectEmpty'),
                error: t('opportunities.form.selectError'),
                clearLabel: t('common.clear'),
                triggerLabel: t('opportunities.form.registry'),
                retry: t('common.retry'),
              }}
              action={renderAction((ref) => selectRegistry(field, ref.id), isDisabled)}
            />
          </FormControl>
        )
      }}
    </MetaField>
  )
}
