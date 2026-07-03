import { useEffect, useState, type ReactNode } from 'react'
import { useTranslation } from 'react-i18next'
import { Lock, UserRound, type LucideIcon } from 'lucide-react'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { Separator } from '@/components/ui/separator'
import { UserAvatar } from '@/components/user-avatar'
import { cn } from '@/lib/utils'
import { useAuth } from '@/features/auth/use-auth'
import { ProfileForm } from '@/features/auth/profile-form'
import { PasswordForm } from '@/features/auth/password-form'
import { AvatarForm } from '@/features/auth/avatar-form'

interface SectionMeta {
  id: string
  icon: LucideIcon
  titleKey: string
  descKey: string
}

// Section identity shared by the sticky index and the content anchors; the array
// order is the render + scroll order.
const SECTIONS: readonly SectionMeta[] = [
  {
    id: 'profile',
    icon: UserRound,
    titleKey: 'settings.profileTitle',
    descKey: 'settings.profileSubtitle',
  },
  {
    id: 'security',
    icon: Lock,
    titleKey: 'settings.passwordTitle',
    descKey: 'settings.passwordSubtitle',
  },
]

const SECTION_IDS = SECTIONS.map((section) => section.id)

/**
 * Connected-user settings. Two-column on desktop (a sticky identity + section
 * index rail beside the section cards), collapsing to a single column below lg.
 * Each section is a white card whose fields sit on a muted panel so the inputs
 * read as elevated, distinct surfaces (see FieldPanel).
 */
export default function SettingsPage() {
  const { t } = useTranslation()
  const { user } = useAuth()
  const activeSection = useActiveSection(SECTION_IDS)

  return (
    <div className="mx-auto flex w-full max-w-5xl flex-1 flex-col gap-6">
      <header className="flex flex-col gap-1">
        <h1 className="text-2xl font-semibold tracking-tight">
          {t('settings.title')}
        </h1>
        <p className="text-sm text-muted-foreground">{t('settings.subtitle')}</p>
      </header>

      <div className="grid gap-6 lg:grid-cols-[16rem_minmax(0,1fr)]">
        <aside className="flex flex-col gap-4 lg:sticky lg:top-6 lg:self-start">
          {user && (
            <Card className="items-center gap-3 py-5 text-center">
              <UserAvatar
                name={user.name}
                src={user.avatar_url}
                className="size-16"
              />
              <div className="flex min-w-0 flex-col px-4">
                <span className="truncate font-semibold leading-tight">
                  {user.name}
                </span>
                <span className="truncate text-sm text-muted-foreground">
                  {user.email}
                </span>
              </div>
            </Card>
          )}

          <nav
            aria-label={t('settings.sectionNavLabel')}
            className="flex flex-col gap-1"
          >
            {SECTIONS.map((section) => {
              const Icon = section.icon
              const isActive = activeSection === section.id
              return (
                <button
                  key={section.id}
                  type="button"
                  onClick={() => scrollToSection(section.id)}
                  aria-current={isActive ? 'true' : undefined}
                  className={cn(
                    'flex items-center gap-2.5 rounded-md px-3 py-2 text-sm font-medium transition-colors',
                    isActive
                      ? 'bg-accent text-accent-foreground'
                      : 'text-muted-foreground hover:bg-accent/50 hover:text-foreground',
                  )}
                >
                  <Icon className="size-4 shrink-0" aria-hidden="true" />
                  {t(section.titleKey)}
                </button>
              )
            })}
          </nav>
        </aside>

        <div className="flex min-w-0 flex-col gap-6">
          <SettingsSection
            id="profile"
            icon={UserRound}
            title={t('settings.profileTitle')}
            description={t('settings.profileSubtitle')}
          >
            <AvatarForm />
            <Separator />
            <FieldPanel>
              <ProfileForm />
            </FieldPanel>
          </SettingsSection>

          <SettingsSection
            id="security"
            icon={Lock}
            title={t('settings.passwordTitle')}
            description={t('settings.passwordSubtitle')}
          >
            <FieldPanel>
              <PasswordForm />
            </FieldPanel>
          </SettingsSection>
        </div>
      </div>
    </div>
  )
}

interface SettingsSectionProps {
  id: string
  icon: LucideIcon
  title: string
  description: string
  children: ReactNode
}

/** White section card with an icon-led header and a stacked body. */
function SettingsSection({
  id,
  icon: Icon,
  title,
  description,
  children,
}: SettingsSectionProps) {
  return (
    <Card id={id} className="scroll-mt-6">
      <CardHeader>
        <div className="flex items-center gap-3">
          <span className="flex size-9 shrink-0 items-center justify-center rounded-lg bg-primary/10 text-primary">
            <Icon className="size-4" aria-hidden="true" />
          </span>
          <div className="flex flex-col gap-0.5">
            <CardTitle className="text-base">{title}</CardTitle>
            <CardDescription>{description}</CardDescription>
          </div>
        </div>
      </CardHeader>
      <CardContent className="flex flex-col gap-6">{children}</CardContent>
    </Card>
  )
}

/**
 * Muted panel that lifts the enclosed form fields onto a white surface: it forces
 * the design-system Input/Select triggers to render solid `bg-card` so they read
 * as distinct, elevated fields against the tinted panel (settings-only styling,
 * scoped via the data-slot descendant selectors — checkboxes/file inputs are
 * untouched because they carry no data-slot).
 */
function FieldPanel({ children }: { children: ReactNode }) {
  return (
    <div className="rounded-lg border bg-muted/50 p-4 sm:p-5 [&_[data-slot=input]]:bg-card [&_[data-slot=select-trigger]]:bg-card">
      {children}
    </div>
  )
}

/** Highlights the section currently in view for the sticky index. */
function useActiveSection(ids: readonly string[]): string {
  const [active, setActive] = useState(ids[0] ?? '')

  useEffect(() => {
    const observer = new IntersectionObserver(
      (entries) => {
        const visible = entries
          .filter((entry) => entry.isIntersecting)
          .sort((a, b) => a.boundingClientRect.top - b.boundingClientRect.top)[0]
        if (visible) {
          setActive(visible.target.id)
        }
      },
      { rootMargin: '-20% 0px -70% 0px' },
    )

    ids.forEach((id) => {
      const element = document.getElementById(id)
      if (element) {
        observer.observe(element)
      }
    })

    return () => observer.disconnect()
  }, [ids])

  return active
}

/** Smooth-scrolls to a section, honoring reduced-motion. */
function scrollToSection(id: string): void {
  const element = document.getElementById(id)
  if (!element) {
    return
  }
  const reduceMotion = window.matchMedia(
    '(prefers-reduced-motion: reduce)',
  ).matches
  element.scrollIntoView({
    behavior: reduceMotion ? 'auto' : 'smooth',
    block: 'start',
  })
}
