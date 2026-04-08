import Select from '../../js/fields/Select.vue'

const options = [
  { label: 'Apple', value: 'apple' },
  { label: 'Banana', value: 'banana' },
]

describe('Select', () => {
  it('renders a select element', () => {
    cy.mount(Select, { props: { config: { options } } })
    cy.get('.v-select').should('exist')
  })

  it('shows the current modelValue as the selected item', () => {
    cy.mount(Select, { props: { modelValue: 'banana', config: { options } } })
    cy.get('.v-select').should('contain', 'Banana')
  })

  it('uses config.default when no modelValue is provided', () => {
    cy.mount(Select, { props: { config: { options, default: 'apple' } } })
    cy.get('.v-select').should('contain', 'Apple')
  })

  it('renders with a placeholder config without errors', () => {
    cy.mount(Select, { props: { config: { options, placeholder: 'Choose fruit' } } })
    cy.get('.v-select').should('exist')
  })

  it('emits error:true when required and no value is present', () => {
    const onError = cy.spy().as('error')
    cy.mount(Select, {
      props: { config: { required: true }, modelValue: null, onError },
    })
    cy.get('@error').should('have.been.calledWith', true)
  })

  it('emits error:false when required and a value is present', () => {
    const onError = cy.spy().as('error')
    cy.mount(Select, {
      props: { config: { required: true }, modelValue: 'apple', onError },
    })
    cy.get('@error').should('have.been.calledWith', false)
  })

  it('emits update:modelValue when an option is selected', () => {
    const onUpdate = cy.spy().as('update')
    cy.mount(Select, {
      props: { config: { options }, 'onUpdate:modelValue': onUpdate },
    })
    cy.get('.v-select').click()
    cy.contains('.v-list-item', 'Banana').click()
    cy.get('@update').should('have.been.calledWith', 'banana')
  })

  it('renders chips for multi-select', () => {
    cy.mount(Select, {
      props: { modelValue: ['apple', 'banana'], config: { options, multiple: true } },
    })
    cy.get('.v-chip').should('have.length', 2)
  })

  it('is readonly when the readonly prop is true', () => {
    cy.mount(Select, { props: { config: { options }, readonly: true } })
    cy.get('.v-input--readonly').should('exist')
  })
})
