import { createPinia, setActivePinia } from 'pinia'
import router from '../../../js/routes'
import { useUserStore } from '../../../js/stores'
import { apolloClient, graphqlFetch, handleError, invalidateList, listFetchPolicy, retry } from '../../../js/graphql'

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
})

describe('retry()', () => {
  it('does not retry HTTP 419 errors', () => {
    expect(retry({ statusCode: 419 })).to.equal(false)
  })

  it('retries other transport errors', () => {
    expect(retry(new Error('Network failure'))).to.equal(true)
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

    invalidateList({ evict, gc }, 'elements')

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
