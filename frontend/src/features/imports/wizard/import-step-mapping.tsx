import { useMemo, useState } from 'react'
import { Controller, useForm, type FieldPath } from 'react-hook-form'
import { zodResolver } from '@hookform/resolvers/zod'
import { useTranslation } from 'react-i18next'
import { Columns3, CopyCheck, Loader2, MoveRight } from 'lucide-react'
import { cn } from '@/lib/utils'
import { Badge } from '@/components/ui/badge'
import { Button } from '@/components/ui/button'
import { Form, FormControl, FormField, FormItem, FormLabel, FormMessage } from '@/components/ui/form'
import { Select, SelectContent, SelectItem, SelectTrigger, SelectValue } from '@/components/ui/select'
import { Separator } from '@/components/ui/separator'
import { FieldHint } from '@/components/field-hint'
import { StepAlert, StepSectionHeader } from '@/features/imports/wizard/wizard-ui'
import {
  buildImportMappingSchema,
  type ImportMappingFormValues,
} from '@/features/imports/wizard/import-mapping-schema'
import { computeMappingSignals } from '@/features/imports/wizard/mapping-signals'
import {
  MatchingTemplateBanner,
  SavedTemplatesMenu,
  SaveAsTemplateToggle,
} from '@/features/imports/wizard/mapping-template-controls'
import { EXTRA_TARGET, IGNORE_TARGET } from '@/features/imports/wizard/types'
import type { DetectedColumn, ImportRunDetail } from '@/features/imports/wizard/types'

/** Stable empty fallback so the `columns` useMemo dependency keeps its identity. */
const EMPTY_COLUMNS: DetectedColumn[] = []

export interface ImportStepMappingProps {
  run: ImportRunDetail | null
  initialMapping: Record<string, string>
  initialDedupStrategy: string | null
  onBack: () => void
  onSubmit: (
    mapping: Record<string, string>,
    dedupStrategy: string,
    /** Present only when the operator checked "save as template" (spec 0035 AC-011). */
    saveAsTemplate?: { name: string },
  ) => void
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

  // The form's `mapping` is keyed by column INDEX (a path-safe string), NOT by
  // the column key: a column name can contain a `.` (e.g. a survey-question
  // header ending in a period), and react-hook-form/lodash treat a `.` in a
  // field NAME as a nested path — so `mapping.<key with a dot>` would silently
  // drop/split the real key and submit a mismatched `column_mapping` the
  // backend rejects ("not part of this run's detected columns"). We map by
  // index here and translate back to the real column key on submit. Every
  // column also gets an explicit default (unmapped → IGNORE), so no value ever
  // stays `undefined` and blocks the (invisible) `z.record(z.string())` rule.
  const completeMapping = useMemo(() => {
    const result: Record<string, string> = {}
    for (const column of columns) {
      result[String(column.index)] = initialMapping[column.key] ?? IGNORE_TARGET
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

  const [saveAsTemplate, setSaveAsTemplate] = useState(false)
  const [templateName, setTemplateName] = useState('')

  const mappingValues = form.watch('mapping')
  // Re-key the index-based form values back to the real column keys for the
  // signals (which reason per column key). Computed inline every render (not
  // memoized): `form.watch` may hand back a stable reference on a change, and
  // a memo keyed on it would show stale conflict/required badges.
  const keyedMapping: Record<string, string> = {}
  for (const column of columns) {
    keyedMapping[column.key] = mappingValues[String(column.index)] ?? IGNORE_TARGET
  }
  const signals = computeMappingSignals(columns, fields, keyedMapping)

  const handleSubmit = form.handleSubmit((values) => {
    const realMapping: Record<string, string> = {}
    for (const column of columns) {
      realMapping[column.key] = values.mapping[String(column.index)] ?? IGNORE_TARGET
    }
    const trimmedTemplateName = templateName.trim()
    if (saveAsTemplate && trimmedTemplateName.length > 0) {
      onSubmit(realMapping, values.dedup_strategy, { name: trimmedTemplateName })
    } else {
      onSubmit(realMapping, values.dedup_strategy)
    }
  })

  // Applies a server-matched template's mapping/dedup strategy onto the form
  // (AC-009): every value stays a normal `setValue`, so it remains fully
  // editable afterward — this never submits or bypasses validation.
  const applyMatchingTemplate = () => {
    const template = run?.matching_template
    if (!template) return
    for (const column of columns) {
      const fieldName = `mapping.${column.index}` as FieldPath<ImportMappingFormValues>
      form.setValue(fieldName, template.column_mapping[column.key] ?? IGNORE_TARGET)
    }
    if (template.dedup_strategy) {
      form.setValue('dedup_strategy', template.dedup_strategy)
    }
  }

  return (
    <Form {...form}>
          <form onSubmit={handleSubmit} className="flex flex-col gap-4" noValidate>
            <StepSectionHeader
              icon={Columns3}
              title={t('mapping.title')}
              description={t('mapping.subtitle')}
              aside={run ? <SavedTemplatesMenu domain={run.resource} /> : null}
            />

            {run?.matching_template ? (
              <MatchingTemplateBanner
                templateName={run.matching_template.name}
                onApply={applyMatchingTemplate}
              />
            ) : null}

            <div className="overflow-hidden rounded-lg border">
              <div className="hidden gap-2 border-b bg-muted/40 px-3 py-2 sm:grid sm:grid-cols-[minmax(0,1fr)_1.25rem_16rem]">
                <span className="text-xs font-medium text-muted-foreground">{t('mapping.columnHeader')}</span>
                <span aria-hidden="true" />
                <span className="flex items-center gap-1 text-xs font-medium text-muted-foreground">
                  {t('mapping.targetHeader')}
                  <FieldHint text={t('mapping.hints.target')} label={t('mapping.hints.targetLabel')} />
                </span>
              </div>
              <div className="divide-y">
              {columns.map((column) => {
                const fieldName = `mapping.${column.index}` as FieldPath<ImportMappingFormValues>
                const currentTarget = mappingValues[String(column.index)] ?? IGNORE_TARGET
                const isConflict = signals.conflictFieldIds.has(currentTarget)
                const isMapped = currentTarget !== IGNORE_TARGET

                return (
                  <div
                    key={`${column.index}-${column.name}`}
                    className="flex flex-col gap-1.5 px-3 py-2.5 transition-colors hover:bg-muted/40 sm:grid sm:grid-cols-[minmax(0,1fr)_1.25rem_16rem] sm:items-center sm:gap-2"
                  >
                    <div className="flex min-w-0 flex-wrap items-center gap-2">
                      <span className="truncate text-sm font-medium">{column.name}</span>
                      {column.duplicate ? (
                        <Badge variant="outline">{t('mapping.badges.duplicateColumn')}</Badge>
                      ) : null}
                      {isConflict ? (
                        <Badge variant="destructive">{t('mapping.badges.conflict')}</Badge>
                      ) : null}
                    </div>
                    <MoveRight
                      aria-hidden="true"
                      className={cn(
                        'hidden size-4 transition-colors sm:block',
                        isMapped ? 'text-primary' : 'text-muted-foreground/40',
                      )}
                    />
                    <Controller
                      control={form.control}
                      name={fieldName}
                      render={({ field }) => (
                        <Select value={(field.value as string) || IGNORE_TARGET} onValueChange={field.onChange}>
                          <SelectTrigger
                            className={cn('w-full', !isMapped && 'text-muted-foreground')}
                            aria-label={t('mapping.targetHeader')}
                          >
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
            </div>

            {signals.requiredMissing.length > 0 ? (
              <StepAlert>
                {t('mapping.badges.requiredMissing', {
                  fields: signals.requiredMissing
                    .map((fieldId) => {
                      const field = fields.find((candidate) => candidate.id === fieldId)
                      return field ? tLabel(field.label) : fieldId
                    })
                    .join(', '),
                })}
              </StepAlert>
            ) : null}

            <Separator />

            <StepSectionHeader
              icon={CopyCheck}
              title={t('mapping.duplicateStrategy')}
              description={t('mapping.hints.dedup')}
            />

            <FormField
              control={form.control}
              name="dedup_strategy"
              render={({ field }) => (
                <FormItem>
                  <FormLabel required className="sr-only">
                    {t('mapping.duplicateStrategy')}
                  </FormLabel>
                  <Select value={field.value} onValueChange={field.onChange}>
                    <FormControl>
                      <SelectTrigger className="w-full sm:w-80">
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

            {submitError ? <StepAlert>{submitError}</StepAlert> : null}

            <SaveAsTemplateToggle
              checked={saveAsTemplate}
              onCheckedChange={setSaveAsTemplate}
              name={templateName}
              onNameChange={setTemplateName}
            />

            <Separator />

            <div className="flex justify-between">
              <Button type="button" variant="outline" onClick={onBack}>
                {t('config.back')}
              </Button>
              <Button type="submit" disabled={isSubmitting}>
                {isSubmitting ? <Loader2 className="animate-spin" aria-hidden="true" /> : null}
                {isSubmitting ? t('mapping.submitting') : t('mapping.submit')}
              </Button>
            </div>
          </form>
        </Form>
  )
}
