import { useTranslation } from 'react-i18next'
import { History, Loader2 } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { Form } from '@/components/ui/form'
import { FormSection } from '@/components/form-section'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { ActivityLogSection } from '@/features/activity-log/activity-log-section'
import { ResourcePermissionsProvider, useResourcePermissions } from '@/features/authorization/permissions'
import { NotesSection } from '@/features/notes/notes-section'
import { fetchRequestWorkPanel } from '@/features/request-management/api'
import { requestManagementKeys } from '@/features/request-management/query-keys'
import { RequestCallbackSection } from '@/features/request-management/request-callback-section'
import { RequestClientSection } from '@/features/request-management/request-client-section'
import { RequestDynamicFields } from '@/features/request-management/request-dynamic-fields'
import { RequestWorkContext } from '@/features/request-management/request-work-context'
import { RequestWorkflowStatusField } from '@/features/request-management/request-workflow-status-field'
import { useRequestWorkForm } from '@/features/request-management/use-request-work-form'
import { REQUEST_MANAGEMENT_DOMAIN } from '@/features/request-management/types'
import type { RequestWorkPanelWithPermissions } from '@/features/request-management/types'

/**
 * DOM id bridging the sticky submit button to the RHF `<form>` (spec 0052
 * F1b): `<NotesSection>` owns its own native `<form>` (composer) and cannot
 * sit inside this one — nested `<form>` elements are invalid HTML and make
 * submit/Enter-key behaviour browser-dependent. The button below stays
 * `type="submit"` via the HTML `form` attribute instead of DOM nesting.
 */
const REQUEST_WORK_FORM_ID = 'request-work-form'

/** Props shape matches the module registry's `ModuleDetailScreenProps` (spec 0042), so this mounts as-is as the module's `DetailScreen`. */
interface RequestWorkPanelScreenProps {
  id: number
}

/** Loading placeholder mirroring the panel's real section layout (spec 0049, mirrors `OpportunityFormSkeleton`). */
export function RequestWorkPanelSkeleton() {
  return (
    <div className="flex flex-col gap-4 p-4" aria-hidden="true">
      {[0, 1, 2, 3].map((section) => (
        <div key={section} className="rounded-xl border bg-card shadow-sm">
          <div className="flex items-center gap-3 border-b px-4 py-3.5">
            <Skeleton className="size-9 rounded-lg" />
            <div className="flex flex-col gap-1.5">
              <Skeleton className="h-3.5 w-40" />
              <Skeleton className="h-3 w-56" />
            </div>
          </div>
          <div className="flex flex-col gap-4 p-4">
            <Skeleton className="h-9 w-full" />
            <div className="grid gap-3 sm:grid-cols-2">
              <Skeleton className="h-9 w-full" />
              <Skeleton className="h-9 w-full" />
            </div>
          </div>
        </div>
      ))}
    </div>
  )
}

/**
 * Content-only work-panel screen (spec 0049 AC-061), the module's
 * `DetailScreen` (spec 0042 registry): fetches the panel fresh on every mount
 * (`useEntityDetail`, same fresh-on-open contract as every other entity
 * card/edit form), then renders the read-only context, the contact
 * verification blocks, the dynamic Attribute fields and the working-state
 * control, wrapped in the actor's `ResourcePermissions` so every field's
 * gating comes from the same server-derived source as everywhere else.
 */
export function RequestWorkPanelScreen({ id }: RequestWorkPanelScreenProps) {
  const { t } = useTranslation()
  const { data: panel, isLoading, isError, refetch } = useEntityDetail(
    requestManagementKeys.panel(id),
    () => fetchRequestWorkPanel(id),
  )

  if (isError) {
    return (
      <div className="flex flex-col items-start gap-3 p-4">
        <p className="text-sm text-destructive" role="alert">
          {t('requestManagement.workPanel.loadError', { defaultValue: 'Could not load the record.' })}
        </p>
        <Button variant="outline" size="sm" onClick={() => refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (isLoading || !panel) {
    return <RequestWorkPanelSkeleton />
  }

  return (
    <ResourcePermissionsProvider permissions={panel.permissions}>
      <RequestWorkPanelBody panel={panel} />
    </ResourcePermissionsProvider>
  )
}

interface RequestWorkPanelBodyProps {
  panel: RequestWorkPanelWithPermissions
}

function RequestWorkPanelBody({ panel }: RequestWorkPanelBodyProps) {
  const { t } = useTranslation()
  const { canAction, canResource } = useResourcePermissions()
  const canUpdate = canResource('update')
  const canViewActivity = canAction('view_activity')
  const { form, onSubmit, serverError, isSubmitting } = useRequestWorkForm(panel)

  return (
    <div className="flex flex-1 flex-col overflow-y-auto">
      <div className="flex flex-col gap-4 p-4">
        <Form {...form}>
          {/* `display: contents`: this native `<form>` only scopes the HTML submit
              boundary, it must not become an extra flex box in the stack below. */}
          {/* Section order = the operator's data-entry order: who the client
              is (anagrafica), what the request needs (dynamic fields), how the
              work is tracked (callback + working state). The read-only
              commercial context sits above as a compact header. */}
          <form id={REQUEST_WORK_FORM_ID} onSubmit={onSubmit} className="contents" noValidate>
            <RequestWorkContext panel={panel} />

            <RequestClientSection control={form.control} />

            <RequestDynamicFields control={form.control} attributes={panel.applicable_attributes} />

            <RequestCallbackSection control={form.control} />

            <RequestWorkflowStatusField control={form.control} statuses={panel.workflow_statuses} />
          </form>
        </Form>

        {/* Own authorization (spec 0052 D-6): shown to any actor who can read the
            record, independent of `canUpdate`. Its composer has its own native
            `<form>`, so it cannot nest inside the one above (see REQUEST_WORK_FORM_ID). */}
        <NotesSection entityType={REQUEST_MANAGEMENT_DOMAIN} entityId={panel.id} />

        {/* Read-only history of everything the panel writes — the request's own
            operative changes, the notes, the uploaded documents and the client
            anagraphic block — gated by the server-derived `view_activity`
            action, collapsed by default so it never pushes the work above it
            out of view. */}
        {canViewActivity && (
          <FormSection
            icon={History}
            title={t('activityLog.title')}
            collapsible
            defaultOpen={false}
          >
            <ActivityLogSection resource={REQUEST_MANAGEMENT_DOMAIN} id={panel.id} />
          </FormSection>
        )}

        {serverError && (
          <div
            role="alert"
            className="flex items-start gap-2 rounded-lg border border-destructive/30 bg-destructive/5 px-3 py-2.5 text-sm font-medium text-destructive"
          >
            {serverError}
          </div>
        )}

        {canUpdate && (
          <div className="sticky bottom-0 z-10 -mx-4 -mb-4 mt-auto flex justify-end gap-2 border-t bg-background/95 px-4 py-3 backdrop-blur supports-[backdrop-filter]:bg-background/80">
            <Button type="submit" form={REQUEST_WORK_FORM_ID} disabled={isSubmitting || !form.formState.isDirty}>
              {isSubmitting && <Loader2 className="size-4 animate-spin" aria-hidden="true" />}
              {isSubmitting
                ? t('requestManagement.workPanel.saving', { defaultValue: 'Saving…' })
                : t('requestManagement.workPanel.save', { defaultValue: 'Save' })}
            </Button>
          </div>
        )}
      </div>
    </div>
  )
}
