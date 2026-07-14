import { useEffect, useMemo, useRef } from 'react'
import { useTranslation } from 'react-i18next'
import { useNavigate } from 'react-router-dom'
import { Button } from '@/components/ui/button'
import { ProjectCard } from '@/features/projects/project-card'
import { ProjectCardSkeleton } from '@/features/projects/project-card-skeleton'
import { useProjectCards } from '@/features/projects/use-project-cards'

const INITIAL_SKELETON_COUNT = 6
const NEXT_PAGE_SKELETON_COUNT = 3

/**
 * Card grid body of the projects page (spec 0026 AC-007/009). Infinite
 * scroll via a bottom sentinel observed against the page viewport (`root:
 * null`, unlike the bounded notifications panel this pattern mirrors —
 * `features/notifications/notification-list.tsx`), with the loading / error /
 * empty triad and skeleton cards while fetching.
 */
export function ProjectCardGrid() {
  const { t } = useTranslation()
  const navigate = useNavigate()
  const sentinelRef = useRef<HTMLDivElement>(null)

  const { data, isPending, isError, refetch, hasNextPage, isFetchingNextPage, fetchNextPage } =
    useProjectCards()

  const cards = useMemo(() => data?.pages.flatMap((page) => page.items) ?? [], [data])

  // Load the next page when the bottom sentinel scrolls into the viewport.
  useEffect(() => {
    const sentinel = sentinelRef.current
    if (!sentinel || !hasNextPage) {
      return
    }

    const observer = new IntersectionObserver(
      (entries) => {
        if (entries[0]?.isIntersecting && hasNextPage && !isFetchingNextPage) {
          void fetchNextPage()
        }
      },
      { rootMargin: '0px 0px 200px 0px' },
    )
    observer.observe(sentinel)

    return () => observer.disconnect()
  }, [hasNextPage, isFetchingNextPage, fetchNextPage, cards.length])

  const handleEdit = (id: number) => void navigate(`/projects/${id}/edit`)

  if (isError) {
    return (
      <div className="flex flex-col items-center gap-2 py-10 text-center">
        <p className="text-sm text-muted-foreground">{t('projects.grid.loadError')}</p>
        <Button variant="outline" size="sm" onClick={() => void refetch()}>
          {t('common.retry')}
        </Button>
      </div>
    )
  }

  if (isPending) {
    return (
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
        {Array.from({ length: INITIAL_SKELETON_COUNT }).map((_, index) => (
          <ProjectCardSkeleton key={index} />
        ))}
      </div>
    )
  }

  if (cards.length === 0) {
    return <p className="py-10 text-center text-sm text-muted-foreground">{t('projects.grid.empty')}</p>
  }

  return (
    <div className="flex flex-col gap-3">
      <div className="grid grid-cols-1 gap-3 sm:grid-cols-2 lg:grid-cols-3">
        {cards.map((project) => (
          <ProjectCard key={project.id} project={project} onEdit={handleEdit} />
        ))}
        {isFetchingNextPage
          ? Array.from({ length: NEXT_PAGE_SKELETON_COUNT }).map((_, index) => (
              <ProjectCardSkeleton key={`next-${index}`} />
            ))
          : null}
      </div>
      {/* Observed to trigger the next page; only mounted while one is available. */}
      {hasNextPage ? <div ref={sentinelRef} aria-hidden="true" /> : null}
    </div>
  )
}
