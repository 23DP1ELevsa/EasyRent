export const roles = ['klients', 'pakalpojumu_sniedzejs', 'administrators']
export const reportTargets = ['user', 'vehicle', 'review']
export const reportReasons = ['fraud', 'misinformation', 'abuse', 'rental_terms', 'suspicious', 'other']
export const reportStates = ['jauns', 'izskatisana', 'atrisinats', 'noraidits']
export const restrictionTypes = ['bridinajums', 'rezervaciju_aizliegums', 'publikaciju_aizliegums', 'atsauksmju_aizliegums', 'konta_blokesana']
export const durations = ['24h', '3d', '7d', '14d', '30d', '90d', 'custom', 'indefinite']

const availability = ['pieejams', 'aiznemts', 'neaktivs']
const accountStates = ['active', 'blocked', 'deleted']
const visibleStates = ['visible', 'hidden', 'archived']
const yesNo = [{ value: '1', key: 'yes' }, { value: '0', key: 'no' }]
const dates = [
  { key: 'from', label: 'from', type: 'date' },
  { key: 'to', label: 'to', type: 'date' },
]
const number = (key, label) => ({ key, label, type: 'number' })
const select = (key, label, options) => ({ key, label, options })
const column = (key, label, format = 'text', sort = null, read = row => row[key]) => ({ key, label, sort, format, read })
const id = key => column(key, 'id', 'id', key)
const date = (key, label, sort = key) => column(key, label, 'date', sort)
const money = (key, label) => column(key, label, 'money', key)
const state = (key, label, sort, read = row => row[key]) => column(key, label, 'state', sort, read)
const party = (key, label, read) => column(key, label, 'person', null, read)
const visibility = row => row.moderation_hidden_at ? 'hidden' : row.archived_at ? 'archived' : row.owner_blocked ? 'blocked' : 'visible'

export const sections = {
  dashboard: { icon: 'mdi-view-dashboard-outline' },
  users: {
    icon: 'mdi-account-group-outline', id: 'persona_id',
    filters: [select('role', 'role', roles), select('verified', 'verified', yesNo), select('status', 'status', accountStates), select('restriction', 'restrictionType', restrictionTypes), ...dates],
    columns: [
      id('persona_id'), column('vards', 'name', 'person', 'vards', row => row), column('epasts', 'email', 'text', 'epasts'),
      state('loma', 'role', 'loma'), date('created_at', 'registered'), state('verified_at', 'verified', 'email_verified_at', row => row.verified_at ? 'yes' : 'no'),
      state('status', 'status', null, row => row.removed_at ? 'deleted' : row.blocked ? 'blocked' : 'active'),
      column('active_restrictions', 'activeRestrictions', 'number'),
    ],
  },
  vehicles: {
    icon: 'mdi-car-outline', id: 'transportlidzeklis_id',
    filters: [select('status', 'availability', availability), select('visibility', 'visibility', visibleStates), number('category', 'categoryId'), number('persona_id', 'userId')],
    columns: [
      id('transportlidzeklis_id'), column('marka', 'vehicle', 'vehicle', 'marka', row => row),
      party('owner', 'owner', row => row.sniedzejs?.persona), column('veids', 'category', 'text', null, row => row.veids?.nosaukums),
      state('statuss', 'availability', 'statuss'), state('visibility', 'visibility', null, visibility), money('dienas_nomas_cena', 'price'),
    ],
  },
  categories: {
    icon: 'mdi-shape-outline', id: 'veids_id', filters: [select('active', 'status', [
      { value: '1', key: 'active' }, { value: '0', key: 'inactive' },
    ])],
    columns: [id('veids_id'), column('nosaukums', 'categoryName', 'text', 'nosaukums'), column('tips', 'slug', 'text', 'tips'),
      state('deactivated_at', 'status', null, row => row.deactivated_at ? 'inactive' : 'active'), column('transportlidzekli_count', 'vehicles', 'number')],
  },
  bookings: {
    icon: 'mdi-calendar-check-outline', id: 'rezervacija_id',
    filters: [select('status', 'status', ['apmaksata', 'neapmaksata']), number('persona_id', 'userId'), number('vehicle_id', 'vehicleId'), ...dates],
    columns: [
      id('rezervacija_id'), column('vehicle', 'vehicle', 'vehicle', null, row => row.transporta_snapshot ?? row.transportlidzeklis),
      party('client', 'client', row => row.klients?.persona), party('provider', 'provider', row => row.transportlidzeklis?.sniedzejs?.persona),
      date('sakuma_laiks', 'start'), date('beigu_laiks', 'end'), money('kopa_summa', 'amount'),
      state('apmaksas_statuss', 'status', 'apmaksas_statuss'), column('invoice', 'invoice', 'text', null, row => row.maksajumi?.map(item => item.rekins).filter(Boolean).join(', ')),
    ],
  },
  payments: {
    icon: 'mdi-credit-card-outline', id: 'maksajums_id',
    filters: [select('status', 'status', ['gaida', 'apstiprinats', 'atteikts']), number('booking_id', 'bookingId'), ...dates],
    columns: [
      id('maksajums_id'), column('rezervacija_id', 'bookingId', 'id', 'rezervacija_id'), money('summa', 'amount'),
      state('statuss', 'status', 'statuss'), column('rekins', 'invoice'), column('tranzakcijas_numurs', 'transaction'), date('created_at', 'created'),
    ],
    details: [
      column('vehicle', 'vehicle', 'vehicle', null, row => row.rezervacija?.transporta_snapshot ?? row.rezervacija?.transportlidzeklis),
      party('client', 'client', row => row.rezervacija?.klients?.persona),
      party('provider', 'provider', row => row.rezervacija?.transportlidzeklis?.sniedzejs?.persona),
      column('start', 'start', 'date', null, row => row.rezervacija?.sakuma_laiks),
      column('end', 'end', 'date', null, row => row.rezervacija?.beigu_laiks),
    ],
  },
  reviews: {
    icon: 'mdi-star-outline', id: 'atsauksme_id',
    filters: [number('persona_id', 'userId'), number('vehicle_id', 'vehicleId'), select('rating', 'rating', [1, 2, 3, 4, 5]),
      select('hidden', 'visibility', [{ value: '1', key: 'hidden' }, { value: '0', key: 'visible' }])],
    columns: [
      id('atsauksme_id'), party('client', 'client', row => row.klients?.persona),
      column('vehicle', 'vehicle', 'vehicle', null, row => row.transportlidzeklis), column('vertejums', 'rating', 'rating', 'vertejums'),
      column('komentars', 'comment'), date('datums', 'created'), state('visibility', 'visibility', null, visibility),
    ],
    details: [column('moderation_reason', 'reason')],
  },
  reports: {
    icon: 'mdi-flag-outline', id: 'zinojums_id',
    filters: [select('status', 'status', ['open', ...reportStates]), select('target', 'target', reportTargets), select('reason', 'reason', reportReasons), number('persona_id', 'userId')],
    columns: [
      id('zinojums_id'), party('reporter', 'reporter', row => row.reporter), column('target', 'target', 'target', null, row => row),
      state('iemesls', 'reason', null), state('statuss', 'status', 'statuss'), date('created_at', 'created'),
    ],
    details: [column('apraksts', 'description'), column('lemuma_komentars', 'decision'), column('izskatits_at', 'created', 'date')],
  },
  audit: {
    icon: 'mdi-history', id: 'darbiba_id',
    filters: [number('administrator_id', 'administratorId'), number('persona_id', 'userId'), ...dates],
    columns: [
      id('darbiba_id'), party('administrator', 'administrator', row => row.administrator), state('darbiba', 'action', 'darbiba'),
      column('target', 'target', 'target', null, row => row), column('iemesls', 'reason'), date('created_at', 'created'),
    ],
    details: [column('iepriekseja_vertiba', 'before', 'changes'), column('jauna_vertiba', 'after', 'changes')],
  },
}

export function sectionPath (key) {
  return key === 'dashboard' ? '/admin' : `/admin/${key}`
}

export function personName (person) {
  return [person?.vards, person?.uzvards].filter(Boolean).join(' ')
}

export function vehicleName (vehicle) {
  return [vehicle?.marka, vehicle?.modelis].filter(Boolean).join(' ')
}
