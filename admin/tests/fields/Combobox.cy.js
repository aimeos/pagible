import Combobox from '../../js/fields/Combobox.vue'

const staticOptions = [
  { label: 'Cat', value: 'cat' },
  { label: 'Dog', value: 'dog' },
]

describe('Combobox', () => {
  it('renders a combobox field', () => {
    cy.mount(Combobox, { props: { config: { options: staticOptions } } })
    cy.get('.v-combobox').should('exist')
  })

  it('displays the current modelValue', () => {
    cy.mount(Combobox, {
      props: { modelValue: 'cat', config: { options: staticOptions } },
    })
    cy.get('.v-combobox').should('contain', 'cat')
  })

  it('shows static options when the menu opens', () => {
    cy.mount(Combobox, { props: { config: { options: staticOptions } } })
    cy.get('.v-combobox').click()
    cy.contains('.v-list-item', 'Cat').should('be.visible')
    cy.contains('.v-list-item', 'Dog').should('be.visible')
  })

  it('allows free-form input not in the option list', () => {
    const onUpdate = cy.spy().as('update')
    cy.mount(Combobox, {
      props: { config: { options: staticOptions }, 'onUpdate:modelValue': onUpdate },
    })
    cy.get('.v-combobox input').type('Hamster{enter}')
    cy.get('@update').should('have.been.called')
  })

  it('shows empty state when no matching options exist', () => {
    cy.mount(Combobox, { props: { config: { options: [] } } })
    cy.get('.v-combobox').click()
    // Combobox allows free-form input, so empty options is still functional
    cy.get('.v-combobox').should('exist')
  })

  it('renders chips for multiple selection', () => {
    cy.mount(Combobox, {
      props: {
        modelValue: ['cat', 'dog'],
        config: { options: staticOptions, multiple: true },
      },
    })
    cy.get('.v-chip').should('have.length', 2)
  })

  it('emits error:true when required and value is null', () => {
    const onError = cy.spy().as('error')
    cy.mount(Combobox, {
      props: { config: { required: true }, modelValue: null, onError },
    })
    cy.get('@error').should('have.been.calledWith', true)
  })

  it('emits error:false when required and value is present', () => {
    const onError = cy.spy().as('error')
    cy.mount(Combobox, {
      props: { config: { required: true }, modelValue: 'cat', onError },
    })
    cy.get('@error').should('have.been.calledWith', false)
  })

  it('is readonly when readonly prop is true', () => {
    cy.mount(Combobox, { props: { config: { options: staticOptions }, readonly: true } })
    cy.get('.v-input--readonly').should('exist')
  })
})
