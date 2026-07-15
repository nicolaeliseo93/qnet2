import { describe, expect, it } from 'vitest'
import { computeMappingSignals } from '@/features/imports/wizard/mapping-signals'
import { IGNORE_TARGET } from '@/features/imports/wizard/types'
import type { DetectedColumn, ImportFieldDescriptor } from '@/features/imports/wizard/types'

const FIELDS: ImportFieldDescriptor[] = [
  { id: 'full_name', label: 'Full name', required: true, group: 'contact', type: 'string' },
  { id: 'email', label: 'Email', required: true, group: 'contact', type: 'string' },
  { id: 'phone', label: 'Phone', required: false, group: 'contact', type: 'string' },
]

const COLUMNS: DetectedColumn[] = [
  { key: 'Name', name: 'Name', index: 0, duplicate: false },
  { key: 'E-mail', name: 'E-mail', index: 1, duplicate: false },
  { key: 'E-mail#2', name: 'E-mail', index: 2, duplicate: true },
]

describe('computeMappingSignals', () => {
  it('flags every required field not targeted by any column', () => {
    const signals = computeMappingSignals(COLUMNS, FIELDS, { Name: IGNORE_TARGET })

    expect(signals.requiredMissing).toEqual(['full_name', 'email'])
  })

  it('does not flag a required field once a column targets it', () => {
    const signals = computeMappingSignals(COLUMNS, FIELDS, { Name: 'full_name', 'E-mail': 'email' })

    expect(signals.requiredMissing).toEqual([])
  })

  it('flags a field targeted by more than one column as a conflict', () => {
    const signals = computeMappingSignals(COLUMNS, FIELDS, { Name: 'email', 'E-mail': 'email' })

    expect(signals.conflictFieldIds.has('email')).toBe(true)
    expect(signals.conflictFieldIds.has('full_name')).toBe(false)
  })

  it('does not count ignore/extra targets toward a conflict', () => {
    const signals = computeMappingSignals(COLUMNS, FIELDS, { Name: IGNORE_TARGET, 'E-mail': IGNORE_TARGET })

    expect(signals.conflictFieldIds.size).toBe(0)
  })
})
