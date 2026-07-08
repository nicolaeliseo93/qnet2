import {
  BookUser,
  Briefcase,
  Building2,
  Circle,
  ContactRound,
  DatabaseZap,
  Layers,
  LayoutDashboard,
  ListTree,
  MapPin,
  Package,
  ShieldCheck,
  SlidersHorizontal,
  Tag,
  Tags,
  Users,
  Waypoints,
  type LucideIcon,
} from 'lucide-react'

/**
 * Maps the backend's icon names (navigation config) to Lucide components.
 * Unknown or missing names fall back to a neutral icon so the menu never breaks.
 */
const iconMap: Record<string, LucideIcon> = {
  'layout-dashboard': LayoutDashboard,
  users: Users,
  'shield-check': ShieldCheck,
  briefcase: Briefcase,
  building: Building2,
  'map-pin': MapPin,
  layers: Layers,
  'database-zap': DatabaseZap,
  'contact-round': ContactRound,
  'book-user': BookUser,
  tag: Tag,
  tags: Tags,
  waypoints: Waypoints,
  'sliders-horizontal': SlidersHorizontal,
  'list-tree': ListTree,
  package: Package,
}

export function resolveIcon(name: string | null): LucideIcon {
  if (!name) {
    return Circle
  }
  return iconMap[name] ?? Circle
}
