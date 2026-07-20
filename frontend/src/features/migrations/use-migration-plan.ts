import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { useTranslation } from 'react-i18next'
import { fetchMigrationPlan, saveMigrationPlan } from '@/features/migrations/api'
import { migrationKeys } from '@/features/migrations/query-keys'
import { resolveMigrationErrorMessage } from '@/features/migrations/resolve-error-message'
import type { MigrationPlan, MigrationPlanInput } from '@/features/migrations/types'

/**
 * Reads and persists the mass-import plan (spec 0046): which sources the
 * "Import all" run includes and in what order. The save response is the
 * reconciled plan, so it becomes the cache's new source of truth. All business
 * logic lives here; the panel component only renders from the returned state.
 */
export function useMigrationPlan() {
  const { t } = useTranslation('migrations')
  const queryClient = useQueryClient()

  const planQuery = useQuery({
    queryKey: migrationKeys.plan,
    queryFn: fetchMigrationPlan,
  })

  const saveMutation = useMutation({
    mutationFn: (sources: MigrationPlanInput[]) => saveMigrationPlan(sources),
    onSuccess: (plan) => {
      queryClient.setQueryData<MigrationPlan>(migrationKeys.plan, plan)
    },
  })

  return {
    plan: planQuery.data,
    isLoading: planQuery.isLoading,
    isError: planQuery.isError,
    refetch: planQuery.refetch,
    save: saveMutation.mutate,
    isSaving: saveMutation.isPending,
    isSaved: saveMutation.isSuccess,
    saveError: saveMutation.isError
      ? resolveMigrationErrorMessage(saveMutation.error, t)
      : null,
  }
}
