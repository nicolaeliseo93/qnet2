import {
  Briefcase,
  Building2,
  Circle,
  Layers,
  LayoutDashboard,
  MapPin,
  ShieldCheck,
  Users,
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
}

export function resolveIcon(name: string | null): LucideIcon {
  if (!name) {
    return Circle
  }
  return iconMap[name] ?? Circle
}
