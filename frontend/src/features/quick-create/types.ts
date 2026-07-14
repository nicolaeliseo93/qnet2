import type { ComponentType, LazyExoticComponent } from 'react'
import type { RelationFieldRef } from '@/components/form/relation-select-field'

/**
 * Props every quick-create module form receives from `QuickCreateButton`
 * (spec 0028). Mirrors the shape shared by every `<X>Form` component
 * (`onSuccess`/`onCancel`), except `onSuccess` here is already projected to
 * the hydrated `{id, name}` relation ref the caller's select needs.
 */
export interface QuickCreateFormProps {
  /** Called with the ref of the record just created: {id, name}, already projected by the adapter. */
  onSuccess: (ref: RelationFieldRef) => void
  onCancel: () => void
}

/** A resource's quick-create wiring, resolved by `resolveQuickCreate`. */
export interface QuickCreateEntry {
  /** i18n key for the dialog title (reuses the module's existing `form.createTitle`). */
  titleKey: string
  /** i18n key for the dialog description (reuses the module's existing `form.createSubtitle`). */
  descriptionKey: string
  /** Permission required to show the "+", e.g. "sources.create". */
  permission: string
  /** The module's real create form, lazy-loaded to keep it out of the entry bundle (AC-013). */
  form: LazyExoticComponent<ComponentType<QuickCreateFormProps>>
}
