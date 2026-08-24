import { createI18n } from 'vue-i18n'
import uk from './locales/uk.json'
import en from './locales/en.json'

export const SUPPORTED_LOCALES = ['uk', 'en']
export const LOCALE_NAMES = { uk: 'Українська', en: 'English' }

const saved = (() => {
  try {
    return localStorage.getItem('locale')
  } catch {
    return null
  }
})()

export const i18n = createI18n({
  legacy: false,
  globalInjection: true,
  locale: SUPPORTED_LOCALES.includes(saved) ? saved : 'uk',
  fallbackLocale: 'uk',
  messages: { uk, en },
})

export function setLocale(locale) {
  if (!SUPPORTED_LOCALES.includes(locale)) return
  i18n.global.locale.value = locale
  try {
    localStorage.setItem('locale', locale)
  } catch {
    /* приватний режим тощо — просто не запам'ятається */
  }
}
