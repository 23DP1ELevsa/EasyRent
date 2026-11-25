import { computed } from 'vue'
import { useLocale } from '@/stores/locale'

const licenceCategories = ['AM', 'A1', 'A2', 'A', 'B1', 'B', 'BE', 'C', 'CE', 'D', 'DE', 'NONE', 'OTHER']

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

  function licenceLabel (value) {
    if (value === 'NONE') {
      return t('vehicleLicence.none')
    }
    if (value === 'OTHER') {
      return t('vehicleLicence.other')
    }
    return value || t('vehicleLicence.unspecified')
  }

  const licenceOptions = computed(() => licenceCategories.map(value => ({ title: licenceLabel(value), value })))
  function listingNotice (vehicle) {
    const needsLicence = !vehicle.vaditaja_apliecibas_kategorija
    const needsPhoto = !vehicle.photos_count
    if (needsLicence && needsPhoto) {
      return t('vehiclePhotos.needBoth')
    }
    if (needsLicence) {
      return t('vehicleLicence.required')
    }
    if (needsPhoto) {
      return t('vehiclePhotos.needPhoto')
    }
    return ''
  }

  return { attributeLabel, attributeOptions, typeLabel, licenceLabel, licenceOptions, listingNotice }
}
