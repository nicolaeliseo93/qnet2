import { describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import { IconPicker } from '@/components/icon-picker'

const LABELS = {
  placeholder: 'Choose an icon…',
  searchPlaceholder: 'Search icon…',
  empty: 'No icon found.',
  clearLabel: 'Clear icon',
}

function renderPicker(value = '', onChange = vi.fn()) {
  render(<IconPicker value={value} onChange={onChange} labels={LABELS} />)
  return { onChange }
}

describe('IconPicker', () => {
  it('shows the placeholder and no clear button when nothing is selected', () => {
    renderPicker('')
    expect(screen.getByText('Choose an icon…')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Clear icon' })).not.toBeInTheDocument()
  })

  it('shows the selected icon name and a clear button when a value is set', () => {
    renderPicker('star')
    expect(screen.getByText('star')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Clear icon' })).toBeInTheDocument()
  })

  it('filters the grid by the search term and selects an icon on click', () => {
    const { onChange } = renderPicker('')

    fireEvent.click(screen.getByRole('button', { name: 'Choose an icon…' }))
    fireEvent.change(screen.getByPlaceholderText('Search icon…'), { target: { value: 'star' } })

    const starOption = screen.getByRole('option', { name: 'star' })
    expect(starOption).toBeInTheDocument()
    // A non-matching icon is filtered out.
    expect(screen.queryByRole('option', { name: 'calendar' })).not.toBeInTheDocument()

    fireEvent.click(starOption)
    expect(onChange).toHaveBeenCalledWith('star')
  })

  it('shows the empty state when the search matches no icon', () => {
    renderPicker('')
    fireEvent.click(screen.getByRole('button', { name: 'Choose an icon…' }))
    fireEvent.change(screen.getByPlaceholderText('Search icon…'), {
      target: { value: 'zzznomatch' },
    })
    expect(screen.getByText('No icon found.')).toBeInTheDocument()
  })

  it('clears the selection when the clear button is pressed', () => {
    const { onChange } = renderPicker('star')
    fireEvent.click(screen.getByRole('button', { name: 'Clear icon' }))
    expect(onChange).toHaveBeenCalledWith('')
  })
})
