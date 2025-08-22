import { onError } from "@apollo/client/link/error"
import { BatchHttpLink } from "apollo-link-batch-http"
import { createApolloProvider } from '@vue/apollo-option'
import { ApolloClient, ApolloLink, InMemoryCache } from '@apollo/client/core'
import createUploadLink from 'apollo-upload-client/createUploadLink.mjs'
import router from './routes'


const node = document.querySelector('#app')

const errorLink = onError(({ errors }) => {
  if(!errors) return

  for(const err of errors) {
    if(err.message === "This action is unauthorized."
      || err.extensions?.code === "UNAUTHENTICATED"
      || err.extensions?.http?.status === 401
    ) {
      router.push({ name: "login" })
      break
    }
  }
})

const httpLink = ApolloLink.split(
  operation => operation.getContext().hasUpload,
  createUploadLink({
    uri: node?.dataset?.urlgraphql || '/graphql',
    credentials: 'include'
  }),
  new BatchHttpLink({
    uri: node?.dataset?.urlgraphql || '/graphql',
    batchMax: 50,
    batchInterval: 20,
    credentials: 'include'
  })
)

const apolloClient = new ApolloClient({cache: new InMemoryCache(), link: ApolloLink.from([errorLink, httpLink])})
const apollo = createApolloProvider({defaultClient: apolloClient})

export default apollo
export { apolloClient }