import { Card, CardContent } from '@/components/ui/card'
import { Skeleton } from '@/components/ui/skeleton'

/** Placeholder card shaped like `ProjectCard`, shown while a page is fetching. */
export function ProjectCardSkeleton() {
  return (
    <Card className="gap-3 py-3">
      <CardContent className="flex flex-col gap-3 px-3">
        <div className="flex items-start justify-between gap-2">
          <div className="flex flex-col gap-1.5">
            <Skeleton className="h-3 w-16" />
            <Skeleton className="h-5 w-20 rounded-md" />
          </div>
          <Skeleton className="size-8 rounded-md" />
        </div>
        <Skeleton className="h-4 w-3/4" />
        <div className="grid grid-cols-3 gap-1.5">
          <Skeleton className="h-10 rounded-md" />
          <Skeleton className="h-10 rounded-md" />
          <Skeleton className="h-10 rounded-md" />
        </div>
        <Skeleton className="h-3 w-24" />
      </CardContent>
    </Card>
  )
}
