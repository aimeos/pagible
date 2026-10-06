/**
 * @license MIT, https://opensource.org/license/mit
 */

import { onError } from '@apollo/client/link/error'
import { RetryLink } from '@apollo/client/link/retry'
import { BatchHttpLink } from 'apollo-link-batch-http'
import { createApolloProvider } from '@vue/apollo-option'
import { ApolloClient, ApolloLink, InMemoryCache, Observable } from '@apollo/client/core'
import router from './routes'
import { socketId } from './echo'
import { useUserStore } from './stores'
import { urlgraphql } from './config'
import { xsrfHeaders } from './utils'

const MESSAGE_HEADERS = ['x-error-message', 'x-status-message', 'x-message']

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
    useUserStore().touch()

    return response
  })
}

export function handleError({ graphQLErrors, networkError, operation, forward }) {
  const unauthorized = [401, 419].includes(networkError?.statusCode) || graphQLErrors?.some((err) =>
    err.message === 'Unauthenticated.' ||
    err.extensions?.code === 'UNAUTHENTICATED' ||
    err.extensions?.http?.status === 401
  )

  if (!unauthorized || operation?.getContext().relogin) return

  const user = useUserStore()

  // Session expired while editing: keep the open views and retry the request after
  // the user signed in again instead of dropping all unsaved changes
  if (user.me && forward) {
    return new Observable((observer) => {
      let sub = null

      user.reauth()
        .then(() => { sub = forward(operation).subscribe(observer) })
        .catch(() => observer.error(networkError || new Error(graphQLErrors?.[0]?.message || 'Unauthenticated')))

      return () => sub?.unsubscribe()
    })
  }

  user.me = false
  apolloClient.clearStore().catch((error) => console.error('Failed to clear Apollo cache', error))
  router.push({ name: 'login' })
}

/** Removes every cached variant of a root list field. */
export function invalidateList(field, cache = apolloClient.cache) {
  cache.evict({ id: 'ROOT_QUERY', fieldName: field })
  cache.gc()
}

/** Uses cached lists only when remote invalidation is configured. */
export function listFetchPolicy() {
  return document.querySelector('#app')?.dataset?.reverb ? 'cache-first' : 'network-only'
}

export function retry(error) {
  return !!error && error.statusCode !== 419
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
