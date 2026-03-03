import log from '../../src/log'

describe('log plugin', () => {
  it('installs $log onto app globalProperties', () => {
    const app = {
      config: { globalProperties: {} },
    }
    log.install(app)
    expect(app.config.globalProperties.$log).to.be.a('function')
  })

  it('$log calls console.error with the provided arguments', () => {
    const app = {
      config: { globalProperties: {} },
    }
    log.install(app)

    cy.stub(console, 'error').as('consoleError')
    app.config.globalProperties.$log('test-message', 42)
    cy.get('@consoleError').should('have.been.calledWith', 'test-message', 42)
  })
})
