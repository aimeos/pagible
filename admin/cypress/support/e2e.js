import 'cypress-real-events'

beforeEach(() => {
  cy.intercept('GET', '**/storage/**', {
    headers: { 'content-type': 'image/svg+xml' },
    body: '<svg xmlns="http://www.w3.org/2000/svg" width="1" height="1" />',
  })
})

// Changed filters are saved by a keepalive request when the page is unloaded, i.e. when the
// next test starts and no intercept is registered yet. These requests delay the GraphQL
// requests of the following pages by several seconds, so they are discarded.
afterEach(() => {
  cy.window().then((win) => {
    win.fetch = () => Promise.resolve(new win.Response('{}'))
  })
})

// "ResizeObserver loop ..." is a benign browser notification (not a real error);
// real browsers ignore it. Prevent Cypress from failing tests because of it.
Cypress.on('uncaught:exception', (err) => {
  if (err.message.includes('ResizeObserver')) {
    return false
  }
})

{}
