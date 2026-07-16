import { useForm, type FieldPath } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { AsyncPaginatedSelect } from '@/components/ui/async-paginated-select'
import { Button } from '@/components/ui/button'
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Input } from '@/components/ui/input'
import {
  buildImportConfigSchema,
  type ImportConfigFormValues,
} from '@/features/imports/wizard/import-config-schema'
import type { ImportGlobalFieldDescriptor } from '@/features/imports/wizard/types'

export interface ImportStepConfigProps {
  globalFields: ImportGlobalFieldDescriptor[]
  initialValues: ImportConfigFormValues
  onNext: (values: ImportConfigFormValues) => void
}

/** Fills every known field id with an explicit `null`, so an incomplete caller value never leaves a key `undefined`. */
function withFieldDefaults(
  globalFields: ImportGlobalFieldDescriptor[],
  values: ImportConfigFormValues,
): ImportConfigFormValues {
  const filled: ImportConfigFormValues = {}
  for (const field of globalFields) {
    filled[field.id] = values[field.id] ?? null
  }
  return filled
}

/**
 * Global-configuration step (AC-021): one control per `global_fields` entry
 * of the run's definition (a relation select when `for_select_resource` is
 * set, a plain number input otherwise). Client-side "submit" only advances
 * to the mapping step locally — the backend's single `configure` endpoint
 * persists mapping + config + dedup strategy together, from the mapping
 * step (see `use-import-wizard.ts`).
 */
export function ImportStepConfig({ globalFields, initialValues, onNext }: ImportStepConfigProps) {
  const { t } = useTranslation('importWizard')
  // Global-field labels arrive from the backend as default-namespace i18n keys
  // (`imports.leads.global.*`) — resolve them through the default translator.
  const { t: tLabel } = useTranslation()
  // `defaultValues` only: the orchestrator unmounts this step whenever the
  // wizard leaves it (conditional render in `import-wizard.tsx`), so a fresh
  // mount already picks up the latest `initialValues` — a reactive `values`
  // resync option would fight the user's in-progress edits on every keystroke.
  const form = useForm<ImportConfigFormValues>({
    resolver: zodResolver(buildImportConfigSchema(globalFields, t)),
    defaultValues: withFieldDefaults(globalFields, initialValues),
  })

  const onSubmit = form.handleSubmit((values) => onNext(values))

  return (
    <Form {...form}>
          <form onSubmit={onSubmit} className="flex flex-col gap-4" noValidate>
            {globalFields.map((globalField) => (
              <FormField
                key={globalField.id}
                control={form.control}
                name={globalField.id as FieldPath<ImportConfigFormValues>}
                render={({ field }) => (
                  <FormItem>
                    <FormLabel required={globalField.required}>{tLabel(globalField.label)}</FormLabel>
                    <FormControl>
                      {globalField.for_select_resource ? (
                        <AsyncPaginatedSelect
                          resource={globalField.for_select_resource}
                          value={(field.value as number | null) ?? null}
                          onChange={(next) => field.onChange(next)}
                          labels={{
                            placeholder: t('config.select.placeholder'),
                            searchPlaceholder: t('config.select.searchPlaceholder'),
                            empty: t('config.select.empty'),
                            error: t('config.select.error'),
                            clearLabel: t('config.select.clear'),
                            triggerLabel: tLabel(globalField.label),
                            retry: t('config.select.retry'),
                          }}
                        />
                      ) : (
                        <Input
                          type="number"
                          value={(field.value as number | null) ?? ''}
                          onChange={(event) =>
                            field.onChange(event.target.value === '' ? null : Number(event.target.value))
                          }
                        />
                      )}
                    </FormControl>
                    <FormMessage role="alert" />
                  </FormItem>
                )}
              />
            ))}

            <div className="flex justify-end">
              <Button type="submit">{t('config.continue')}</Button>
            </div>
          </form>
        </Form>
  )
}
