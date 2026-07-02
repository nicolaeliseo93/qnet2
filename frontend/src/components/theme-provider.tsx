import type { ReactNode } from 'react'
import { ThemeProvider as NextThemesProvider } from 'next-themes'

/**
 * App-wide theme provider. Persists the light/dark choice and applies it as a
 * `.dark` class on <html>, which drives both Tailwind's `dark:` variant and the
 * CSS variables in index.css. Also the source of truth for sonner's `useTheme`.
 */
export function ThemeProvider({ children }: { children: ReactNode }) {
  return (
    <NextThemesProvider
      attribute="class"
      defaultTheme="system"
      enableSystem
      disableTransitionOnChange
    >
      {children}
    </NextThemesProvider>
  )
}
