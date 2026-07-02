/**
 * Pure client-side validation for avatar uploads, mirrored from the backend
 * contract (image type + max 10 MB). Kept framework-free so it is trivially
 * unit-testable; the component maps the returned reason to a localized message.
 */

/** Accepted image MIME types, mirrored from the backend contract. */
export const ACCEPTED_AVATAR_TYPES = [
  'image/jpeg',
  'image/png',
  'image/gif',
  'image/webp',
]

/** Maximum avatar size in bytes (10 MB), mirrored from the backend contract. */
export const MAX_AVATAR_SIZE_BYTES = 10 * 1024 * 1024

/** Why an avatar file was rejected, or null when it is valid. */
export type AvatarValidationError = 'invalidType' | 'tooLarge' | null

export function validateAvatarFile(file: File): AvatarValidationError {
  if (!ACCEPTED_AVATAR_TYPES.includes(file.type)) {
    return 'invalidType'
  }
  if (file.size > MAX_AVATAR_SIZE_BYTES) {
    return 'tooLarge'
  }
  return null
}
