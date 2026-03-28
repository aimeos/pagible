import Slider from '../../src/fields/Slider.vue'

describe('Slider', () => {
  it('renders a slider', () => {
    cy.mount(Slider, { props: { config: {} } })
    cy.get('.v-slider').should('exist')
  })

  it('shows the current value in the append slot', () => {
    cy.mount(Slider, { props: { modelValue: 42, config: {} } })
    cy.get('.v-slider .v-input__append').should('contain', '42')
  })

  it('uses config.default when no modelValue is provided', () => {
    cy.mount(Slider, { props: { config: { default: 25 } } })
    // The slider uses config.default internally for its position
    cy.get('.v-slider [role="slider"]').should('have.attr', 'aria-valuenow', '25')
  })

  it('applies config.min and config.max to the slider', () => {
    cy.mount(Slider, { props: { config: { min: 5, max: 95 } } })
    cy.get('.v-slider [role="slider"]').should('have.attr', 'aria-valuemin', '5')
    cy.get('.v-slider [role="slider"]').should('have.attr', 'aria-valuemax', '95')
  })

  it('defaults to min=0 and max=100', () => {
    cy.mount(Slider, { props: { config: {} } })
    cy.get('.v-slider [role="slider"]').should('have.attr', 'aria-valuemin', '0')
    cy.get('.v-slider [role="slider"]').should('have.attr', 'aria-valuemax', '100')
  })

  it('renders with a step configuration', () => {
    cy.mount(Slider, { props: { config: { step: 5 } } })
    cy.get('.v-slider').should('exist')
  })
})
