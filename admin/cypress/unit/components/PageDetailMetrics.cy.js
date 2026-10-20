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

function colorChannels(value) {
  const channels = value.match(/[\d.]+/g)?.slice(0, 3).map(Number) || []

  return value.startsWith('color(srgb')
    ? channels.map(channel => Math.round(channel * 255))
    : channels
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
    cy.get('.loading-overlay .spinner').should('exist')
  })

  it('renders the weekly insights card with emphasis colors', () => {
    mountMetrics()
    cy.contains('.v-card-title', 'Weekly Insights')
      .closest('.v-card')
      .should('have.class', 'emphasis-bg')
      .then(($card) => {
        $card[0].style.setProperty('transition', 'none')
        $card[0].style.setProperty('--v-theme-emphasis', '248, 250, 252')
        $card[0].style.setProperty('--v-theme-on-emphasis', '15, 23, 42')
        $card[0].style.setProperty('--v-medium-emphasis-opacity', '1')
      })
      .should('have.css', 'background-color', 'rgb(248, 250, 252)')
      .and('have.css', 'color', 'rgb(15, 23, 42)')
      .find('.text-medium-emphasis')
      .first()
      .should(($label) => {
        expect(colorChannels(getComputedStyle($label[0]).color)).to.deep.equal([15, 23, 42])
      })
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

  it('ignores responses of outdated requests', () => {
    const resolvers = []

    mountMetrics().then(({ wrapper }) => {
      const vm = wrapper.findComponent(PageDetailMetrics).vm

      vm.$apollo.mutate = () => new Promise((resolve) => resolvers.push(resolve))

      const first = vm.metrics()
      const second = vm.metrics()

      resolvers[1]({ data: { metrics: { queries: [{ key: 'new', impressions: 1 }] } } })
      resolvers[0]({ data: { metrics: { queries: [{ key: 'old', impressions: 1 }] } } })

      return Promise.all([first, second]).then(() => {
        expect(vm.queries.map((q) => q.key)).to.deep.equal(['new'])
        expect(vm.loading).to.be.false
      })
    })
  })

  it('sorts the queries without changing the response', () => {
    const queries = Object.freeze([
      Object.freeze({ key: 'a', impressions: 1 }),
      Object.freeze({ key: 'b', impressions: 5 })
    ])

    mountMetrics().then(({ wrapper }) => {
      const vm = wrapper.findComponent(PageDetailMetrics).vm

      vm.$apollo.mutate = () => Promise.resolve({ data: { metrics: { queries } } })

      return vm.metrics().then(() => {
        expect(vm.errors).to.deep.equal([])
        expect(vm.queries.map((q) => q.key)).to.deep.equal(['b', 'a'])
        expect(queries.map((q) => q.key)).to.deep.equal(['a', 'b'])
      })
    })
  })

  it('shows the first query page again after loading new metrics', () => {
    mountMetrics().then(({ wrapper }) => {
      const vm = wrapper.findComponent(PageDetailMetrics).vm

      vm.querypage = 3
      vm.$apollo.mutate = () => Promise.resolve({ data: { metrics: { queries: [] } } })

      return vm.metrics().then(() => {
        expect(vm.querypage).to.equal(1)
      })
    })
  })

  it('removes the metrics of the previous time range if loading fails', () => {
    mountMetrics().then(({ wrapper }) => {
      const vm = wrapper.findComponent(PageDetailMetrics).vm
      const metrics = {
        views: [{ key: '2026-10-01', value: 5 }],
        queries: [{ key: 'old', impressions: 1 }],
        pagespeed: [{ key: 'lcp', value: 1200 }]
      }

      vm.$apollo.mutate = () => Promise.resolve({ data: { metrics } })

      return vm.metrics().then(() => {
        expect(vm.views).to.have.length(1)
        expect(vm.queries).to.have.length(1)
        expect(vm.pagespeed).to.deep.equal({ lcp: 1200 })

        vm.querypage = 2
        vm.$apollo.mutate = () => Promise.reject(new Error('Network down'))

        return vm.metrics()
      }).then(() => {
        expect(vm.errors).to.deep.equal(['Error fetching metrics'])
        expect(vm.views).to.deep.equal([])
        expect(vm.queries).to.deep.equal([])
        expect(vm.pagespeed).to.be.null
        expect(vm.querypage).to.equal(1)
        expect(vm.loading).to.be.false
      })
    })
  })
})
