import Radio from '../../js/fields/Radio.vue'

const options = [
  { label: 'Red', value: 'red' },
  { label: 'Blue', value: 'blue' },
  { label: 'Green', value: 'green' },
]

describe('Radio', () => {
  it('renders a radio group', () => {
    cy.mount(Radio, { props: { config: { options } } })
    cy.get('.v-radio-group').should('exist')
  })

  it('renders one radio button per option', () => {
    cy.mount(Radio, { props: { config: { options } } })
    cy.get('.v-radio').should('have.length', 3)
  })

  it('shows the correct option labels', () => {
    cy.mount(Radio, { props: { config: { options } } })
    cy.contains('.v-radio', 'Red').should('exist')
    cy.contains('.v-radio', 'Blue').should('exist')
    cy.contains('.v-radio', 'Green').should('exist')
  })

  it('selects the option matching modelValue', () => {
    cy.mount(Radio, { props: { modelValue: 'blue', config: { options } } })
    cy.contains('.v-radio', 'Blue').find('input').should('be.checked')
  })

  it('uses config.default when no modelValue is provided', () => {
    cy.mount(Radio, { props: { config: { options, default: 'green' } } })
    cy.contains('.v-radio', 'Green').find('input').should('be.checked')
  })

  it('emits error:true when required and no value is selected', () => {
    const onError = cy.spy().as('error')
    cy.mount(Radio, {
      props: { config: { required: true }, modelValue: null, onError },
    })
    cy.get('@error').should('have.been.calledWith', true)
  })

  it('emits error:false when required and a value is selected', () => {
    const onError = cy.spy().as('error')
    cy.mount(Radio, {
      props: { config: { required: true }, modelValue: 'red', onError },
    })
    cy.get('@error').should('have.been.calledWith', false)
  })

  it('emits update:modelValue when a radio is selected', () => {
    const onUpdate = cy.spy().as('update')
    cy.mount(Radio, {
      props: { config: { options }, 'onUpdate:modelValue': onUpdate },
    })
    cy.contains('.v-radio', 'Red').find('input').check({ force: true })
    cy.get('@update').should('have.been.calledWith', 'red')
  })
})
