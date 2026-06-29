import { graphqlFetch } from '../../../js/graphql'

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
