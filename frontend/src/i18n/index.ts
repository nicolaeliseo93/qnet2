import i18n from 'i18next'
import { initReactI18next } from 'react-i18next'
import { en } from '@/i18n/locales/en'
import { it } from '@/i18n/locales/it'

export const defaultLocale = 'it'
export const fallbackLocale = 'en'
export const supportedLocales = ['en', 'it'] as const

type SupportedLocale = (typeof supportedLocales)[number]

function isSupportedLocale(locale: string): locale is SupportedLocale {
  return (supportedLocales as readonly string[]).includes(locale)
}

/**
 * Switches the active UI language to a backend-provided locale, ignoring any
 * value the frontend does not ship translations for.
 */
export function applyLocale(locale: string | null | undefined): void {
  if (locale && isSupportedLocale(locale) && i18n.language !== locale) {
    void i18n.changeLanguage(locale)
  }
}

void i18n.use(initReactI18next).init({
  resources: {
    en: { translation: en },
    it: { translation: it },
  },
  lng: defaultLocale,
  fallbackLng: fallbackLocale,
  interpolation: {
    escapeValue: false, // React already escapes values.
  },
})

export default i18n
