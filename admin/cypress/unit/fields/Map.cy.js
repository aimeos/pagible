import Map from '../../../js/fields/Map.vue'

describe('Map', () => {
  const location = { latitude: 52.538456, longitude: 13.409564, zoom: 16 }

  it('renders coordinates and an OpenStreetMap preview', () => {
    cy.mount(Map, { props: { modelValue: location, config: {} } })

    cy.get('.latitude input').should('have.value', '52.538456')
    cy.get('.longitude input').should('have.value', '13.409564')
    cy.get('.zoom input').should('have.value', '16')
    cy.get('iframe')
      .should('have.attr', 'src')
      .and('include', 'https://www.openstreetmap.org/export/embed.html?')
      .and('include', 'marker=52.538456%2C13.409564')
    cy.get('.preview a')
      .should('have.attr', 'href')
      .and('include', '#map=16/52.538456/13.409564')
  })

  it('emits a structured location when coordinates change', () => {
    const onUpdate = cy.spy().as('update')

    cy.mount(Map, {
      props: {
        modelValue: location,
        config: {},
        'onUpdate:modelValue': onUpdate
      }
    })

    cy.get('.latitude input').clear().type('52.54').blur()
    cy.get('@update').should('have.been.called')
    cy.get('@update').then((spy) => {
      expect(spy.lastCall.args[0]).to.deep.equal({
        latitude: 52.54,
        longitude: 13.409564,
        zoom: 16
      })
    })
  })

  it('marks an incomplete required location as invalid', () => {
    const onError = cy.spy().as('error')

    cy.mount(Map, {
      props: {
        modelValue: { latitude: 52.538456, zoom: 16 },
        config: { required: true },
        onError
      }
    })

    cy.get('@error').should('have.been.calledWith', true)
    cy.get('iframe').should('not.exist')
  })

  it('is readonly when configured', () => {
    cy.mount(Map, { props: { modelValue: location, config: {}, readonly: true } })
    cy.get('input').should('have.attr', 'readonly')
  })
})
