/**
 * @license MIT, https://opensource.org/license/mit
 */

/**
 * Returns the CSRF header derived from Laravel's XSRF-TOKEN cookie for cookie-authenticated
 * requests, or an empty object when the cookie is absent. Used directly by Apollo's headersLink and
 * (via postHeaders) by raw fetch POSTs, so the cookie/header contract lives in one place.
 *
 * @returns {Object} { 'X-XSRF-TOKEN': token } or {}
 */
export function xsrfHeaders() {
  const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/)

  return match ? { 'X-XSRF-TOKEN': decodeURIComponent(match[1]) } : {}
}

/**
 * Returns the request headers for a cookie-authenticated JSON POST: content negotiation plus the
 * CSRF token header. Keeps the header contract for raw fetch() calls in one place (builds on
 * xsrfHeaders so a future CSRF-contract change is made once).
 *
 * @param {string} accept Value for the Accept header (e.g. 'text/plain' for a streamed response)
 * @returns {Object} request headers
 */
export function postHeaders(accept = 'application/json') {
  return { 'Content-Type': 'application/json', Accept: accept, ...xsrfHeaders() }
}
