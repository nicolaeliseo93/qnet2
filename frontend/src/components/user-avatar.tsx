import { Avatar, AvatarFallback, AvatarImage } from '@/components/ui/avatar'
import { avatarColor } from '@/components/avatar-color'

interface UserAvatarProps {
  /** Display name: drives the initials fallback and the image alt text. */
  name: string
  /** Avatar image source (data: URI or URL); falls back to initials when null. */
  src?: string | null
  /** Forwarded to the underlying `Avatar` root; defaults to `"default"`. */
  size?: 'default' | 'sm' | 'lg'
  /** Extra classes forwarded to the avatar root (e.g. sizing, rounding). */
  className?: string
}

/**
 * Single source of truth for rendering a user's avatar across the app — sidebar,
 * tables, forms, anywhere a user is shown. Renders the image when available and
 * the name's initials otherwise. Change the avatar's look or behaviour HERE and
 * it propagates everywhere; do not re-compose Avatar/AvatarImage/AvatarFallback
 * ad hoc elsewhere.
 */
export function UserAvatar({ name, src, size, className }: UserAvatarProps) {
  const color = avatarColor(name)
  return (
    // Keying by image-vs-fallback remounts the Radix root when the avatar is
    // removed, resetting its internal image-loading status so the initials
    // fallback shows immediately instead of leaving a blank circle.
    <Avatar key={src ? 'image' : 'fallback'} size={size} className={className}>
      {src && <AvatarImage src={src} alt={name} />}
      <AvatarFallback
        className="font-medium"
        style={{ backgroundColor: color.bg, color: color.fg }}
      >
        {initials(name)}
      </AvatarFallback>
    </Avatar>
  )
}

/** Derives up to two uppercase initials from a display name. */
function initials(name: string): string {
  return name
    .split(' ')
    .filter(Boolean)
    .slice(0, 2)
    .map((part) => part[0]?.toUpperCase() ?? '')
    .join('')
}
