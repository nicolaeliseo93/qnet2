import { OPEN_MODE_MODAL, type ModuleRegistryEntry } from '@/features/modules/types'
import { OPPORTUNITIES_DOMAIN } from '@/features/opportunities/api'
import {
  OpportunityDetailScreen,
  OpportunityFormScreen,
} from '@/features/opportunities/opportunity-screens'
import { ProjectDetailScreen, ProjectFormScreen } from '@/features/projects/project-screens'
import { CampaignDetailScreen, CampaignFormScreen } from '@/features/campaigns/campaign-screens'
import {
  LeadDetailPageActions,
  LeadDetailScreen,
  LeadFormScreen,
} from '@/features/leads/lead-screens'

/** Domain slugs — the same kebab-case keys as `config/tables.php`/`TableRegistry` server-side. */
export const PROJECTS_DOMAIN = 'projects'
export const CAMPAIGNS_DOMAIN = 'campaigns'
export const LEADS_DOMAIN = 'leads'

/**
 * Single source of truth of every module whose open mode (modal vs
 * dedicated page) the user can control (spec 0042). Wave 0 registers the 4
 * reference modules, which already own both mount points today; later waves
 * append entries here without touching `useModuleOpener`, the generic
 * `ModuleDetailPage`/`ModuleFormPage` hosts, or the settings UI. `import-runs`
 * and `migrations` are intentionally never registered (non-CRUD flows, out
 * of scope — spec 0042 `<out>`).
 */
export const MODULE_REGISTRY: readonly ModuleRegistryEntry[] = [
  {
    domain: PROJECTS_DOMAIN,
    basePath: '/projects',
    defaultMode: OPEN_MODE_MODAL,
    labelKey: 'navigation.projects',
    DetailScreen: ProjectDetailScreen,
    FormScreen: ProjectFormScreen,
  },
  {
    domain: CAMPAIGNS_DOMAIN,
    basePath: '/campaigns',
    defaultMode: OPEN_MODE_MODAL,
    labelKey: 'navigation.campaigns',
    DetailScreen: CampaignDetailScreen,
    FormScreen: CampaignFormScreen,
  },
  {
    domain: LEADS_DOMAIN,
    basePath: '/leads',
    defaultMode: OPEN_MODE_MODAL,
    labelKey: 'navigation.leads',
    DetailScreen: LeadDetailScreen,
    FormScreen: LeadFormScreen,
    DetailPageActions: LeadDetailPageActions,
  },
  {
    domain: OPPORTUNITIES_DOMAIN,
    basePath: '/opportunities',
    defaultMode: OPEN_MODE_MODAL,
    labelKey: 'navigation.opportunities',
    DetailScreen: OpportunityDetailScreen,
    FormScreen: OpportunityFormScreen,
  },
]

/** Looks up a registered module by its domain slug, or `undefined` if not (yet) registered. */
export function getModuleRegistryEntry(domain: string): ModuleRegistryEntry | undefined {
  return MODULE_REGISTRY.find((entry) => entry.domain === domain)
}
