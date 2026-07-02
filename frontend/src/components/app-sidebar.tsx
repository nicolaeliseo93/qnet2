import { useEffect } from 'react'
import { Settings } from 'lucide-react'
import { NavLink, useLocation } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import {
  Sidebar,
  SidebarContent,
  SidebarFooter,
  SidebarGroup,
  SidebarHeader,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarMenuSkeleton,
  SidebarRail,
} from '@/components/ui/sidebar'
import { NavMain, NAV_ITEM_CLASS } from '@/components/nav-main'
import { useNavigation } from '@/features/navigation/use-navigation'
import { useAuth } from '@/features/auth/use-auth'
import { env } from '@/config/env'

const SETTINGS_ROUTE = '/settings'

export function AppSidebar() {
  const { t } = useTranslation()
  const location = useLocation()
  const { logout } = useAuth()
  const navigation = useNavigation()

  // Navigation is required to use the app. If it cannot be loaded the session
  // can no longer be trusted, so log out and return to /login (ProtectedRoute
  // performs the redirect once the session is cleared).
  useEffect(() => {
    if (navigation.isError) {
      void logout()
    }
  }, [navigation.isError, logout])

  return (
    <Sidebar collapsible="icon">
      <SidebarHeader className="border-b border-sidebar-border">
        <SidebarMenu>
          <SidebarMenuItem>
            <SidebarMenuButton size="lg" className="pointer-events-none gap-0">
              <img
                src="/brands/logo_white.svg"
                alt=""
                aria-hidden
                className="size-8 shrink-0 object-contain"
              />
              <div className="grid flex-1 text-left leading-tight">
                <span className="truncate text-lg font-semibold">{env.appNameSidebar}</span>
              </div>
            </SidebarMenuButton>
          </SidebarMenuItem>
        </SidebarMenu>
      </SidebarHeader>

      <SidebarContent>
        {(navigation.isPending || navigation.isError) && (
          <SidebarGroup>
            <NavSkeleton />
          </SidebarGroup>
        )}

        {navigation.data && <NavMain items={navigation.data} />}
      </SidebarContent>

      <SidebarFooter className="border-t border-sidebar-border">
        <SidebarMenu>
          <SidebarMenuItem>
            <SidebarMenuButton
              asChild
              tooltip={t('navigation.settings')}
              size="sm"
              isActive={location.pathname === SETTINGS_ROUTE}
              className={NAV_ITEM_CLASS}
            >
              <NavLink to={SETTINGS_ROUTE}>
                <Settings />
                <span>{t('navigation.settings')}</span>
              </NavLink>
            </SidebarMenuButton>
          </SidebarMenuItem>
        </SidebarMenu>
      </SidebarFooter>

      <SidebarRail />
    </Sidebar>
  )
}

function NavSkeleton() {
  return (
    <SidebarMenu>
      {Array.from({ length: 4 }).map((_, index) => (
        <SidebarMenuItem key={index}>
          <SidebarMenuSkeleton showIcon />
        </SidebarMenuItem>
      ))}
    </SidebarMenu>
  )
}
