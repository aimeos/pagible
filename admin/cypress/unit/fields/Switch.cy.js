import Switch from '../../../js/fields/Switch.vue'

describe('Switch', () => {
  it('renders a switch', () => {
    cy.mount(Switch, { props: { config: {} } })
    cy.get('.v-switch').should('exist')
  })

  it('is off when modelValue is false', () => {
    cy.mount(Switch, { props: { modelValue: false, config: {} } })
    cy.get('input[type="checkbox"]').should('not.be.checked')
  })

  it('is on when modelValue is true', () => {
    cy.mount(Switch, { props: { modelValue: true, config: {} } })
    cy.get('input[type="checkbox"]').should('be.checked')
  })

  it('uses config.default (false) when no modelValue supplied', () => {
    cy.mount(Switch, { props: { config: { default: false } } })
    cy.get('input[type="checkbox"]').should('not.be.checked')
  })

  it('emits update:modelValue true when toggled on', () => {
    const onUpdate = cy.spy().as('update')
    cy.mount(Switch, {
      props: { modelValue: false, config: {}, 'onUpdate:modelValue': onUpdate },
    })
    cy.get('input[type="checkbox"]').check({ force: true })
    cy.get('@update').should('have.been.calledWith', true)
  })

  it('uses config.on/off values for custom true/false', () => {
    const onUpdate = cy.spy().as('update')
    cy.mount(Switch, {
      props: {
        modelValue: 'no',
        config: { on: 'yes', off: 'no' },
        'onUpdate:modelValue': onUpdate,
      },
    })
    cy.get('input[type="checkbox"]').check({ force: true })
    cy.get('@update').should('have.been.calledWith', 'yes')
  })

  it('uses config.default when no modelValue is supplied', () => {
    cy.mount(Switch, { props: { config: { default: true } } })
    cy.get('input[type="checkbox"]').should('be.checked')
  })

  it('accepts custom on/off values without prop type warnings', () => {
    cy.window().then((win) => cy.spy(win.console, 'warn').as('warn'))
    cy.mount(Switch, { props: { modelValue: 'yes', config: { on: 'yes', off: 'no' } } })
    cy.get('input[type="checkbox"]').should('be.checked')
    cy.get('@warn').should('not.have.been.calledWithMatch', /Invalid prop/)
  })

  it('emits the config.off value when toggled off', () => {
    const onUpdate = cy.spy().as('update')
    cy.mount(Switch, {
      props: { modelValue: 1, config: { on: 1, off: 0 }, 'onUpdate:modelValue': onUpdate },
    })
    cy.get('input[type="checkbox"]').uncheck({ force: true })
    cy.get('@update').should('have.been.calledWith', 0)
  })
})
