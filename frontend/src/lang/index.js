import admin from '@/lang/admin'
import en from '@/lang/en'
import lv from '@/lang/lv'

import ru from '@/lang/ru'
import turnstile from '@/lang/turnstile'
import vehicleListing from '@/lang/vehicleListing'

export const messages = {
  lv: { ...lv, ...vehicleListing.lv, admin: admin.lv, turnstile: turnstile.lv },
  ru: { ...ru, ...vehicleListing.ru, admin: admin.ru, turnstile: turnstile.ru },
  en: { ...en, ...vehicleListing.en, admin: admin.en, turnstile: turnstile.en },
}
