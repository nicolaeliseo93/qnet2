/**
 * A single backend-driven navigation node. The tree is already filtered by the
 * user's permissions server-side — the frontend only renders what it receives.
 */
export interface NavigationItem {
  key: string
  /** i18n key resolved by the frontend, e.g. "navigation.dashboard". */
  label: string
  /** Icon name mapped to a component, or null. */
  icon: string | null
  /** Target route, or null for pure grouping nodes. */
  route: string | null
  /**
   * 'item' = ordinary entry (leaf, or collapsible parent when it has children).
   * 'section' = labeled separator whose children render as flat sibling links.
   */
  type: 'item' | 'section'
  children: NavigationItem[]
}
