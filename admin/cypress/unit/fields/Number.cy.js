import Number from '../../../js/fields/Number.vue'

describe('Number', () => {
  it('renders a number input', () => {
    cy.mount(Number, { props: { config: {} } })
    cy.get('.v-number-input').should('exist')
  })

  it('displays the modelValue', () => {
    cy.mount(Number, { props: { modelValue: 42, config: {} } })
    cy.get('input').should('have.value', '42')
  })

  it('uses config.default when no modelValue is provided', () => {
    cy.mount(Number, { props: { config: { default: 10 } } })
    cy.get('input').should('have.value', '10')
  })

  it('shows placeholder from config', () => {
    cy.mount(Number, { props: { config: { placeholder: 'Enter number' } } })
    cy.get('input').should('have.attr', 'placeholder', 'Enter number')
  })

  it('emits error:false when value is present and required', () => {
    const onError = cy.spy().as('error')
    cy.mount(Number, {
      props: { config: { required: true }, modelValue: 5, onError },
    })
    cy.get('@error').should('have.been.calledWith', false)
  })

  it('emits update:modelValue when the value changes', () => {
    const onUpdate = cy.spy().as('update')
    cy.mount(Number, {
      props: { config: {}, 'onUpdate:modelValue': onUpdate },
    })
    cy.get('input').clear().type('7')
    cy.get('input').blur()
    cy.get('@update').should('have.been.called')
  })

  it('is readonly when readonly prop is true', () => {
    cy.mount(Number, { props: { config: {}, readonly: true } })
    cy.get('input').should('have.attr', 'readonly')
  })

  it('hides the clear button when required', () => {
    cy.mount(Number, { props: { config: { required: true }, modelValue: 5 } })
    cy.get('.v-field__clearable').should('not.exist')
  })
})
