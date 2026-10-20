import { h } from 'vue'
import PageDetailItem from '../../../js/components/PageDetailItem.vue'
import { useUserStore } from '../../../js/stores'

const stubs = {
  PageAccess: { template: '<div class="access-stub" />' },
  PageDetailItemProps: { template: '<div class="props-stub" />' },
  PageDetailItemSection: { template: '<div class="section-stub" />' },
}

const item = {
  id: '1',
  title: 'Test Page',
  name: 'test',
  path: 'test-page',
  status: 1,
  lang: 'en',
}

function mountDetail(props = {}) {
  return cy.mount(PageDetailItem, {
    props: {
      item: { ...item },
      assets: {},
      ...props,
    },
    global: { stubs },
  }).then(() => {
    useUserStore().me = { permission: {} }
  })
}

describe('PageDetailItem', () => {
  beforeEach(() => {
    cy.on('uncaught:exception', () => false)
  })

  it('renders the component', () => {
    mountDetail()
    cy.get('.v-container').should('exist')
  })

  it('renders three tabs: Detail, Meta, Config', () => {
    mountDetail()
    cy.get('.v-tab').should('have.length', 3)
    cy.contains('.v-tab', 'Detail').should('exist')
    cy.contains('.v-tab', 'Meta').should('exist')
    cy.contains('.v-tab', 'Config').should('exist')
  })

  it('shows Detail tab content by default', () => {
    mountDetail()
    cy.get('.props-stub').should('exist')
  })

  it('switches to Meta tab when clicked', () => {
    mountDetail()
    cy.contains('.v-tab', 'Meta').click()
    cy.get('.section-stub').should('exist')
  })

  it('switches to Config tab when clicked', () => {
    mountDetail()
    cy.contains('.v-tab', 'Config').click()
    cy.get('.section-stub').should('exist')
  })

  it('emits update:aside when Detail tab is clicked', () => {
    const onUpdateAside = cy.spy().as('aside')
    cy.mount(PageDetailItem, {
      props: {
        item: { ...item },
        assets: {},
        'onUpdate:aside': onUpdateAside,
      },
      global: { stubs },
    })
    cy.contains('.v-tab', 'Detail').click()
    cy.get('@aside').should('have.been.calledWith', 'meta')
  })

  it('emits update:aside with "count" when Meta tab is clicked', () => {
    const onUpdateAside = cy.spy().as('aside')
    cy.mount(PageDetailItem, {
      props: {
        item: { ...item },
        assets: {},
        'onUpdate:aside': onUpdateAside,
      },
      global: { stubs },
    })
    cy.contains('.v-tab', 'Meta').click()
    cy.get('@aside').should('have.been.calledWith', 'count')
  })

  it('shows immediate access management with page:access permission', () => {
    mountDetail().then(() => {
      useUserStore().me = { permission: { 'page:access': true } }
    })

    cy.contains('.v-tab', 'Access').click()
    cy.get('.access-stub').should('exist')
  })

  it('resets the details form and opens the failed section', () => {
    const reset = cy.stub()
    const showError = cy.stub()
    const stub = (cls, methods) => ({ render: () => h('div', { class: cls }), methods })

    cy.mount(PageDetailItem, {
      props: { item: { ...item }, assets: {} },
      global: {
        stubs: {
          PageAccess: stub('access-stub', {}),
          PageDetailItemProps: stub('props-stub', { reset }),
          PageDetailItemSection: stub('section-stub', { reset() {}, showError }),
        },
      },
    }).then(({ wrapper }) => {
      const vm = wrapper.findComponent(PageDetailItem).vm

      vm.reset()
      expect(reset).to.have.been.calledOnce

      // render the lazy meta window item once before switching back
      vm.tab = 'meta'
      return vm.$nextTick().then(() => {
        vm.tab = 'details'
        vm.error('meta', true)
        return vm.showError()
      }).then(() => {
        expect(vm.tab).to.equal('meta')
        expect(showError).to.have.been.calledOnce
      })
    })
  })
})
