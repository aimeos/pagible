import Date from '../../../js/fields/Date.vue'

describe('Date', () => {
  it('renders a date input', () => {
    cy.mount(Date, { props: { config: {} } })
    cy.get('.v-date-input').should('exist')
  })

  it('shows a placeholder when configured', () => {
    cy.mount(Date, { props: { config: { placeholder: 'YYYY-MM-DD' } } })
    cy.get('input').should('have.attr', 'placeholder', 'YYYY-MM-DD')
  })

  it('emits error:false when required and a value is present', () => {
    const onError = cy.spy().as('error')
    cy.mount(Date, {
      props: { modelValue: '2025-01-15', config: { required: true }, onError },
    })
    cy.get('@error').should('have.been.calledWith', false)
  })

  it('emits error:true when required and value is null', () => {
    const onError = cy.spy().as('error')
    cy.mount(Date, {
      props: { modelValue: null, config: { required: true }, onError },
    })
    cy.get('@error').should('have.been.calledWith', true)
  })

  it('emits error:false when not required and value is null', () => {
    const onError = cy.spy().as('error')
    cy.mount(Date, {
      props: { modelValue: null, config: { required: false }, onError },
    })
    cy.get('@error').should('have.been.calledWith', false)
  })

  it('is readonly when readonly prop is true', () => {
    cy.mount(Date, { props: { config: {}, readonly: true } })
    cy.get('input').should('have.attr', 'readonly')
  })

  it('hides the clear button when required', () => {
    cy.mount(Date, { props: { modelValue: '2025-06-01', config: { required: true } } })
    cy.get('.v-field__clearable').should('not.exist')
  })
})
