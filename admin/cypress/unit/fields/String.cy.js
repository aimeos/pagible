import StringField from '../../../js/fields/String.vue'

describe('String (textarea)', () => {
  it('renders a textarea', () => {
    cy.mount(StringField, { props: { config: {} } })
    cy.get('.v-textarea').should('exist')
  })

  it('displays the modelValue', () => {
    cy.mount(StringField, { props: { modelValue: 'Hello world', config: {} } })
    cy.get('textarea').first().should('have.value', 'Hello world')
  })

  it('uses config.default when no modelValue is provided', () => {
    cy.mount(StringField, { props: { config: { default: 'Default text' } } })
    cy.get('textarea').first().should('have.value', 'Default text')
  })

  it('shows a character counter when config.max is set', () => {
    cy.mount(StringField, {
      props: { modelValue: 'hi', config: { max: 100 } },
    })
    cy.get('.v-counter').should('exist')
  })

  it('applies a custom CSS class from config', () => {
    cy.mount(StringField, { props: { config: { class: 'custom-cls' } } })
    cy.get('.custom-cls').should('exist')
  })

  it('emits error:true when value is shorter than config.min', () => {
    const onError = cy.spy().as('error')
    cy.mount(StringField, {
      props: { modelValue: 'Hi', config: { min: 5 }, onError },
    })
    cy.get('@error').should('have.been.calledWith', true)
  })

  it('emits error:false when value meets config.min', () => {
    const onError = cy.spy().as('error')
    cy.mount(StringField, {
      props: { modelValue: 'Hello', config: { min: 5 }, onError },
    })
    cy.get('@error').should('have.been.calledWith', false)
  })

  it('emits error:true when value exceeds config.max', () => {
    const onError = cy.spy().as('error')
    cy.mount(StringField, {
      props: { modelValue: 'Too long text here', config: { max: 5 }, onError },
    })
    cy.get('@error').should('have.been.calledWith', true)
  })

  it('emits update:modelValue as the user types', () => {
    const onUpdate = cy.spy().as('update')
    cy.mount(StringField, {
      props: { config: {}, 'onUpdate:modelValue': onUpdate },
    })
    cy.get('textarea').first().type('new text')
    cy.get('@update').should('have.been.called')
  })

  it('is readonly when readonly prop is true', () => {
    cy.mount(StringField, { props: { config: {}, readonly: true } })
    cy.get('.v-input--readonly').should('exist')
  })
})
