import { useMutation, useQuery, useQueryClient } from '@tanstack/react-query'
import { deleteMappingTemplate, listMappingTemplates } from '@/features/imports/wizard/api'
import { importWizardKeys } from '@/features/imports/wizard/query-keys'

/**
 * Loads the team-shared mapping templates of a domain (spec 0035): every
 * template any operator with import access saved, not just the actor's own.
 */
export function useMappingTemplates(domain: string) {
  return useQuery({
    queryKey: importWizardKeys.mappingTemplates(domain),
    queryFn: () => listMappingTemplates(domain),
  })
}

/** Deletes a saved mapping template (owner-only server-side). Invalidates the list on success. */
export function useDeleteMappingTemplate(domain: string) {
  const queryClient = useQueryClient()

  return useMutation({
    mutationFn: (mappingTemplateId: number) => deleteMappingTemplate(domain, mappingTemplateId),
    onSuccess: () => {
      queryClient.invalidateQueries({ queryKey: importWizardKeys.mappingTemplates(domain) })
    },
  })
}
