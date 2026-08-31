import 'cypress-real-events'

beforeEach(() => {
  cy.intercept('GET', '**/storage/**', {
    headers: { 'content-type': 'image/svg+xml' },
    body: '<svg xmlns="http://www.w3.org/2000/svg" width="1" height="1" />',
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
