import { useLocale } from '@/stores/locale'

const attributes = new Map([
  ['Automāts', 'vehicle.automatic'], ['Mehānika', 'vehicle.manual'],
  ['Benzīns', 'vehicle.petrol'], ['Dīzelis', 'vehicle.diesel'], ['Elektro', 'vehicle.electric'],
  ['-', 'vehicle.other'], ['pieejams', 'map.available'], ['aiznemts', 'map.busy'], ['neaktivs', 'map.inactive'],
])

const types = new Map([
  ['auto', 'car'], ['automašīna', 'car'], ['vieglā automašīna', 'car'], ['suv', 'suv'],
  ['kravas auto', 'truck'], ['furgons', 'van'], ['mikroautobuss', 'bus'], ['motocikls', 'motorcycle'],
  ['velosipēds', 'bicycle'], ['laiva', 'boat'], ['ūdens motocikls', 'jetSki'], ['kvadracikls', 'atv'],
  ['piekabe', 'trailer'], ['skrejritenis', 'scooter'],
])

export function useVehicleLabels () {
  const { locale, t } = useLocale()

  function attributeLabel (value) {
    const key = attributes.get(value)
    return key ? t(key) : value
  }

  function typeLabel (name) {
    if (locale.value === 'lv') {
      return name
    }

    const key = types.get(name?.trim().toLowerCase())
    return key ? t(`vehicle.${key}`) : name
  }

  const attributeOptions = values => values.map(value => ({ title: attributeLabel(value), value }))

  return { attributeLabel, attributeOptions, typeLabel }
}
