import { useMemo } from 'react'
import { useSearchParams } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import { Columns3, FileCheck2, FileUp, ListChecks, Settings2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Card, CardContent, CardHeader, CardTitle } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'
import { Stepper, type StepperStep } from '@/components/ui/stepper'
// Side effect: registers the `importWizard` i18next namespace (see the
// module doc comment) before this component's own `t()` calls run.
import '@/features/imports/wizard/i18n'
import { ImportStepConfig } from '@/features/imports/wizard/import-step-config'
import { ImportStepMapping } from '@/features/imports/wizard/import-step-mapping'
import { ImportStepReview } from '@/features/imports/wizard/import-step-review'
import { ImportStepSummary } from '@/features/imports/wizard/import-step-summary'
import { ImportStepUpload } from '@/features/imports/wizard/import-step-upload'
import { useImportWizard } from '@/features/imports/wizard/use-import-wizard'

export interface ImportWizardProps {
  /** Resource key selecting the backend `ImportDefinition`, e.g. `leads`. */
  domain: string
}

/** URL query param the wizard resumes an in-progress run from (AC-025). */
const RUN_ID_PARAM = 'runId'

function parseRunId(value: string | null): number | null {
  if (!value) return null
  const parsed = Number(value)
  return Number.isInteger(parsed) && parsed > 0 ? parsed : null
}

/**
 * Full-page wizard orchestrator (spec 0033): mounts the 5-step stepper and
 * routes to the step matching the run's server-authoritative status (see
 * `useImportWizard`). The review/summary steps (`ImportStepReview`/
 * `ImportStepSummary`) are the integration points for lanes F2/F3 — this
 * component only wires their props, it owns none of their content.
 */
export function ImportWizard({ domain }: ImportWizardProps) {
  const { t } = useTranslation('importWizard')
  const title = t('page.title')
  const [searchParams, setSearchParams] = useSearchParams()
  const initialRunId = parseRunId(searchParams.get(RUN_ID_PARAM))

  const wizard = useImportWizard({
    domain,
    initialRunId,
    onRunCreated: (runId) => {
      const next = new URLSearchParams(searchParams)
      next.set(RUN_ID_PARAM, String(runId))
      setSearchParams(next, { replace: true })
    },
  })

  const steps: StepperStep[] = useMemo(
    () => [
      { key: 'upload', label: t('stepper.upload'), icon: FileUp },
      { key: 'config', label: t('stepper.config'), icon: Settings2 },
      { key: 'mapping', label: t('stepper.mapping'), icon: Columns3 },
      { key: 'review', label: t('stepper.review'), icon: ListChecks },
      { key: 'summary', label: t('stepper.summary'), icon: FileCheck2 },
    ],
    [t],
  )

  if (wizard.isRunLoading) {
    return (
      <div className="flex flex-col gap-4" aria-hidden="true">
        <Skeleton className="h-8 w-full" />
        <Skeleton className="h-48 w-full" />
      </div>
    )
  }

  if (wizard.isRunError) {
    return (
      <div className="flex flex-col items-start gap-3">
        <p className="text-sm text-destructive" role="alert">
          {t('page.loadError')}
        </p>
        <Button type="button" variant="outline" size="sm" onClick={() => wizard.refetchRun()}>
          {t('config.select.retry')}
        </Button>
      </div>
    )
  }

  return (
    <Card>
      <CardHeader className="gap-4">
        <CardTitle className="text-base">{title}</CardTitle>
        <Stepper steps={steps} currentStep={wizard.currentStep} onStepClick={wizard.goToStep} />
      </CardHeader>
      <CardContent className="flex flex-col gap-4">
        {wizard.currentStep === 0 ? (
        <ImportStepUpload
          run={wizard.run}
          isUploading={wizard.isUploading}
          uploadError={wizard.uploadError}
          onUpload={wizard.upload}
          onContinue={wizard.advanceFromUpload}
        />
      ) : null}

      {wizard.currentStep === 1 ? (
        <ImportStepConfig
          globalFields={wizard.run?.global_fields ?? []}
          initialValues={wizard.configValues}
          onNext={wizard.submitConfig}
        />
      ) : null}

      {wizard.currentStep === 2 ? (
        <ImportStepMapping
          run={wizard.run}
          initialMapping={wizard.mappingValues}
          initialDedupStrategy={wizard.dedupStrategy}
          onBack={() => wizard.goToStep(1)}
          onSubmit={wizard.submitMapping}
          isSubmitting={wizard.isConfiguring}
          submitError={wizard.configureError}
        />
      ) : null}

      {wizard.currentStep === 3 ? (
        <ImportStepReview domain={domain} run={wizard.run} onContinue={wizard.advanceFromReview} />
      ) : null}

      {wizard.currentStep === 4 ? (
        <ImportStepSummary
          domain={domain}
          run={wizard.run}
          onConfirm={wizard.confirm}
          isConfirming={wizard.isConfirming}
          confirmError={wizard.confirmError}
        />
      ) : null}
      </CardContent>
    </Card>
  )
}
