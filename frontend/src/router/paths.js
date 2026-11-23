export const HOME_ROUTE = '/'
export const MAP_ROUTE = '/map'
export const AUTH_ROUTE = '/auth'
export const FORGOT_PASSWORD_ROUTE = '/forgot-password'
export const RESET_PASSWORD_ROUTE = '/reset-password'
export const VERIFY_EMAIL_ROUTE = '/verify-email'
export const PROFILE_ROUTE = '/profile'

export function accountHome (persona) {
  return persona?.loma === 'pakalpojumu_sniedzejs' ? MAP_ROUTE : HOME_ROUTE
}

export function emailVerificationRoute (email, receipt = {}) {
  return {
    path: VERIFY_EMAIL_ROUTE,
    state: {
      emailVerification: {
        email,
        mailStatus: receipt.mail_status ?? null,
        cooldownUntil: Date.now() + (receipt.retry_after ?? 0) * 1000,
      },
    },
  }
}

export function buildCompanyRoute(companyId) {
	return `/company/${companyId}`
}
