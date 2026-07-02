import type { ReactNode } from 'react'
import {
  Card,
  CardContent,
  CardDescription,
  CardHeader,
  CardTitle,
} from '@/components/ui/card'
import { AppBreadcrumbs } from '@/routes/breadcrumbs'

interface AuthCardProps {
  title: string
  description: string
  children: ReactNode
}

/** Centered card shell shared by the public authentication screens. */
export function AuthCard({ title, description, children }: AuthCardProps) {
  return (
    <div className="flex min-h-svh items-center justify-center bg-muted/40 p-4">
      <div className="flex w-full max-w-sm flex-col gap-4">
        <AppBreadcrumbs />
        <Card>
          <CardHeader>
            <CardTitle className="text-2xl">{title}</CardTitle>
            <CardDescription>{description}</CardDescription>
          </CardHeader>
          <CardContent>{children}</CardContent>
        </Card>
      </div>
    </div>
  )
}
