export const HOME_ROUTE = '/'
export const MAP_ROUTE = '/map'
export const AUTH_ROUTE = '/auth'
export const PROFILE_ROUTE = '/profile'

export function buildCompanyRoute(companyId) {
	return `/company/${companyId}`
}