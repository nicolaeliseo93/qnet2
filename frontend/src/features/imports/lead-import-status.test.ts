import { describe, expect, it } from 'vitest'
import { isConcludedImportRun, isResumableImportRun } from '@/features/imports/lead-import-status'

describe('isConcludedImportRun / isResumableImportRun', () => {
  it.each(['completed', 'failed'])('treats "%s" as concluded, not resumable', (status) => {
    expect(isConcludedImportRun(status)).toBe(true)
    expect(isResumableImportRun(status)).toBe(false)
  })

  it.each(['analyzing', 'configuring', 'staging', 'reviewing', 'processing'])(
    'treats "%s" as resumable, not concluded',
    (status) => {
      expect(isConcludedImportRun(status)).toBe(false)
      expect(isResumableImportRun(status)).toBe(true)
    },
  )
})
