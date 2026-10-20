import Autocomplete from '../../../js/fields/Autocomplete.vue'

const staticOptions = [
  { label: 'Paris', value: 'paris' },
  { label: 'London', value: 'london' },
  { label: 'Berlin', value: 'berlin' }
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
        config: { options: staticOptions, 'item-title': 'label', 'item-value': 'value' }
      }
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
      props: { config: { options: [], 'empty-text': 'Nothing here' } }
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

  it('accepts scalar GraphQL result lists', () => {
    cy.mount(Autocomplete, {
      props: {
        config: {
          'api-type': 'GQL',
          query: 'query{access}',
          'list-key': 'access',
          'item-value': 'value'
        }
      },
      global: {
        mocks: {
          $apollo: {
            query: () => Promise.resolve({ data: { access: ['member', 'premium'] } })
          }
        }
      }
    })
    cy.get('.v-autocomplete').click()
    cy.contains('.v-list-item', 'member').should('be.visible')
    cy.contains('.v-list-item', 'premium').should('be.visible')
  })

  it('emits error:true when required and value is null', () => {
    const onError = cy.spy().as('error')
    cy.mount(Autocomplete, {
      props: { config: { required: true }, modelValue: null, onError }
    })
    cy.get('@error').should('have.been.calledWith', true)
  })

  it('emits error:false when required and value is present', () => {
    const onError = cy.spy().as('error')
    cy.mount(Autocomplete, {
      props: {
        config: { required: true },
        modelValue: 'paris',
        onError
      }
    })
    cy.get('@error').should('have.been.calledWith', false)
  })

  it('renders chips for multiple selection', () => {
    cy.mount(Autocomplete, {
      props: {
        modelValue: ['paris', 'london'],
        config: { options: staticOptions, multiple: true }
      }
    })
    cy.get('.v-chip').should('have.length', 2)
  })

  it('renders with a placeholder config without errors', () => {
    cy.mount(Autocomplete, {
      props: { config: { options: staticOptions, placeholder: 'Search cities' } }
    })
    cy.get('.v-autocomplete').should('exist')
  })

  it('is readonly when readonly prop is true', () => {
    cy.mount(Autocomplete, { props: { config: { options: staticOptions }, readonly: true } })
    cy.get('.v-input--readonly').should('exist')
  })

  it('resets loading after a failed request', () => {
    cy.mount(Autocomplete, {
      props: { config: { 'api-type': 'GQL', query: 'query{access}' } },
      global: { mocks: { $apollo: { query: () => Promise.reject(new Error('failed')) } } }
    }).then(({ wrapper }) => {
      const vm = wrapper.findComponent(Autocomplete).vm
      vm.graphql('a')
      expect(vm.loading).to.equal(true)
      cy.wrap(vm).its('loading').should('equal', false)
    })
  })

  it('resets loading for an invalid configured query', () => {
    const query = cy.stub()

    cy.mount(Autocomplete, {
      props: { config: { 'api-type': 'GQL', query: 'query{' } },
      global: { mocks: { $apollo: { query }, $log: cy.stub().as('log') } }
    }).then(({ wrapper }) => {
      const vm = wrapper.findComponent(Autocomplete).vm

      expect(() => vm.graphql('a')).not.to.throw()
      cy.wrap(vm).its('loading').should('equal', false)
      cy.get('@log').should('have.been.called').then(() => {
        expect(query).not.to.have.been.called
      })
    })
  })

  it('ignores stale responses of older requests', () => {
    const resolvers = []
    cy.mount(Autocomplete, {
      props: { config: { 'api-type': 'GQL', query: 'query{access}', 'list-key': 'access' } },
      global: {
        mocks: { $apollo: { query: () => new Promise((resolve) => resolvers.push(resolve)) } }
      }
    }).then(({ wrapper }) => {
      const vm = wrapper.findComponent(Autocomplete).vm
      vm.graphql('a')
      vm.graphql('ab')

      cy.wrap(resolvers).should('have.length', 2).then(() => resolvers[1]({ data: { access: ['new'] } }))
      cy.wrap(vm).its('list').should('deep.equal', ['new']).then(() => {
        resolvers[0]({ data: { access: ['old'] } })
      })
      cy.wrap(vm).its('loading').should('equal', false)
      cy.wrap(vm).its('list').should('deep.equal', ['new'])
    })
  })

  it('accepts an array as modelValue without prop warnings', () => {
    cy.window().then((win) => cy.spy(win.console, 'warn').as('warn'))
    cy.mount(Autocomplete, {
      props: { modelValue: [staticOptions[0]], config: { options: staticOptions, multiple: true } }
    })
    cy.get('@warn').should((spy) => {
      expect(spy.getCalls().some((call) => String(call.args[0]).includes('Invalid prop'))).to.equal(false)
    })
  })

  it('searches only when the menu opens', () => {
    cy.mount(Autocomplete, {
      props: { config: { 'api-type': 'GQL', query: 'query{access}' } },
      global: { mocks: { $apollo: { query: () => Promise.resolve({ data: {} }) } } }
    }).then(({ wrapper }) => {
      const vm = wrapper.findComponent(Autocomplete).vm
      const spy = cy.spy().as('search')
      spy.cancel = () => {}
      vm.debouncedGraphql = spy

      const field = wrapper.findComponent({ name: 'VAutocomplete' })
      field.vm.$emit('update:menu', false)
      expect(spy).not.to.have.been.called
      field.vm.$emit('update:menu', true)
      expect(spy).to.have.been.calledOnceWith('')
    })
  })

  it('uses config.default only when no value is set', () => {
    cy.mount(Autocomplete, { props: { config: { options: staticOptions, default: 'paris' } } })
    cy.get('.v-autocomplete').should('contain', 'Paris')
  })

  it('keeps a cleared selection empty instead of showing the default', () => {
    cy.mount(Autocomplete, { props: { modelValue: null, config: { options: staticOptions, default: 'paris' } } })
    cy.get('.v-autocomplete').should('not.contain', 'Paris')
  })
})
