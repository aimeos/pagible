import 'cypress-real-events'

// "ResizeObserver loop ..." is a benign browser notification (not a real error);
// real browsers ignore it. Prevent Cypress from failing tests because of it.
Cypress.on('uncaught:exception', (err) => {
  if (err.message.includes('ResizeObserver')) {
    return false
  }
})

{}
