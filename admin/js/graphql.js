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

const retryLink = new RetryLink({
  delay: { initial: 300, max: 5000, jitter: true },
  attempts: { max: 2, retryIf: (error) => !!error }
})

// Forwards Laravel's XSRF-TOKEN cookie as the X-XSRF-TOKEN header so cookie
// authenticated mutations are protected against CSRF when the GraphQL route is
// guarded by the VerifyCsrfToken middleware. No-op when the cookie is absent.
const csrfLink = new ApolloLink((operation, forward) => {
  const match = document.cookie.match(/(?:^|;\s*)XSRF-TOKEN=([^;]+)/)

  if (match) {
    operation.setContext(({ headers = {} }) => ({
      headers: { ...headers, 'X-XSRF-TOKEN': decodeURIComponent(match[1]) }
    }))
  }

  return forward(operation)
})

// Forwards the websocket connection id as the X-Socket-ID header so the server
// can use broadcast()->toOthers() to skip echoing a change back to the tab that
// made it. No-op until Echo is connected, which is fine: an unconnected tab is
// not subscribed to any channel and so receives no events anyway.
const socketLink = new ApolloLink((operation, forward) => {
  const id = socketId()

  if (id) {
    operation.setContext(({ headers = {} }) => ({
      headers: { ...headers, 'X-Socket-ID': id }
    }))
  }

  return forward(operation)
})

const errorLink = onError(({ errors }) => {
  if (!errors) return

  for (const err of errors) {
    if (
      err.message === 'This action is unauthorized.' ||
      err.extensions?.code === 'UNAUTHENTICATED' ||
      err.extensions?.http?.status === 401
    ) {
      useUserStore().me = null
      router.push({ name: 'login' })
      break
    }
  }
})

let uploadLink = null

export function clearUploadLink() {
  uploadLink = null
}

const lazyUploadLink = new ApolloLink((operation, forward) => {
  return new Observable((observer) => {
    let sub = null

    const load = uploadLink
      ? Promise.resolve(uploadLink)
      : import('apollo-upload-client/createUploadLink.mjs').then((mod) => {
          uploadLink = mod.default({ uri: urlgraphql, credentials: 'include' })
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
    credentials: 'include'
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
  link: ApolloLink.from([retryLink, errorLink, csrfLink, socketLink, httpLink]),
  queryDeduplication: true
})
const apollo = createApolloProvider({ defaultClient: apolloClient })

export default apollo
export { apolloClient }
