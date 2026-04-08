import Range from '../../js/fields/Range.vue'

describe('Range', () => {
  it('renders a range slider', () => {
    cy.mount(Range, { props: { config: {} } })
    cy.get('.v-range-slider').should('exist')
  })

  it('shows the lower bound value in the prepend slot', () => {
    cy.mount(Range, { props: { modelValue: [20, 80], config: {} } })
    cy.get('.v-range-slider .v-input__prepend').should('contain', '20')
  })

  it('shows the upper bound value in the append slot', () => {
    cy.mount(Range, { props: { modelValue: [20, 80], config: {} } })
    cy.get('.v-range-slider .v-input__append').should('contain', '80')
  })

  it('uses config.min and config.max as bounds', () => {
    cy.mount(Range, { props: { config: { min: 10, max: 50 } } })
    cy.get('.v-range-slider [role="slider"]').first().should('have.attr', 'aria-valuemin', '10')
    cy.get('.v-range-slider [role="slider"]').first().should('have.attr', 'aria-valuemax', '50')
  })

  it('defaults to min=0 and max=100 when not configured', () => {
    cy.mount(Range, { props: { config: {} } })
    cy.get('.v-range-slider [role="slider"]').first().should('have.attr', 'aria-valuemin', '0')
    cy.get('.v-range-slider [role="slider"]').first().should('have.attr', 'aria-valuemax', '100')
  })

  it('uses config.default when no modelValue is provided', () => {
    cy.mount(Range, { props: { config: { default: [30, 70] } } })
    // The slider uses config.default internally for thumb positions
    cy.get('.v-range-slider [role="slider"]').first().should('have.attr', 'aria-valuenow', '30')
    cy.get('.v-range-slider [role="slider"]').last().should('have.attr', 'aria-valuenow', '70')
  })

  it('shows empty prepend/append when modelValue is not set', () => {
    cy.mount(Range, { props: { config: {} } })
    cy.get('.v-range-slider .v-input__prepend').should('have.text', '')
    cy.get('.v-range-slider .v-input__append').should('have.text', '')
  })
})
