import { useTranslation } from 'react-i18next'
import { MessagesSquare } from 'lucide-react'
import { Button } from '@/components/ui/button'
import { Skeleton } from '@/components/ui/skeleton'
import { FormSection } from '@/components/form-section'
import { NoteComposer } from '@/features/notes/note-composer'
import { NoteList } from '@/features/notes/note-list'
import { useNotes } from '@/features/notes/use-notes'

export interface NotesSectionProps {
  /** Domain slug registered in `config/notes.php` (D-9), owned entirely by the host module. */
  entityType: string
  /** Id of the host record within `entityType`. */
  entityId: number
  /**
   * Renders the built-in `FormSection` card (icon/title/description).
   * Default `true` keeps the panel's current look; pass `false` when an
   * ancestor already renders its own heading (e.g. `NotesDialog`'s
   * `DialogTitle`), mirroring `ContactsManager`'s `showHeader` convention.
   */
  showHeader?: boolean
}

const SKELETON_ROWS = 3

/**
 * Sole public component of the agnostic notes feature (D-14): a composer for
 * new root notes on top and the thread below, optionally wrapped in a titled
 * `FormSection` card. Carries no knowledge of the host module beyond the two
 * entity props (D-9) — this feature never imports from a host module's own
 * `features/` folder.
 */
export function NotesSection({ entityType, entityId, showHeader = true }: NotesSectionProps) {
  const { t } = useTranslation()
  const {
    data,
    isLoading,
    isError,
    refetch,
    hasNextPage,
    isFetchingNextPage,
    fetchNextPage,
  } = useNotes(entityType, entityId)

  const roots = data?.pages.flatMap((page) => page.data) ?? []

  const content = (
    <>
      <NoteComposer entityType={entityType} entityId={entityId} />

      {isLoading ? (
        <div className="flex flex-col gap-2">
          {Array.from({ length: SKELETON_ROWS }).map((_, index) => (
            <Skeleton key={index} className="h-14 w-full" />
          ))}
        </div>
      ) : isError ? (
        <div className="flex flex-col items-start gap-2">
          <p className="text-xs text-destructive">
            {t('notes.section.loadError', { defaultValue: 'Impossibile caricare le note.' })}
          </p>
          <Button type="button" variant="outline" size="sm" onClick={() => refetch()}>
            {t('common.retry')}
          </Button>
        </div>
      ) : roots.length === 0 ? (
        <p className="text-xs text-muted-foreground">
          {t('notes.section.empty', { defaultValue: 'Nessuna nota. Scrivi la prima per iniziare la discussione.' })}
        </p>
      ) : (
        <NoteList
          roots={roots}
          entityType={entityType}
          entityId={entityId}
          hasNextPage={hasNextPage}
          isFetchingNextPage={isFetchingNextPage}
          onLoadMore={() => fetchNextPage()}
        />
      )}
    </>
  )

  if (!showHeader) {
    return <div className="flex flex-col gap-4">{content}</div>
  }

  return (
    <FormSection
      icon={MessagesSquare}
      title={t('notes.section.title', { defaultValue: 'Note' })}
      description={t('notes.section.description', {
        defaultValue: 'Discuti il record con i colleghi: usa @ per menzionarli.',
      })}
    >
      {content}
    </FormSection>
  )
}
