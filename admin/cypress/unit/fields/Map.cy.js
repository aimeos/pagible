import Map from '../../../js/fields/Map.vue'

describe('Map', () => {
  const location = { latitude: 52.538456, longitude: 13.409564, zoom: 16 }

  it('renders the selected location in an interactive OpenStreetMap', () => {
    cy.mount(Map, { props: { modelValue: location, config: {} } })

    cy.get('.coordinates').should('not.exist')
    cy.get('input').should('not.exist')
    cy.get('.leaflet-container').should('exist')
    cy.get('.leaflet-marker-pane .location-marker svg').should('exist')
    cy.get('.leaflet-control-zoom').should('exist')
    cy.then(() => {
      const wrapper = Cypress.vueWrapper.findComponent(Map)

      expect(wrapper.vm.map.scrollWheelZoom.enabled()).to.equal(false)
    })
  })

  it('sets coordinates by clicking on the map', () => {
    const onUpdate = cy.spy().as('update')

    cy.mount(Map, {
      props: {
        modelValue: location,
        config: {},
        'onUpdate:modelValue': onUpdate
      }
    })

    cy.get('.leaflet-container').click('center')
    cy.get('@update').should('have.been.called')
    cy.get('@update').then((spy) => {
      expect(spy.lastCall.args[0].latitude).to.be.closeTo(location.latitude, 0.00001)
      expect(spy.lastCall.args[0].longitude).to.be.closeTo(location.longitude, 0.00001)
      expect(spy.lastCall.args[0].zoom).to.equal(16)
    })
  })

  it('zooms to the configured level when setting the first location', () => {
    const onUpdate = cy.spy().as('update')

    cy.mount(Map, {
      props: {
        modelValue: null,
        config: { required: true, zoom: 14 },
        'onUpdate:modelValue': onUpdate
      }
    })

    cy.get('.leaflet-container').click('center')
    cy.get('@update').then((spy) => {
      expect(spy.lastCall.args[0].latitude).to.be.closeTo(0, 0.000001)
      expect(spy.lastCall.args[0].longitude).to.be.closeTo(0, 0.000001)
      expect(spy.lastCall.args[0].zoom).to.equal(14)
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
    cy.get('.map-field').should('have.class', 'invalid')
    cy.get('.leaflet-container').should('exist')
  })

  it('clears the selected location', () => {
    const onUpdate = cy.spy().as('update')

    cy.mount(Map, {
      props: {
        modelValue: location,
        config: {},
        'onUpdate:modelValue': onUpdate
      }
    })

    cy.get('.clear-location').click()
    cy.get('@update').should('have.been.calledWith', null)
  })

  it('does not change a readonly location', () => {
    const onUpdate = cy.spy().as('update')

    cy.mount(Map, {
      props: {
        modelValue: location,
        config: {},
        readonly: true,
        'onUpdate:modelValue': onUpdate
      }
    })

    cy.get('.clear-location').should('not.exist')
    cy.get('.leaflet-container').click('center')
    cy.get('@update').should('not.have.been.called')
  })
})
