import { computed, reactive } from 'vue'
import { messages } from '@/lang'

const LOCALE_STORAGE_KEY = 'easy-rent-locale'
const fallbackLocale = 'lv'

const supportedLanguages = [
  { code: 'lv', flag: '🇱🇻', nativeName: 'Latviešu', intl: 'lv-LV', datePicker: 'lv' },
  { code: 'ru', flag: '🇷🇺', nativeName: 'Русский', intl: 'ru-RU', datePicker: 'ru' },
  { code: 'en', flag: '🇬🇧', nativeName: 'English', intl: 'en-GB', datePicker: 'en' },
]

function resolveInitialLocale() {
  const stored = typeof window !== 'undefined' ? window.localStorage.getItem(LOCALE_STORAGE_KEY) : null
  if (stored && messages[stored]) return stored
  return fallbackLocale
}

const state = reactive({
  locale: resolveInitialLocale(),
})

function resolveMessage(locale, key) {
  return key.split('.').reduce((current, part) => current?.[part], messages[locale])
}

function interpolate(template, params = {}) {
  return String(template).replace(/\{(\w+)\}/g, (_, key) => String(params[key] ?? ''))
}

export function setLocale(locale) {
  if (!messages[locale]) return
  state.locale = locale
  if (typeof window !== 'undefined') {
    window.localStorage.setItem(LOCALE_STORAGE_KEY, locale)
  }
}

export function useLocale() {
  const currentLanguage = computed(() => {
    return supportedLanguages.find(item => item.code === state.locale) || supportedLanguages[0]
  })

  const t = (key, params) => {
    const currentValue = resolveMessage(state.locale, key)
    const fallbackValue = resolveMessage(fallbackLocale, key)
    const value = currentValue ?? fallbackValue ?? key
    return typeof value === 'string' ? interpolate(value, params) : value
  }

  return {
    locale: computed(() => state.locale),
    currentLanguage,
    supportedLanguages,
    setLocale,
    t,
    getIntlLocale: () => currentLanguage.value.intl,
    getDatePickerLocale: () => currentLanguage.value.datePicker,
  }
}