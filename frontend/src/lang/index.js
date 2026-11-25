import admin from '@/lang/admin'
import en from '@/lang/en'
import lv from '@/lang/lv'

import ru from '@/lang/ru'
import turnstile from '@/lang/turnstile'

export const messages = {
  lv: { ...lv, admin: admin.lv, turnstile: turnstile.lv },
  ru: { ...ru, admin: admin.ru, turnstile: turnstile.ru },
  en: { ...en, admin: admin.en, turnstile: turnstile.en },
}
