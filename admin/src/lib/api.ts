import axios, { AxiosError } from 'axios'

/**
 * Talks to Laravel at /admin/api with the session cookie.
 * Laravel's XSRF-TOKEN cookie is sent back as X-XSRF-TOKEN automatically (CSRF protection).
 */
export const api = axios.create({
  baseURL: '/admin/api',
  withCredentials: true,
  withXSRFToken: true,
  headers: { Accept: 'application/json', 'X-Requested-With': 'XMLHttpRequest' },
})

/** First error message from a Laravel validation (422) or other error response. */
export function apiErrorMessage(error: unknown, fallback = 'Something went wrong. Please try again.'): string {
  if (error instanceof AxiosError) {
    const data = error.response?.data as { message?: string; errors?: Record<string, string[]> } | undefined
    const first = data?.errors ? Object.values(data.errors)[0]?.[0] : undefined
    return first ?? data?.message ?? fallback
  }
  return fallback
}

/** Field errors from a 422 response, for react-hook-form. */
export function apiFieldErrors(error: unknown): Record<string, string> {
  if (error instanceof AxiosError && error.response?.status === 422) {
    const errors = (error.response.data as { errors?: Record<string, string[]> }).errors ?? {}
    return Object.fromEntries(Object.entries(errors).map(([k, v]) => [k, v[0]]))
  }
  return {}
}
