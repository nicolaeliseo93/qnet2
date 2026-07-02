import { useTranslation } from 'react-i18next'
import { useQueryClient } from '@tanstack/react-query'
import { toast } from 'sonner'
import { AvatarUpload } from '@/components/avatar-upload'
import { uploadMyAvatar, deleteMyAvatar } from '@/features/auth/api'
import { authKeys } from '@/features/auth/query-keys'
import { useAuth } from '@/features/auth/use-auth'

/**
 * Self-service avatar management for the settings page. Uploads/removes the
 * current user's avatar and updates the `me` cache so the whole app (sidebar,
 * etc.) reflects the change immediately, mirroring ProfileForm.
 */
export function AvatarForm() {
  const { t } = useTranslation()
  const { user } = useAuth()
  const queryClient = useQueryClient()

  if (!user) {
    return null
  }

  const handleUpload = async (file: File) => {
    const updated = await uploadMyAvatar(file)
    queryClient.setQueryData(authKeys.me, updated)
    toast.success(t('settings.avatarUpdated'))
  }

  const handleRemove = async () => {
    const updated = await deleteMyAvatar()
    queryClient.setQueryData(authKeys.me, updated)
    toast.success(t('settings.avatarRemoved'))
  }

  return (
    <AvatarUpload
      mode="immediate"
      name={user.name}
      avatarUrl={user.avatar_url}
      onUpload={handleUpload}
      onRemove={handleRemove}
    />
  )
}
