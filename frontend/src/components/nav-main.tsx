import { createElement } from 'react'
import { ChevronRight } from 'lucide-react'
import { NavLink, useLocation } from 'react-router-dom'
import { useTranslation } from 'react-i18next'
import {
  Collapsible,
  CollapsibleContent,
  CollapsibleTrigger,
} from '@/components/ui/collapsible'
import {
  SidebarGroup,
  SidebarGroupLabel,
  SidebarMenu,
  SidebarMenuButton,
  SidebarMenuItem,
  SidebarMenuSub,
  SidebarMenuSubButton,
  SidebarMenuSubItem,
} from '@/components/ui/sidebar'
import { resolveIcon } from '@/features/navigation/icon-map'
import type { NavigationItem } from '@/features/navigation/types'

export function NavMain({ items }: { items: NavigationItem[] }) {
  const { t } = useTranslation()
  const topLevel = items.filter((item) => item.type !== 'section')
  const sections = items.filter((item) => item.type === 'section')

  return (
    <>
      {topLevel.length > 0 && (
        <SidebarGroup>
          <SidebarMenu>
            {topLevel.map((item) => (
              <NavNode key={item.key} item={item} />
            ))}
          </SidebarMenu>
        </SidebarGroup>
      )}

      {sections.map((section) => (
        <SidebarGroup key={section.key}>
          <SidebarGroupLabel>{t(section.label)}</SidebarGroupLabel>
          <SidebarMenu>
            {section.children.map((child) => (
              <NavNode key={child.key} item={child} />
            ))}
          </SidebarMenu>
        </SidebarGroup>
      ))}
    </>
  )
}

function NavNode({ item }: { item: NavigationItem }) {
  const { t } = useTranslation()
  const location = useLocation()
  const icon = createElement(resolveIcon(item.icon))
  const label = t(item.label)

  if (item.children.length > 0) {
    const hasActiveChild = item.children.some((child) => isActive(child, location.pathname))

    return (
      <Collapsible asChild defaultOpen={hasActiveChild} className="group/collapsible">
        <SidebarMenuItem>
          <CollapsibleTrigger asChild>
            <SidebarMenuButton tooltip={label}>
              {icon}
              <span>{label}</span>
              <ChevronRight className="ml-auto transition-transform duration-200 group-data-[state=open]/collapsible:rotate-90" />
            </SidebarMenuButton>
          </CollapsibleTrigger>
          <CollapsibleContent>
            <SidebarMenuSub>
              {item.children.map((child) => (
                <SidebarMenuSubItem key={child.key}>
                  <SidebarMenuSubButton asChild isActive={isActive(child, location.pathname)}>
                    <NavLink to={child.route ?? '#'}>
                      <span>{t(child.label)}</span>
                    </NavLink>
                  </SidebarMenuSubButton>
                </SidebarMenuSubItem>
              ))}
            </SidebarMenuSub>
          </CollapsibleContent>
        </SidebarMenuItem>
      </Collapsible>
    )
  }

  // A leaf node without a route is a non-navigable label (defensive: the
  // backend only emits route-less nodes as groups, which are handled above).
  if (!item.route) {
    return (
      <SidebarMenuItem>
        <SidebarMenuButton tooltip={label} className="pointer-events-none opacity-70">
          {icon}
          <span>{label}</span>
        </SidebarMenuButton>
      </SidebarMenuItem>
    )
  }

  return (
    <SidebarMenuItem>
      <SidebarMenuButton asChild tooltip={label} isActive={isActive(item, location.pathname)}>
        <NavLink to={item.route}>
          {icon}
          <span>{label}</span>
        </NavLink>
      </SidebarMenuButton>
    </SidebarMenuItem>
  )
}

function isActive(item: NavigationItem, pathname: string): boolean {
  if (!item.route) {
    return false
  }
  return pathname === item.route || pathname.startsWith(`${item.route}/`)
}
