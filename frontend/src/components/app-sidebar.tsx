import { useEffect } from 'react'
import { GalleryVerticalEnd } from 'lucide-react'
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
import { NavMain } from '@/components/nav-main'
import { NavUser } from '@/components/nav-user'
import { useNavigation } from '@/features/navigation/use-navigation'
import { useAuth } from '@/features/auth/use-auth'
import { env } from '@/config/env'

export function AppSidebar() {
  const { user, logout } = useAuth()
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
      <SidebarHeader>
        <SidebarMenu>
          <SidebarMenuItem>
            <SidebarMenuButton size="lg" className="pointer-events-none">
              <div className="flex aspect-square size-8 items-center justify-center rounded-lg bg-sidebar-primary text-sidebar-primary-foreground">
                <GalleryVerticalEnd className="size-4" />
              </div>
              <div className="grid flex-1 text-left text-sm leading-tight">
                <span className="truncate font-medium">{env.appNameSidebar}</span>
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

      <SidebarFooter>{user && <NavUser user={user} />}</SidebarFooter>
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
