import { createPinia, setActivePinia } from 'pinia'
import router from '../../../js/routes'
import { useSessionStore, useUserStore } from '../../../js/stores'
import { Observable, gql } from '@apollo/client/core'
import { AUTH_STATUS, apolloClient, graphqlFetch, handleError, invalidateList, listFetchPolicy, retry } from '../../../js/graphql'
import '../../../js/session'

describe('handleError()', () => {
  beforeEach(() => {
    setActivePinia(createPinia())
  })

  it('redirects to login on an HTTP 419 error', () => {
    const user = useUserStore()
    user.me = { id: 'user-1' }

    cy.stub(apolloClient, 'clearStore').as('clearStore').resolves()
    cy.stub(router, 'push').as('routerPush').resolves()

    handleError({ networkError: { statusCode: 419 } })

    expect(user.me).to.equal(false)
    cy.get('@clearStore').should('have.been.calledOnce')
    cy.get('@routerPush').should('have.been.calledOnceWith', { name: 'login' })
  })

  it('retries the request after the user signed in again', () => {
    const user = useUserStore()
    user.me = { email: 'editor@example.com' }

    const operation = { getContext: () => ({}) }
    const forward = cy.stub().returns(Observable.of({ data: { ok: true } }))
    const push = cy.stub(router, 'push').resolves()
    let resolve = null
    cy.stub(useSessionStore(), 'reauth').returns(new Promise((res) => { resolve = res }))

    const observable = handleError({ graphQLErrors: [{ message: 'Unauthenticated.' }], operation, forward })

    return new Promise((done, fail) => {
      observable.subscribe({
        next: (result) => {
          expect(result).to.deep.equal({ data: { ok: true } })
          expect(forward).to.have.been.calledOnceWith(operation)
          expect(push).not.to.have.been.called
          done()
        },
        error: fail
      })
      expect(forward).not.to.have.been.called
      resolve()
    })
  })

  it('fails the request if the user gives up signing in again', () => {
    const user = useUserStore()
    user.me = { email: 'editor@example.com' }

    const forward = cy.stub()
    cy.stub(useSessionStore(), 'reauth').rejects(new Error('Unauthenticated'))

    const observable = handleError({
      networkError: { statusCode: 419 },
      operation: { getContext: () => ({}) },
      forward
    })

    return new Promise((done) => {
      observable.subscribe({
        error: (error) => {
          expect(error.statusCode).to.equal(419)
          expect(forward).not.to.have.been.called
          done()
        }
      })
    })
  })

  it('ignores errors of the re-login requests', () => {
    const user = useUserStore()
    user.me = { email: 'editor@example.com' }
    const reauth = cy.stub(useSessionStore(), 'reauth')

    const result = handleError({
      graphQLErrors: [{ message: 'Unauthenticated.' }],
      operation: { getContext: () => ({ relogin: true }) },
      forward: cy.stub()
    })

    expect(result).to.equal(undefined)
    expect(reauth).not.to.have.been.called
  })
})

describe('AUTH_STATUS', () => {
  it('contains the status codes of expired sessions', () => {
    expect(AUTH_STATUS).to.deep.equal([401, 419])
    expect(Object.isFrozen(AUTH_STATUS)).to.be.true
  })

  it('treats HTTP 401 errors as expired sessions', () => {
    setActivePinia(createPinia())
    const user = useUserStore()
    user.me = { id: 'user-1' }

    cy.stub(apolloClient, 'clearStore').resolves()
    cy.stub(router, 'push').resolves()

    handleError({ networkError: { statusCode: 401 } })

    expect(user.me).to.equal(false)
  })
})

describe('retry()', () => {
  const query = { query: { definitions: [{ kind: 'OperationDefinition', operation: 'query' }] } }
  const mutation = { query: { definitions: [{ kind: 'OperationDefinition', operation: 'mutation' }] } }

  it('does not retry authentication errors', () => {
    expect(retry({ statusCode: 419 }, query)).to.equal(false)
    expect(retry({ statusCode: 401 }, query)).to.equal(false)
  })

  it('retries other transport errors of queries', () => {
    expect(retry(new Error('Network failure'), query)).to.equal(true)
    expect(retry({ statusCode: 502 }, query)).to.equal(true)
  })

  it('does not retry mutations', () => {
    expect(retry(new Error('Network failure'), mutation)).to.equal(false)
    expect(retry({ statusCode: 502 }, mutation)).to.equal(false)
  })
})

describe('graphqlFetch()', () => {
  it('passes successful responses through', () => {
    const response = new Response('{"data":{}}', { status: 200 })

    cy.stub(window, 'fetch').resolves(response)

    return graphqlFetch('/graphql', {}).then((result) => {
      expect(result).to.equal(response)
    })
  })

  it('uses a safe error header before the status text', () => {
    const response = new Response('<!DOCTYPE html>', {
      status: 419,
      statusText: 'Page Expired',
      headers: { 'x-error-message': 'CSRF token mismatch.' },
    })

    cy.stub(window, 'fetch').resolves(response)

    return graphqlFetch('/graphql', {}).then(
      () => { throw new Error('graphqlFetch unexpectedly resolved') },
      (error) => {
        expect(error.name).to.equal('ServerError')
        expect(error.message).to.equal('HTTP 419: CSRF token mismatch.')
        expect(error.response).to.equal(response)
        expect(error.statusCode).to.equal(419)
      },
    )
  })

  it('falls back to the status text', () => {
    const response = new Response('<!DOCTYPE html>', {
      status: 503,
      statusText: 'Service Unavailable',
    })

    cy.stub(window, 'fetch').resolves(response)

    return graphqlFetch('/graphql', {}).then(
      () => { throw new Error('graphqlFetch unexpectedly resolved') },
      (error) => {
        expect(error.message).to.equal('HTTP 503: Service Unavailable')
      },
    )
  })

  it('does not include the response body', () => {
    const response = new Response('<!DOCTYPE html>', { status: 500 })

    cy.stub(window, 'fetch').resolves(response)

    return graphqlFetch('/graphql', {}).then(
      () => { throw new Error('graphqlFetch unexpectedly resolved') },
      (error) => {
        expect(error.message).to.equal('HTTP 500')
      },
    )
  })
})

describe('invalidateList()', () => {
  it('removes every variant of the selected list field', () => {
    const evict = cy.stub()
    const gc = cy.stub()

    invalidateList('elements', { evict, gc })

    expect(evict).to.have.been.calledWith({ id: 'ROOT_QUERY', fieldName: 'elements' })
    expect(gc).to.have.been.calledOnce
  })
})

describe('listFetchPolicy()', () => {
  beforeEach(() => {
    document.querySelector('[data-cy-root]').id = 'app'
  })

  afterEach(() => {
    document.querySelector('#app')?.removeAttribute('data-reverb')
  })

  it('uses the network when remote invalidation is unavailable', () => {
    expect(listFetchPolicy()).to.equal('network-only')
  })

  it('uses the cache when remote invalidation is configured', () => {
    document.querySelector('#app').dataset.reverb = '{}'

    expect(listFetchPolicy()).to.equal('cache-first')
  })
})

describe('apolloClient cache', () => {
  it('keeps the language variants of pages apart in cached lists', () => {
    const query = gql`
      query ($lang: String) {
        pages(lang: $lang) {
          data {
            id
            lang
            name
          }
        }
      }
    `
    const result = (lang, name) => ({ pages: { __typename: 'PagePaginator', data: [{ __typename: 'Page', id: 'page-1', lang, name }] } })

    apolloClient.cache.writeQuery({ query, variables: { lang: 'en' }, data: result('en', 'Home') })
    apolloClient.cache.writeQuery({ query, variables: { lang: 'de' }, data: result('de', 'Startseite') })

    expect(apolloClient.cache.readQuery({ query, variables: { lang: 'en' } }).pages.data[0]).to.include({ lang: 'en', name: 'Home' })
    expect(apolloClient.cache.readQuery({ query, variables: { lang: 'de' } }).pages.data[0]).to.include({ lang: 'de', name: 'Startseite' })

    invalidateList('pages')
  })
})
