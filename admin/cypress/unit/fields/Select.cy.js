import Select from '../../../js/fields/Select.vue'

const options = [
  { label: 'Apple', value: 'apple' },
  { label: 'Banana', value: 'banana' }
]

describe('Select', () => {
  it('renders a select element', () => {
    cy.mount(Select, { props: { config: { options } } })
    cy.get('.v-select').should('exist')
  })

  it('translates configured option labels', () => {
    const translate = cy.stub().returns('Abonnement')
    const items = Select.computed.items.call({
      config: { options: [{ label: 'Subscription', value: 'subscription' }] },
      $pgettext: translate
    })

    expect(items[0].label).to.equal('Abonnement')
    expect(translate).to.have.been.calledWith('op', 'Subscription')
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
      props: { config: { required: true }, modelValue: null, onError }
    })
    cy.get('@error').should('have.been.calledWith', true)
  })

  it('emits error:true when a required multi-select is empty', () => {
    const onError = cy.spy().as('error')
    cy.mount(Select, {
      props: { config: { multiple: true, required: true }, modelValue: [], onError }
    })
    cy.get('@error').should('have.been.calledWith', true)
  })

  it('emits error:false when required and a value is present', () => {
    const onError = cy.spy().as('error')
    cy.mount(Select, {
      props: { config: { required: true }, modelValue: 'apple', onError }
    })
    cy.get('@error').should('have.been.calledWith', false)
  })

  it('emits update:modelValue when an option is selected', () => {
    const onUpdate = cy.spy().as('update')
    cy.mount(Select, {
      props: { config: { options }, 'onUpdate:modelValue': onUpdate }
    })
    cy.get('.v-select').click()
    cy.contains('.v-list-item', 'Banana').click()
    cy.get('@update').should('have.been.calledWith', 'banana')
  })

  it('renders chips for multi-select', () => {
    cy.mount(Select, {
      props: { modelValue: ['apple', 'banana'], config: { options, multiple: true } }
    })
    cy.get('.v-chip').should('have.length', 2)
  })

  it('is readonly when the readonly prop is true', () => {
    cy.mount(Select, { props: { config: { options }, readonly: true } })
    cy.get('.v-input--readonly').should('exist')
  })

  it('uses an empty list as fallback for multiple selections', () => {
    const onError = cy.spy().as('error')
    cy.mount(Select, { props: { config: { options, multiple: true }, onError } })
    cy.get('.v-select').should('exist')
    cy.get('.v-chip').should('not.exist')
    cy.get('@error').should('have.been.calledWith', false)
  })

  it('accepts numeric option values', () => {
    cy.window().then((win) => cy.spy(win.console, 'warn').as('warn'))
    cy.mount(Select, {
      props: { modelValue: 2, config: { options: [{ label: 'One', value: 1 }, { label: 'Two', value: 2 }] } },
    })
    cy.get('.v-select').should('contain', 'Two')
    cy.get('@warn').should('not.have.been.calledWithMatch', /Invalid prop/)
  })
})
