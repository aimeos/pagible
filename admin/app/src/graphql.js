/**
 * @license LGPL, https://opensource.org/license/lgpl-3-0
 */

import { onError } from '@apollo/client/link/error'
import { RetryLink } from '@apollo/client/link/retry'
import { BatchHttpLink } from 'apollo-link-batch-http'
import { createApolloProvider } from '@vue/apollo-option'
import { ApolloClient, ApolloLink, InMemoryCache } from '@apollo/client/core'
import createUploadLink from 'apollo-upload-client/createUploadLink.mjs'
import router from './routes'
import { urlgraphql } from './config'

const retryLink = new RetryLink({
  delay: { initial: 300, max: 5000, jitter: true },
  attempts: { max: 2, retryIf: (error) => !!error }
})

const errorLink = onError(({ errors }) => {
  if (!errors) return

  for (const err of errors) {
    if (
      err.message === 'This action is unauthorized.' ||
      err.extensions?.code === 'UNAUTHENTICATED' ||
      err.extensions?.http?.status === 401
    ) {
      router.push({ name: 'login' })
      break
    }
  }
})

const httpLink = ApolloLink.split(
  (operation) => operation.getContext().hasUpload,
  createUploadLink({
    uri: urlgraphql,
    credentials: 'include'
  }),
  new BatchHttpLink({
    uri: urlgraphql,
    batchMax: 50,
    batchInterval: 20,
    credentials: 'include'
  })
)

const apolloClient = new ApolloClient({
  cache: new InMemoryCache(),
  link: ApolloLink.from([retryLink, errorLink, httpLink])
})
const apollo = createApolloProvider({ defaultClient: apolloClient })


export default apollo
export { apolloClient }
