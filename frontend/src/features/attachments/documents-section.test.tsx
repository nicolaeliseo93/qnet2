import { beforeAll, beforeEach, describe, expect, it, vi } from 'vitest'
import type { ReactElement } from 'react'
import { fireEvent, render, screen, waitFor, within } from '@testing-library/react'
import { QueryClient, QueryClientProvider } from '@tanstack/react-query'
import i18n from '@/i18n'
import { ConfirmDialogProvider } from '@/components/confirm-dialog'
import { DocumentsSection } from '@/features/attachments/documents-section'
import type { Attachment } from '@/features/attachments/types'

/**
 * Documents section (shared, self-fetching Attachment API consumer): list
 * render (image thumbnail vs. non-image icon), empty/error states, upload,
 * delete-through-confirm, and that preview/download anchors carry the
 * backend-provided `view_url`/`download_url` untouched.
 */

const listAttachmentsMock = vi.fn()
const uploadAttachmentMock = vi.fn()
const deleteAttachmentMock = vi.fn()

vi.mock('@/features/attachments/api', () => ({
  attachmentsQueryKey: (resource: string, id: number, collection: string) =>
    ['attachments', resource, id, collection] as const,
  listAttachments: (...args: unknown[]) => listAttachmentsMock(...args),
  uploadAttachment: (...args: unknown[]) => uploadAttachmentMock(...args),
  deleteAttachment: (...args: unknown[]) => deleteAttachmentMock(...args),
}))

function attachment(overrides: Partial<Attachment> = {}): Attachment {
  return {
    id: 1,
    collection: 'documents',
    original_name: 'contract.pdf',
    mime_type: 'application/pdf',
    extension: 'pdf',
    size: 2048,
    attachable_type: 'opportunity',
    attachable_id: 42,
    uploaded_by: 9,
    download_url: 'https://api.test/api/attachments/1/download',
    view_url: 'https://api.test/api/attachments/1/view',
    created_at: '2026-07-20T10:00:00.000Z',
    ...overrides,
  }
}

function renderSection(ui: ReactElement) {
  const client = new QueryClient({ defaultOptions: { queries: { retry: false } } })
  return render(
    <QueryClientProvider client={client}>
      <ConfirmDialogProvider>{ui}</ConfirmDialogProvider>
    </QueryClientProvider>,
  )
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

beforeEach(() => {
  listAttachmentsMock.mockReset()
  uploadAttachmentMock.mockReset()
  deleteAttachmentMock.mockReset()
})

describe('DocumentsSection', () => {
  it('renders an image thumbnail for image documents and an icon for other files', async () => {
    listAttachmentsMock.mockResolvedValue([
      attachment({ id: 1, original_name: 'photo.png', mime_type: 'image/png' }),
      attachment({ id: 2, original_name: 'contract.pdf', mime_type: 'application/pdf' }),
    ])

    renderSection(
      <DocumentsSection resource="opportunity" id={42} canUpload={false} canDelete={false} />,
    )

    await waitFor(() => expect(screen.getByText('photo.png')).toBeInTheDocument())
    expect(screen.getByText('contract.pdf')).toBeInTheDocument()

    const images = screen.getAllByRole('img')
    expect(images).toHaveLength(1)
    expect(images[0]).toHaveAttribute('src', 'https://api.test/api/attachments/1/view')
    expect(listAttachmentsMock).toHaveBeenCalledWith('opportunity', 42, 'documents')
  })

  it('shows the empty state when there are no documents', async () => {
    listAttachmentsMock.mockResolvedValue([])

    renderSection(
      <DocumentsSection resource="opportunity" id={42} canUpload={false} canDelete={false} />,
    )

    await waitFor(() => expect(screen.getByText('No documents yet.')).toBeInTheDocument())
  })

  it('shows the error state with a retry action', async () => {
    listAttachmentsMock.mockRejectedValue(new Error('network error'))

    renderSection(
      <DocumentsSection resource="opportunity" id={42} canUpload={false} canDelete={false} />,
    )

    await waitFor(() =>
      expect(screen.getByText('Unable to load the documents. Please try again.')).toBeInTheDocument(),
    )
    expect(screen.getByRole('button', { name: 'Retry' })).toBeInTheDocument()
  })

  it('renders preview/download anchors carrying the resource view_url/download_url, preview opening safely in a new tab', async () => {
    listAttachmentsMock.mockResolvedValue([attachment()])

    renderSection(
      <DocumentsSection resource="opportunity" id={42} canUpload={false} canDelete={false} />,
    )

    const preview = await screen.findByRole('link', { name: 'Preview' })
    expect(preview).toHaveAttribute('href', 'https://api.test/api/attachments/1/view')
    expect(preview).toHaveAttribute('target', '_blank')
    expect(preview).toHaveAttribute('rel', 'noopener noreferrer')

    const download = screen.getByRole('link', { name: 'Download' })
    expect(download).toHaveAttribute('href', 'https://api.test/api/attachments/1/download')
  })

  it('uploads the dropped/selected file and refreshes the list', async () => {
    listAttachmentsMock.mockResolvedValue([])
    uploadAttachmentMock.mockResolvedValue(attachment())

    renderSection(
      <DocumentsSection resource="opportunity" id={42} canUpload canDelete={false} />,
    )

    await waitFor(() => expect(screen.getByText('No documents yet.')).toBeInTheDocument())

    const file = new File(['%PDF-1.4'], 'contract.pdf', { type: 'application/pdf' })
    fireEvent.change(screen.getByLabelText('Drop a file here or click to browse'), {
      target: { files: [file] },
    })

    await waitFor(() =>
      expect(uploadAttachmentMock).toHaveBeenCalledWith({
        resource: 'opportunity',
        id: 42,
        collection: 'documents',
        file,
      }),
    )
    await waitFor(() => expect(listAttachmentsMock).toHaveBeenCalledTimes(2))
  })

  it('does not render the dropzone when the caller has no upload permission', async () => {
    listAttachmentsMock.mockResolvedValue([])

    renderSection(
      <DocumentsSection resource="opportunity" id={42} canUpload={false} canDelete={false} />,
    )

    await waitFor(() => expect(screen.getByText('No documents yet.')).toBeInTheDocument())
    expect(screen.queryByLabelText('Drop a file here or click to browse')).not.toBeInTheDocument()
  })

  it('deletes a document after the confirm dialog is accepted', async () => {
    listAttachmentsMock.mockResolvedValue([attachment()])
    deleteAttachmentMock.mockResolvedValue(undefined)

    renderSection(
      <DocumentsSection resource="opportunity" id={42} canUpload={false} canDelete />,
    )

    const deleteButton = await screen.findByRole('button', { name: 'Delete document' })
    fireEvent.click(deleteButton)

    const dialog = await screen.findByRole('alertdialog')
    fireEvent.click(within(dialog).getByRole('button', { name: 'Delete document' }))

    await waitFor(() => expect(deleteAttachmentMock).toHaveBeenCalledWith(1))
    await waitFor(() => expect(listAttachmentsMock).toHaveBeenCalledTimes(2))
  })

  it('does not delete when the confirm dialog is cancelled', async () => {
    listAttachmentsMock.mockResolvedValue([attachment()])

    renderSection(
      <DocumentsSection resource="opportunity" id={42} canUpload={false} canDelete />,
    )

    const deleteButton = await screen.findByRole('button', { name: 'Delete document' })
    fireEvent.click(deleteButton)

    const dialog = await screen.findByRole('alertdialog')
    fireEvent.click(within(dialog).getByRole('button', { name: 'Cancel' }))

    await waitFor(() => expect(screen.queryByRole('alertdialog')).not.toBeInTheDocument())
    expect(deleteAttachmentMock).not.toHaveBeenCalled()
  })

  it('does not render the delete action when the caller has no delete permission', async () => {
    listAttachmentsMock.mockResolvedValue([attachment()])

    renderSection(
      <DocumentsSection resource="opportunity" id={42} canUpload={false} canDelete={false} />,
    )

    await screen.findByText('contract.pdf')
    expect(screen.queryByRole('button', { name: 'Delete document' })).not.toBeInTheDocument()
  })
})
