import Checkbox from '../../../js/fields/Checkbox.vue'

describe('Checkbox', () => {
  it('renders a checkbox', () => {
    cy.mount(Checkbox, { props: { config: {} } })
    cy.get('.v-checkbox').should('exist')
  })

  it('is unchecked when modelValue is false', () => {
    cy.mount(Checkbox, { props: { modelValue: false, config: {} } })
    cy.get('input[type="checkbox"]').should('not.be.checked')
  })

  it('is checked when modelValue is true', () => {
    cy.mount(Checkbox, { props: { modelValue: true, config: {} } })
    cy.get('input[type="checkbox"]').should('be.checked')
  })

  it('uses config.default (false) when no modelValue', () => {
    cy.mount(Checkbox, { props: { config: { default: false } } })
    cy.get('input[type="checkbox"]').should('not.be.checked')
  })

  it('emits update:modelValue true when checked', () => {
    const onUpdate = cy.spy().as('update')
    cy.mount(Checkbox, {
      props: { modelValue: false, config: {}, 'onUpdate:modelValue': onUpdate },
    })
    cy.get('input[type="checkbox"]').check({ force: true })
    cy.get('@update').should('have.been.calledWith', true)
  })

  it('emits custom on/off values from config', () => {
    const onUpdate = cy.spy().as('update')
    cy.mount(Checkbox, {
      props: {
        modelValue: 0,
        config: { on: 1, off: 0 },
        'onUpdate:modelValue': onUpdate,
      },
    })
    cy.get('input[type="checkbox"]').check({ force: true })
    cy.get('@update').should('have.been.calledWith', 1)
  })

  it('is readonly when readonly prop is true', () => {
    cy.mount(Checkbox, { props: { modelValue: false, config: {}, readonly: true } })
    cy.get('.v-input--readonly').should('exist')
  })
})
