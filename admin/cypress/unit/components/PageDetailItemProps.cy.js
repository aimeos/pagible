import PageDetailItemProps from '../../../js/components/PageDetailItemProps.vue'
import { useAppStore, useLanguageStore, useUserStore, useSchemaStore } from '../../../js/stores'

const stubs = {
}

const item = {
  id: '1',
  title: 'Test Page',
  name: 'test',
  path: 'test-page',
  status: 1,
  lang: 'en',
  theme: 'cms',
  type: 'page',
  tag: '',
  cache: 5,
  to: '',
  domain: '',
}

function mountProps(props = {}, perms = {}, apollo = {}) {
  return cy.mount(PageDetailItemProps, {
    props: {
      item: { ...item },
      ...props,
    },
    global: {
      mocks: {
        $apollo: {
          query: () => Promise.resolve({ data: { pages: { data: [] } } }),
          ...apollo,
        },
      },
      stubs,
      provide: {
        debounce: (fn) => fn,
        locales: () => ['en', 'de'],
        slugify: (val) => val?.toLowerCase().replace(/\s+/g, '-') || '',
      },
    },
  }).then(() => {
    const user = useUserStore()
    user.me = { permission: perms }
    const schemas = useSchemaStore()
    schemas.themes = { cms: { types: { page: {} } } }
    const app = useAppStore()
    app.multidomain = false
  })
}

// mounts the component with the permissions and languages already set when it's created
function mountLangs(available, props = {}, perms = { 'page:save': true }) {
  return cy.mount(PageDetailItemProps, {
    props: { item: { ...item }, ...props },
    global: {
      mocks: { $apollo: { query: () => Promise.resolve({ data: { pages: { data: [] } } }) } },
      provide: { debounce: (fn) => fn },
      plugins: [{
        install() {
          useUserStore().me = { permission: perms }
          useLanguageStore().available = available
          useSchemaStore().themes = { cms: { types: { page: {} } } }
          useAppStore().multidomain = false
        }
      }],
    },
  }).then(({ wrapper }) => wrapper.findComponent(PageDetailItemProps))
}

describe('PageDetailItemProps', () => {
  describe('language', () => {
    it('fills in the only language automatically and hides the selector', () => {
      mountLangs(['de'], { item: { ...item, lang: '' } }).then((comp) => {
        expect(comp.vm.item.lang).to.equal('de')
        expect(comp.emitted('change')).to.have.length(1)
      })
      cy.get('.v-select').contains('Language').should('not.exist')
    })

    it('does not fill in the language without permission', () => {
      mountLangs(['de'], { item: { ...item, lang: '' } }, {}).then((comp) => {
        expect(comp.vm.item.lang).to.equal('')
        expect(comp.emitted('change')).to.equal(undefined)
      })
      cy.get('.v-select').contains('Language').should('exist')
    })

    it('shows the selector if the page uses a language which is not configured', () => {
      mountLangs(['de'], { item: { ...item, lang: 'en' } }).then((comp) => {
        expect(comp.vm.item.lang).to.equal('en')
        expect(comp.emitted('change')).to.equal(undefined)
      })
      cy.get('.v-select').contains('Language').should('exist')
    })

    it('asks for the language if several are available', () => {
      mountLangs(['en', 'de'], { item: { ...item, lang: '' } }).then((comp) => {
        expect(comp.vm.item.lang).to.equal('')
      })
      cy.get('.v-select').contains('Language').should('exist')
    })
  })

  it('renders the component', () => {
    mountProps()
    cy.get('.v-container').should('exist')
  })

  it('renders status select', () => {
    mountProps()
    cy.get('.v-select').should('exist')
  })

  it('renders title field with value', () => {
    mountProps()
    cy.get('input').should('exist')
  })

  it('renders path field', () => {
    mountProps()
    // There should be multiple text fields
    cy.get('.v-text-field').should('have.length.greaterThan', 2)
  })

  it('renders cache select', () => {
    mountProps()
    cy.get('.v-select').should('have.length.greaterThan', 1)
  })

  it('renders redirect URL field', () => {
    mountProps()
    cy.get('.v-text-field').should('exist')
  })

  it('makes fields readonly without page:save permission', () => {
    mountProps()
    cy.get('input.v-field__input').first().should('have.attr', 'readonly')
  })

  it('makes fields editable with page:save permission', () => {
    mountProps({}, { 'page:save': true })
    cy.get('input.v-field__input').first().should('not.have.attr', 'readonly')
  })

  it('does not show domain field when multidomain is false', () => {
    mountProps()
    cy.contains('Domain').should('not.exist')
  })

  it('checks the published route while excluding the current page ID', () => {
    const query = cy.stub().resolves({ data: { pages: { data: [{ id: 'page-id' }] } } })
    const current = { ...item, id: 'page-id', path: 'features', domain: 'dev.example' }

    mountProps({ item: current }, { 'page:save': true }, { query }).then(({ wrapper }) => {
      const component = wrapper.findComponent(PageDetailItemProps)

      return component.vm.checkPath().then(() => {
        expect(query).to.have.been.called
        expect(query.lastCall.args[0].variables).to.deep.equal({
          filter: {
            path: 'features',
            domain: 'dev.example',
          },
        })
        expect(component.vm.messages.path).to.deep.equal([])
      })
    })
  })

  it('shows an error when another published page uses the route', () => {
    const query = cy.stub().resolves({ data: { pages: { data: [{ id: 'another-page' }] } } })

    mountProps({}, { 'page:save': true }, { query }).then(({ wrapper }) => {
      const component = wrapper.findComponent(PageDetailItemProps)

      return component.vm.checkPath().then(() => {
        expect(component.vm.messages.path).to.deep.equal([
          'The path is already in use by another page',
        ])
      })
    })
  })
})
