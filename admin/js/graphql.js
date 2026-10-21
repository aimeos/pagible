/**
 * @license MIT, https://opensource.org/license/mit
 */

import { onError } from '@apollo/client/link/error'
import { RetryLink } from '@apollo/client/link/retry'
import { BatchHttpLink } from 'apollo-link-batch-http'
import { createApolloProvider } from '@vue/apollo-option'
import { ApolloClient, ApolloLink, InMemoryCache, Observable } from '@apollo/client/core'
import { realtime, socketId } from './echo'
import { urlgraphql } from './config'
import { xsrfHeaders } from './http'
import gettext from './i18n'

const MESSAGE_HEADERS = ['x-error-message', 'x-status-message', 'x-message']

// HTTP status codes of requests rejected because the session expired
export const AUTH_STATUS = Object.freeze([401, 419])

// Handlers for the user session, registered by session.js so this module doesn't depend on the stores and router
const session = {
  // returns TRUE if a user is signed in
  active: () => false,
  // resolves after the user signed in again, rejects if the user gave up
  reauth: () => Promise.reject(new Error('Unauthenticated')),
  // called when the session ended and the user must sign in again
  ended: () => {},
  // called for each successful request, which extends the session
  touch: () => {}
}

/**
 * Registers the handlers for the user session
 *
 * @param {Object} handlers Functions active(), reauth(), ended() and touch()
 */
export function onSession(handlers) {
  Object.assign(session, handlers)
}

const retryLink = new RetryLink({
  delay: { initial: 300, max: 5000, jitter: true },
  attempts: { max: 2, retryIf: retry }
})

// Forwards Laravel's XSRF-TOKEN cookie as the X-XSRF-TOKEN header so cookie authenticated
// mutations are protected against CSRF, and the websocket connection id as the X-Socket-ID
// header so the server can skip echoing a change back to the tab that made it via toOthers().
// Both are no-ops when the cookie is absent or Echo isn't connected (no subscribed channels then).
const headersLink = new ApolloLink((operation, forward) => {
  const id = socketId()
  const headers = { ...xsrfHeaders(), ...(id && { 'X-Socket-ID': id }) }

  if (Object.keys(headers).length) {
    operation.setContext((ctx) => ({ headers: { ...ctx.headers, ...headers } }))
  }

  return forward(operation)
})

const errorLink = onError(handleError)

let uploadLink = null

export function clearUploadLink() {
  uploadLink = null
}

export function graphqlFetch(input, init) {
  return fetch(input, init).then((response) => {
    if (!response.ok) {
      throw graphqlError(response)
    }

    // each request extends the session, so check it again after it would expire without one
    session.touch()

    return response
  })
}

export function handleError({ graphQLErrors, networkError, operation, forward }) {
  const unauthorized = AUTH_STATUS.includes(networkError?.statusCode) || graphQLErrors?.some((err) =>
    err.message === 'Unauthenticated.' ||
    err.extensions?.code === 'UNAUTHENTICATED' ||
    err.extensions?.http?.status === 401
  )

  if (!unauthorized || operation?.getContext().relogin) return

  // Session expired while editing: keep the open views and retry the request after
  // the user signed in again instead of dropping all unsaved changes
  if (session.active() && forward) {
    return new Observable((observer) => {
      let sub = null

      session.reauth()
        .then(() => { sub = forward(operation).subscribe(observer) })
        .catch(() => observer.error(networkError || new Error(graphQLErrors?.[0]?.message || 'Unauthenticated')))

      return () => sub?.unsubscribe()
    })
  }

  apolloClient.clearStore().catch((error) => console.error('Failed to clear Apollo cache', error))
  session.ended()
}

/** Removes every cached variant of a root list field. */
export function invalidateList(field, cache = apolloClient.cache) {
  cache.evict({ id: 'ROOT_QUERY', fieldName: field })
  cache.gc()
}

/** Uses cached lists only when remote invalidation is configured. */
export function listFetchPolicy() {
  return realtime() ? 'cache-first' : 'network-only'
}

// TRUE if the server wasn't reachable at all, e.g. offline, instead of responding with an error
export function unreachable(error) {
  return (error?.networkError ?? error) instanceof TypeError
}

// translated message shown if the server wasn't reachable
export function connectionError() {
  return gettext.$gettext('Connection failed, please try again')
}

// translated message of a failed sign-in, the browser's message of network errors is technical
export function loginError(error) {
  return unreachable(error) ? connectionError() : error?.message || gettext.$gettext('Login failed')
}

// retries failed queries only, mutations may have been executed on the server before the request failed
// and authentication errors are handled by the error link
export function retry(error, operation) {
  const mutation = operation?.query?.definitions?.some((def) => def.operation === 'mutation')
  return !!error && !mutation && !AUTH_STATUS.includes(error.statusCode)
}

const lazyUploadLink = new ApolloLink((operation, forward) => {
  return new Observable((observer) => {
    let sub = null

    const load = uploadLink
      ? Promise.resolve(uploadLink)
      : import('apollo-upload-client/createUploadLink.mjs').then((mod) => {
          uploadLink = mod.default({
            uri: urlgraphql,
            credentials: 'include',
            fetch: graphqlFetch
          })
          return uploadLink
        })

    load
      .then((link) => { sub = link.request(operation).subscribe(observer) })
      .catch((err) => observer.error(err))

    return () => sub?.unsubscribe()
  })
})

const httpLink = ApolloLink.split(
  (operation) => operation.getContext().hasUpload,
  lazyUploadLink,
  new BatchHttpLink({
    uri: urlgraphql,
    batchMax: 50,
    batchInterval: 20,
    credentials: 'include',
    fetch: graphqlFetch
  })
)

const apolloClient = new ApolloClient({
  cache: new InMemoryCache({
    typePolicies: {
      // pages share their ID across all language variants, so normalized entries would mix the
      // variants of different languages in cached lists; each result keeps its own copy instead
      Page: { keyFields: false },
      Query: {
        fields: {
          elements: { merge: false },
          files: { merge: false },
          pages: { merge: false }
        }
      }
    },
    resultCacheMaxSize: 100
  }),
  link: ApolloLink.from([retryLink, errorLink, headersLink, httpLink]),
  queryDeduplication: true
})
const apollo = createApolloProvider({ defaultClient: apolloClient })

export default apollo
export { apolloClient }


function clean(value) {
  return String(value || '').replace(/[\r\n]+/g, ' ').trim()
}


function graphqlError(response) {
  const error = new Error(graphqlMessage(response))

  error.name = 'ServerError'
  error.response = response
  error.statusCode = response.status

  return error
}


function graphqlMessage(response) {
  const message = MESSAGE_HEADERS
    .map((name) => clean(response.headers?.get(name)))
    .find(Boolean) || clean(response.statusText)

  return message ? `HTTP ${response.status}: ${message}` : `HTTP ${response.status}`
}
