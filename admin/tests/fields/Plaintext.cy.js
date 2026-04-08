import Plaintext from '../../js/fields/Plaintext.vue'

describe('Plaintext (textarea)', () => {
  it('renders a textarea', () => {
    cy.mount(Plaintext, { props: { config: {} } })
    cy.get('.v-textarea').should('exist')
  })

  it('displays the modelValue', () => {
    cy.mount(Plaintext, { props: { modelValue: 'Plain text here', config: {} } })
    cy.get('textarea').first().should('have.value', 'Plain text here')
  })

  it('uses config.default when no modelValue is supplied', () => {
    cy.mount(Plaintext, { props: { config: { default: 'Default plain' } } })
    cy.get('textarea').first().should('have.value', 'Default plain')
  })

  it('applies a custom CSS class from config', () => {
    cy.mount(Plaintext, { props: { config: { class: 'my-class' } } })
    cy.get('.my-class').should('exist')
  })

  it('shows a counter when config.max is set', () => {
    cy.mount(Plaintext, { props: { modelValue: 'hi', config: { max: 50 } } })
    cy.get('.v-counter').should('exist')
  })

  it('emits error:true when value is shorter than config.min', () => {
    const onError = cy.spy().as('error')
    cy.mount(Plaintext, {
      props: { modelValue: 'Hi', config: { min: 10 }, onError },
    })
    cy.get('@error').should('have.been.calledWith', true)
  })

  it('emits error:false when value meets config.min', () => {
    const onError = cy.spy().as('error')
    cy.mount(Plaintext, {
      props: { modelValue: 'Long enough text', config: { min: 5 }, onError },
    })
    cy.get('@error').should('have.been.calledWith', false)
  })

  it('emits update:modelValue as the user types', () => {
    const onUpdate = cy.spy().as('update')
    cy.mount(Plaintext, {
      props: { config: {}, 'onUpdate:modelValue': onUpdate },
    })
    cy.get('textarea').first().type('typed text')
    cy.get('@update').should('have.been.called')
  })

  it('is readonly when readonly prop is true', () => {
    cy.mount(Plaintext, { props: { config: {}, readonly: true } })
    cy.get('.v-input--readonly').should('exist')
  })
})
