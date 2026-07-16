import { useCallback } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate, useParams } from 'react-router-dom'
import { useQueryClient } from '@tanstack/react-query'
import { Button } from '@/components/ui/button'
import { PageHeader } from '@/components/page-header'
import { Can } from '@/features/auth/can'
import { useEntityDetail } from '@/hooks/use-entity-detail'
import { fetchLead, leadDetailQueryKey } from '@/features/leads/api'
import { LeadForm, LeadFormSkeleton } from '@/features/leads/lead-form'
import type { LeadDetail } from '@/features/leads/types'
import { useBreadcrumbTitle } from '@/routes/breadcrumb-title'
import { parseEntityId } from '@/routes/entity-id'
import NotFoundPage from '@/pages/not-found-page'

/**
 * Dedicated create/edit page of a lead (spec 0024, mirrors Campaigns). One
 * page serves both routes: `/leads/new` (no `:id`) and `/leads/:id/edit`. In
 * edit mode the fresh, re-authorized detail is fetched before the form
 * mounts, so the partial PATCH starts from authoritative values.
 */
export default function LeadFormPage() {
  const { t } = useTranslation()
  const { id } = useParams()
  const navigate = useNavigate()
  const queryClient = useQueryClient()

  const isEdit = id !== undefined
  const leadId = parseEntityId(id)

  const {
    data: lead,
    isLoading,
    isError,
    refetch,
  } = useEntityDetail(leadDetailQueryKey(leadId), () => fetchLead(leadId as number), leadId !== null)

  useBreadcrumbTitle(`/leads/${id}`, lead?.referent?.name)

  const onSuccess = useCallback(
    (saved: LeadDetail) => {
      queryClient.invalidateQueries({ queryKey: leadDetailQueryKey(saved.id) })
      void navigate(`/leads/${saved.id}`)
    },
    [navigate, queryClient],
  )

  const onCancel = useCallback(() => {
    void navigate(isEdit ? `/leads/${leadId}` : '/leads')
  }, [isEdit, navigate, leadId])

  if (isEdit && leadId === null) {
    return <NotFoundPage />
  }

  return (
    <Can
      permission={isEdit ? 'leads.update' : 'leads.create'}
      fallback={<p className="text-sm text-muted-foreground">{t('leads.forbidden')}</p>}
    >
      <div className="flex flex-1 flex-col gap-4">
        <PageHeader />

        <div className="flex flex-1 flex-col overflow-hidden rounded-lg border bg-card">
          <header className="flex flex-col gap-1 border-b px-4 py-3">
            <h2 className="text-base font-semibold">
              {t(isEdit ? 'leads.form.editTitle' : 'leads.form.createTitle')}
            </h2>
            <p className="text-sm text-muted-foreground">
              {t(isEdit ? 'leads.form.editSubtitle' : 'leads.form.createSubtitle')}
            </p>
          </header>

          {isError ? (
            <div className="flex flex-col items-start gap-3 p-4">
              <p className="text-sm text-destructive" role="alert">
                {t('leads.detail.loadError')}
              </p>
              <Button variant="outline" size="sm" onClick={() => refetch()}>
                {t('common.retry')}
              </Button>
            </div>
          ) : isEdit && (isLoading || !lead) ? (
            <LeadFormSkeleton />
          ) : (
            <LeadForm
              mode={lead ? { type: 'edit', lead } : { type: 'create' }}
              onSuccess={onSuccess}
              onCancel={onCancel}
            />
          )}
        </div>
      </div>
    </Can>
  )
}
