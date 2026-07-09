import { beforeAll, describe, expect, it, vi } from 'vitest'
import { fireEvent, render, screen } from '@testing-library/react'
import i18n from '@/i18n'
import { ColorTokenPicker } from '@/features/custom-fields/components/color-token-picker'

function renderPicker(value = '', onChange = vi.fn()) {
  render(<ColorTokenPicker value={value} onChange={onChange} />)
  return { onChange }
}

beforeAll(async () => {
  await i18n.changeLanguage('en')
})

describe('ColorTokenPicker', () => {
  it('shows the placeholder and no clear button when unset', () => {
    renderPicker('')
    expect(screen.getByText('Choose a color…')).toBeInTheDocument()
    expect(screen.queryByRole('button', { name: 'Clear color' })).not.toBeInTheDocument()
  })

  it('shows the localized token name and a clear button when set', () => {
    renderPicker('blue')
    expect(screen.getByText('Blue')).toBeInTheDocument()
    expect(screen.getByRole('button', { name: 'Clear color' })).toBeInTheDocument()
  })

  it('stores the token name (not a hex) when a swatch is picked', () => {
    const { onChange } = renderPicker('')
    fireEvent.click(screen.getByRole('button', { name: 'Choose a color…' }))
    fireEvent.click(screen.getByRole('option', { name: 'Emerald' }))
    expect(onChange).toHaveBeenCalledWith('emerald')
  })

  it('clears the color when the clear button is pressed', () => {
    const { onChange } = renderPicker('blue')
    fireEvent.click(screen.getByRole('button', { name: 'Clear color' }))
    expect(onChange).toHaveBeenCalledWith('')
  })
})
