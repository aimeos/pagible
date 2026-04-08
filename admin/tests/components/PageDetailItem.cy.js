import PageDetailItem from '../../js/components/PageDetailItem.vue'

const stubs = {
  PageDetailItemProps: { template: '<div class="props-stub" />' },
  PageDetailItemMeta: { template: '<div class="meta-stub" />' },
  PageDetailItemConfig: { template: '<div class="config-stub" />' },
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
    cy.get('.meta-stub').should('exist')
  })

  it('switches to Config tab when clicked', () => {
    mountDetail()
    cy.contains('.v-tab', 'Config').click()
    cy.get('.config-stub').should('exist')
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
})
