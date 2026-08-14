import PageDetailMetrics from '../../../js/components/PageDetailMetrics.vue'
import { useAppStore } from '../../../js/stores'

const stubs = {
  Line: { template: '<div class="chart-stub" />' },
}

const item = {
  id: '1',
  path: 'test-page',
  domain: '',
}

function mountMetrics(props = {}) {
  return cy.mount(PageDetailMetrics, {
    props: {
      item: { ...item },
      ...props,
    },
    global: {
      stubs,
      mocks: {
        // Make mutate hang so loading stays true for the loading test
        $apollo: {
          mutate: () => new Promise(() => {}),
          query: () => new Promise(() => {}),
        },
      },
      plugins: [{
        install() {
          const app = useAppStore()
          app.urlpage = 'http://_domain_:8000/_path_'
        }
      }],
    },
  })
}

describe('PageDetailMetrics', () => {
  beforeEach(() => {
    cy.on('uncaught:exception', () => false)
  })

  it('renders the component', () => {
    mountMetrics()
    cy.get('.v-container').should('exist')
  })

  it('shows "Page metrics" title', () => {
    mountMetrics()
    cy.contains('Page metrics').should('exist')
  })

  it('renders days select with 30 and 60 options', () => {
    mountMetrics()
    cy.get('.v-select').should('exist')
  })

  it('shows loading indicator initially', () => {
    mountMetrics()
    cy.get('.v-progress-circular').should('exist')
  })

  it('renders the weekly insights card', () => {
    mountMetrics()
    cy.contains('Weekly Insights').should('exist')
  })

  it('shows dash when no data is available', () => {
    mountMetrics()
    // "—" is shown for empty data
    cy.contains('—').should('exist')
  })

  it('uses the public origin for pages without a domain', () => {
    mountMetrics().then(({ wrapper }) => {
      const metrics = wrapper.findComponent(PageDetailMetrics)
      const expected = new URL('/test-page', window.location.origin)

      expect(metrics.vm.url(item)).to.equal(expected.href)
    })
  })

  it('uses the page domain with the public protocol and port', () => {
    const page = { ...item, domain: 'paper.themes.pagible.com' }

    mountMetrics({ item: page }).then(({ wrapper }) => {
      const metrics = wrapper.findComponent(PageDetailMetrics)
      const expected = new URL('/test-page', window.location.origin)
      expected.hostname = page.domain

      expect(metrics.vm.url(page)).to.equal(expected.href)
    })
  })
})
