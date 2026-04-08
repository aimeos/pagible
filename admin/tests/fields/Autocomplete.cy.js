import Autocomplete from '../../js/fields/Autocomplete.vue'

const staticOptions = [
  { label: 'Paris', value: 'paris' },
  { label: 'London', value: 'london' },
  { label: 'Berlin', value: 'berlin' },
]

describe('Autocomplete', () => {
  it('renders an autocomplete field', () => {
    cy.mount(Autocomplete, { props: { config: { options: staticOptions } } })
    cy.get('.v-autocomplete').should('exist')
  })

  it('displays the selected modelValue', () => {
    cy.mount(Autocomplete, {
      props: {
        modelValue: { label: 'Paris', value: 'paris' },
        config: { options: staticOptions, 'item-title': 'label', 'item-value': 'value' },
      },
    })
    cy.get('.v-autocomplete').should('contain', 'Paris')
  })

  it('shows "No data available" when there are no options', () => {
    cy.mount(Autocomplete, { props: { config: { options: [] } } })
    cy.get('.v-autocomplete').click()
    cy.contains('No data available').should('be.visible')
  })

  it('shows a custom empty text from config', () => {
    cy.mount(Autocomplete, {
      props: { config: { options: [], 'empty-text': 'Nothing here' } },
    })
    cy.get('.v-autocomplete').click()
    cy.contains('Nothing here').should('be.visible')
  })

  it('shows static options when the menu opens', () => {
    cy.mount(Autocomplete, { props: { config: { options: staticOptions } } })
    cy.get('.v-autocomplete').click()
    cy.contains('.v-list-item', 'Paris').should('be.visible')
    cy.contains('.v-list-item', 'London').should('be.visible')
  })

  it('emits error:true when required and value is null', () => {
    const onError = cy.spy().as('error')
    cy.mount(Autocomplete, {
      props: { config: { required: true }, modelValue: null, onError },
    })
    cy.get('@error').should('have.been.calledWith', true)
  })

  it('emits error:false when required and value is present', () => {
    const onError = cy.spy().as('error')
    cy.mount(Autocomplete, {
      props: {
        config: { required: true },
        modelValue: 'paris',
        onError,
      },
    })
    cy.get('@error').should('have.been.calledWith', false)
  })

  it('renders chips for multiple selection', () => {
    cy.mount(Autocomplete, {
      props: {
        modelValue: ['paris', 'london'],
        config: { options: staticOptions, multiple: true },
      },
    })
    cy.get('.v-chip').should('have.length', 2)
  })

  it('renders with a placeholder config without errors', () => {
    cy.mount(Autocomplete, {
      props: { config: { options: staticOptions, placeholder: 'Search cities' } },
    })
    cy.get('.v-autocomplete').should('exist')
  })

  it('is readonly when readonly prop is true', () => {
    cy.mount(Autocomplete, { props: { config: { options: staticOptions }, readonly: true } })
    cy.get('.v-input--readonly').should('exist')
  })
})
