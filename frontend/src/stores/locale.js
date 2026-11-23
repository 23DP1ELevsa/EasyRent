import { computed, reactive } from 'vue'
import { messages } from '@/lang'

const LOCALE_STORAGE_KEY = 'easy-rent-locale'
const fallbackLocale = 'lv'

const supportedLanguages = [
  { code: 'lv', flag: '🇱🇻', nativeName: 'Latviešu', intl: 'lv-LV', datePicker: 'lv' },
  { code: 'ru', flag: '🇷🇺', nativeName: 'Русский', intl: 'ru-RU', datePicker: 'ru' },
  { code: 'en', flag: '🇬🇧', nativeName: 'English', intl: 'en-GB', datePicker: 'en' },
]

function resolveInitialLocale () {
  const stored = typeof window === 'undefined' ? null : window.localStorage.getItem(LOCALE_STORAGE_KEY)
  return stored && Object.hasOwn(messages, stored) ? stored : fallbackLocale
}

const state = reactive({
  locale: resolveInitialLocale(),
})

function resolveMessage (locale, key) {
  return key.split('.').reduce((current, part) => current?.[part], messages[locale])
}

function interpolate (template, params = {}) {
  return String(template).replace(/\{(\w+)\}/g, (_, key) => String(params[key] ?? ''))
}

export function setLocale (locale) {
  if (!Object.hasOwn(messages, locale)) {
    return
  }
  state.locale = locale
  if (typeof window !== 'undefined') {
    window.localStorage.setItem(LOCALE_STORAGE_KEY, locale)
  }
}

export function getLocale () {
  return state.locale
}

export function useLocale () {
  const currentLanguage = computed(() => {
    return supportedLanguages.find(item => item.code === state.locale) || supportedLanguages[0]
  })
  const priceFormatter = computed(() => new Intl.NumberFormat(currentLanguage.value.intl, { style: 'currency', currency: 'EUR' }))
  const ratingFormatter = computed(() => new Intl.NumberFormat(currentLanguage.value.intl, { minimumFractionDigits: 1, maximumFractionDigits: 1 }))

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
    formatPrice: value => priceFormatter.value.format(Number(value || 0)),
    formatRating: value => ratingFormatter.value.format(Number(value || 0)),
    getIntlLocale: () => currentLanguage.value.intl,
    getDatePickerLocale: () => currentLanguage.value.datePicker,
  }
}
