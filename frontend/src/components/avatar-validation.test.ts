import { describe, expect, it } from 'vitest'
import {
  MAX_AVATAR_SIZE_BYTES,
  validateAvatarFile,
} from './avatar-validation'

/** Builds a File of the given type and size without allocating real bytes. */
function fakeFile(type: string, size: number): File {
  const file = new File(['x'], 'avatar', { type })
  Object.defineProperty(file, 'size', { value: size })
  return file
}

describe('validateAvatarFile', () => {
  it('accepts a supported image within the size limit', () => {
    expect(validateAvatarFile(fakeFile('image/png', 1024))).toBeNull()
    expect(validateAvatarFile(fakeFile('image/jpeg', 1024))).toBeNull()
    expect(validateAvatarFile(fakeFile('image/gif', 1024))).toBeNull()
    expect(validateAvatarFile(fakeFile('image/webp', 1024))).toBeNull()
  })

  it('rejects an unsupported type', () => {
    expect(validateAvatarFile(fakeFile('application/pdf', 1024))).toBe(
      'invalidType',
    )
    expect(validateAvatarFile(fakeFile('image/svg+xml', 1024))).toBe(
      'invalidType',
    )
  })

  it('rejects a file over the size limit', () => {
    expect(
      validateAvatarFile(fakeFile('image/png', MAX_AVATAR_SIZE_BYTES + 1)),
    ).toBe('tooLarge')
  })

  it('accepts a file exactly at the size limit', () => {
    expect(
      validateAvatarFile(fakeFile('image/png', MAX_AVATAR_SIZE_BYTES)),
    ).toBeNull()
  })
})
