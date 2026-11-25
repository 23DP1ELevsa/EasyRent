import { useLocale } from '@/stores/locale'
import { useVehicleLabels } from '@/utils/vehicleLabels'
import { personName, vehicleName } from './sections'

export function useAdminFormat () {
  const { t, getIntlLocale, formatPrice } = useLocale()
  const { licenceLabel } = useVehicleLabels()
  const label = (key, params) => t(/^(vehicleLicence|vehiclePhotos)\./.test(key) ? key : `admin.${key}`, params)
  const status = value => label(`values.${value}`)
  const number = value => new Intl.NumberFormat(getIntlLocale()).format(value ?? 0)
  const date = (value, time = true) => {
    if (!value) return '—'
    const parsed = new Date(value)
    if (Number.isNaN(parsed.getTime())) return '—'
    return new Intl.DateTimeFormat(getIntlLocale(), { dateStyle: 'medium', ...(time ? { timeStyle: 'short' } : {}) }).format(parsed)
  }

  function format (value, formatType = 'text') {
    if (formatType === 'licence') return licenceLabel(value)
    if (value === null || value === undefined || value === '') return '—'
    switch (formatType) {
      case 'state': return status(value)
      case 'date': return date(value)
      case 'number': return number(value)
      case 'id': return `#${value}`
      case 'money': return formatPrice(value)
      case 'person': return personName(value) || (value.persona_id ? `#${value.persona_id}` : '—')
      case 'vehicle': return vehicleName(value) || '—'
      case 'rating': return `${value}/5`
      case 'target': return `${status(value.objekta_veids)} #${value.objekta_id}`
      default: return value
    }
  }

  return { label, status, format, date, number, formatPrice }
}

export function stateColor (value) {
  if (['active', 'yes', 'pieejams', 'visible', 'apmaksata', 'apstiprinats', 'atrisinats'].includes(value)) return 'success'
  if (['hidden', 'blocked', 'atteikts', 'noraidits'].includes(value)) return 'error'
  if (['gaida', 'aiznemts', 'neapmaksata', 'jauns', 'izskatisana'].includes(value)) return 'warning'
  return undefined
}
