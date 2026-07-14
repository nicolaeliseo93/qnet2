import { useState } from 'react'

/** The two ways the projects page can render its list (spec 0026 AC-008). */
export type ProjectsView = 'grid' | 'table'

const STORAGE_KEY = 'projects.view'
const DEFAULT_VIEW: ProjectsView = 'grid'

function isProjectsView(value: string | null): value is ProjectsView {
  return value === 'grid' || value === 'table'
}

function readStoredView(): ProjectsView {
  if (typeof window === 'undefined') {
    return DEFAULT_VIEW
  }
  try {
    const stored = window.localStorage.getItem(STORAGE_KEY)
    return isProjectsView(stored) ? stored : DEFAULT_VIEW
  } catch {
    return DEFAULT_VIEW
  }
}

/**
 * Persists the user's grid/table choice for the projects page across reloads
 * (spec 0026 AC-008). Grid is the default when nothing was stored yet.
 */
export function useProjectsViewPreference() {
  const [view, setViewState] = useState<ProjectsView>(readStoredView)

  const setView = (next: ProjectsView) => {
    setViewState(next)
    try {
      window.localStorage.setItem(STORAGE_KEY, next)
    } catch {
      // Storage can be unavailable (private mode, quota): the toggle still works for this session.
    }
  }

  return { view, setView }
}
