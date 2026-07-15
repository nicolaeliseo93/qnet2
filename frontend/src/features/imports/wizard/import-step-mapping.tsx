import { useMemo } from 'react'
import { Controller, useForm, type FieldPath } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { AlertTriangle } from 'lucide-react'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Card, CardContent } from '@/components/ui/card'
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import {
  buildImportMappingSchema,
  type ImportMappingFormValues,
} from '@/features/imports/wizard/import-mapping-schema'
import { computeMappingSignals } from '@/features/imports/wizard/mapping-signals'
import { EXTRA_TARGET, IGNORE_TARGET } from '@/features/imports/wizard/types'
import type { DetectedColumn, ImportRunDetail } from '@/features/imports/wizard/types'

/** Stable empty fallback so the `columns` useMemo dependency keeps its identity. */
const EMPTY_COLUMNS: DetectedColumn[] = []

export interface ImportStepMappingProps {
  run: ImportRunDetail | null
  initialMapping: Record<string, string>
  initialDedupStrategy: string | null
  onBack: () => void
  onSubmit: (mapping: Record<string, string>, dedupStrategy: string) => void
  isSubmitting: boolean
  submitError: string | null
}

/**
 * Column mapping step (AC-022): one target select per detected file column
 * (a mappable field, "ignore", or "extra field"), pre-populated from the
 * run's auto-mapping suggestion / already-persisted mapping. Submitting this
 * step is what actually calls `PUT .../configure` (mapping + the config
 * step's values, bundled — see `use-import-wizard.ts`).
 */
export function ImportStepMapping({
  run,
  initialMapping,
  initialDedupStrategy,
  onBack,
  onSubmit,
  isSubmitting,
  submitError,
}: ImportStepMappingProps) {
  const { t } = useTranslation('importWizard')
  // Field labels come from the backend as default-namespace i18n keys
  // (`imports.leads.fields.*`), so they resolve through the default translator,
  // not the `importWizard` one.
  const { t: tLabel } = useTranslation()
  const fields = run?.fields ?? []
  const columns = run?.detected_columns ?? EMPTY_COLUMNS
  const dedupModes = run?.dedup_modes ?? []

  // Every detected column MUST have a string form value: a column the
  // auto-mapping did not cover is absent from `initialMapping`, and without a
  // default its RHF value stays `undefined` — which `z.record(z.string(),
  // z.string())` rejects, blocking submit with an error that has no visible
  // FormMessage (the "dead button"). Default an unmapped column to
  // IGNORE_TARGET, matching what its Select already displays.
  const completeMapping = useMemo(() => {
    const result: Record<string, string> = {}
    for (const column of columns) {
      result[column.key] = initialMapping[column.key] ?? IGNORE_TARGET
    }
    return result
  }, [columns, initialMapping])

  // `defaultValues` only: the orchestrator unmounts this step whenever the
  // wizard leaves it, so a fresh mount already picks up the latest mapping —
  // see the same note in `import-step-config.tsx`.
  const form = useForm<ImportMappingFormValues>({
    resolver: zodResolver(buildImportMappingSchema(fields, t)),
    defaultValues: { mapping: completeMapping, dedup_strategy: initialDedupStrategy ?? dedupModes[0] ?? '' },
  })

  const mappingValues = form.watch('mapping')
  const signals = computeMappingSignals(columns, fields, mappingValues)

  const handleSubmit = form.handleSubmit((values) => onSubmit(values.mapping, values.dedup_strategy))

  return (
    <Card>
      <CardContent className="pt-4">
        <Form {...form}>
          <form onSubmit={handleSubmit} className="flex flex-col gap-4" noValidate>
            <div className="flex flex-col gap-3">
              {columns.map((column) => {
                const fieldName = `mapping.${column.key}` as FieldPath<ImportMappingFormValues>
                const currentTarget = mappingValues[column.key] ?? IGNORE_TARGET
                const isConflict = signals.conflictFieldIds.has(currentTarget)

                return (
                  <div
                    key={`${column.index}-${column.name}`}
                    className="flex flex-col gap-1.5 border-b pb-3 last:border-b-0 sm:flex-row sm:items-center sm:justify-between"
                  >
                    <div className="flex flex-1 flex-wrap items-center gap-2">
                      <span className="text-sm font-medium">{column.name}</span>
                      {column.duplicate ? (
                        <Badge variant="outline">{t('mapping.badges.duplicateColumn')}</Badge>
                      ) : null}
                      {isConflict ? (
                        <Badge variant="destructive">{t('mapping.badges.conflict')}</Badge>
                      ) : null}
                    </div>
                    <Controller
                      control={form.control}
                      name={fieldName}
                      render={({ field }) => (
                        <Select value={(field.value as string) || IGNORE_TARGET} onValueChange={field.onChange}>
                          <SelectTrigger className="w-full sm:w-64" aria-label={t('mapping.targetHeader')}>
                            <SelectValue />
                          </SelectTrigger>
                          <SelectContent>
                            <SelectItem value={IGNORE_TARGET}>{t('mapping.ignore')}</SelectItem>
                            <SelectItem value={EXTRA_TARGET}>{t('mapping.extra')}</SelectItem>
                            {fields.map((mappableField) => (
                              <SelectItem key={mappableField.id} value={mappableField.id}>
                                {mappableField.required ? `${tLabel(mappableField.label)} *` : tLabel(mappableField.label)}
                              </SelectItem>
                            ))}
                          </SelectContent>
                        </Select>
                      )}
                    />
                  </div>
                )
              })}
            </div>

            {signals.requiredMissing.length > 0 ? (
              <p className="flex items-center gap-2 text-sm text-destructive" role="alert">
                <AlertTriangle className="size-4 shrink-0" aria-hidden="true" />
                {t('mapping.badges.requiredMissing', {
                  fields: signals.requiredMissing
                    .map((fieldId) => {
                      const field = fields.find((candidate) => candidate.id === fieldId)
                      return field ? tLabel(field.label) : fieldId
                    })
                    .join(', '),
                })}
              </p>
            ) : null}

            <FormField
              control={form.control}
              name="dedup_strategy"
              render={({ field }) => (
                <FormItem>
                  <FormLabel required>{t('mapping.duplicateStrategy')}</FormLabel>
                  <Select value={field.value} onValueChange={field.onChange}>
                    <FormControl>
                      <SelectTrigger className="w-full sm:w-64">
                        <SelectValue placeholder={t('config.select.placeholder')} />
                      </SelectTrigger>
                    </FormControl>
                    <SelectContent>
                      {dedupModes.map((mode) => (
                        <SelectItem key={mode} value={mode}>
                          {t(`mapping.dedupModes.${mode}`, { defaultValue: mode })}
                        </SelectItem>
                      ))}
                    </SelectContent>
                  </Select>
                  <FormMessage role="alert" />
                </FormItem>
              )}
            />

            {submitError ? (
              <p className="text-sm text-destructive" role="alert">
                {submitError}
              </p>
            ) : null}

            <div className="flex justify-between">
              <Button type="button" variant="outline" onClick={onBack}>
                {t('config.back')}
              </Button>
              <Button type="submit" disabled={isSubmitting}>
                {isSubmitting ? t('mapping.submitting') : t('mapping.submit')}
              </Button>
            </div>
          </form>
        </Form>
      </CardContent>
    </Card>
  )
}
