import Color from '../../src/fields/Color.vue'

describe('Color', () => {
  it('renders a color input', () => {
    cy.mount(Color, { props: { config: {} } })
    cy.get('.v-color-input').should('exist')
  })

  it('emits error:false for a valid 6-digit hex color', () => {
    const onError = cy.spy().as('error')
    cy.mount(Color, {
      props: { modelValue: '#FF5733', config: {}, onError },
    })
    cy.get('@error').should('have.been.calledWith', false)
  })

  it('emits error:false for a valid 8-digit hex color (with alpha)', () => {
    const onError = cy.spy().as('error')
    cy.mount(Color, {
      props: { modelValue: '#FF573380', config: {}, onError },
    })
    cy.get('@error').should('have.been.calledWith', false)
  })

  it('emits error:true for an invalid hex string', () => {
    const onError = cy.spy().as('error')
    cy.mount(Color, {
      props: { modelValue: 'not-a-color', config: {}, onError },
    })
    cy.get('@error').should('have.been.calledWith', true)
  })

  it('emits error:true for a truncated hex value', () => {
    const onError = cy.spy().as('error')
    cy.mount(Color, {
      props: { modelValue: '#FFF', config: {}, onError },
    })
    cy.get('@error').should('have.been.calledWith', true)
  })

  it('emits error:false for an empty value when not required', () => {
    const onError = cy.spy().as('error')
    cy.mount(Color, {
      props: { modelValue: '', config: { required: false }, onError },
    })
    cy.get('@error').should('have.been.calledWith', false)
  })

  it('emits error:true when required and no value is present', () => {
    const onError = cy.spy().as('error')
    cy.mount(Color, {
      props: { modelValue: '', config: { required: true }, onError },
    })
    cy.get('@error').should('have.been.calledWith', true)
  })

  it('uses config.default when no modelValue is supplied', () => {
    cy.mount(Color, { props: { config: { default: '#123456' } } })
    cy.get('input').should('have.value', '#123456')
  })
})
