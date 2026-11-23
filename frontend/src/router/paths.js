export const HOME_ROUTE = '/'
export const MAP_ROUTE = '/map'
export const AUTH_ROUTE = '/auth'
export const PROFILE_ROUTE = '/profile'

export function accountHome (persona) {
  return persona?.loma === 'pakalpojumu_sniedzejs' ? MAP_ROUTE : HOME_ROUTE
}

export function buildCompanyRoute(companyId) {
	return `/company/${companyId}`
}
