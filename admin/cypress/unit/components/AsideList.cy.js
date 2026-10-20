import { reactive } from 'vue'
import AsideList from '../../../js/components/AsideList.vue'

const content = [
  {
    key: 'status',
    title: 'Status',
    items: [
      { title: 'Published', value: { publish: 'PUBLISHED' }, icon: 'mdi-check' },
      { title: 'Draft', value: { publish: 'DRAFT' }, icon: 'mdi-pencil' },
    ],
  },
  {
    key: 'trashed',
    title: 'Trashed',
    items: [
      { title: 'With trashed', value: { trashed: 'WITH' }, icon: 'mdi-delete' },
    ],
  },
]

function mountList(filter = {}, props = {}) {
  return cy.mount(AsideList, {
    props: { content, filter, ...props },
  })
}

describe('AsideList', () => {
  it('renders a navigation drawer', () => {
    mountList()
    cy.get('.v-navigation-drawer').should('exist')
  })

  it('renders a list group for each content entry', () => {
    mountList()
    cy.get('.v-list-group').should('have.length', 2)
  })

  it('renders the group title', () => {
    mountList()
    cy.contains('Status').should('exist')
    cy.contains('Trashed').should('exist')
  })

  it('renders a reset button', () => {
    mountList()
    cy.contains('.v-btn', 'Reset').should('exist')
  })

  it('disables reset button when filter is unchanged', () => {
    mountList({})
    cy.contains('.v-btn', 'Reset').should('be.disabled')
  })

  it('renders the counts of the items', () => {
    const counted = [{ key: 'translation', title: 'Translation', items: [
      { title: 'All', value: { translation: null } },
      { title: 'Missing', value: { translation: 'missing' }, count: 3 },
    ] }]

    mountList({}, { content: counted })
    cy.get('.v-list-group').first().find('.v-list-item').first().click({ force: true })
    cy.contains('.v-btn', 'Missing').find('.count').should('have.text', '3')
    cy.contains('.v-btn', 'All').find('.count').should('not.exist')
  })

  it('renders item titles inside groups', () => {
    mountList({ status: 'PUBLISHED' })
    cy.get('.v-list-group').first().find('.v-list-item').first().click({ force: true })
    cy.contains('.v-btn', 'Published').should('exist')
    cy.contains('.v-btn', 'Draft').should('exist')
  })

  it('tells which filter is selected', () => {
    mountList({ publish: 'PUBLISHED' })
    cy.get('.v-list-group').first().find('.v-list-item').first().click({ force: true })
    cy.contains('.v-btn', 'Published').should('have.attr', 'aria-pressed', 'true')
    cy.contains('.v-btn', 'Draft').should('have.attr', 'aria-pressed', 'false')
  })

  it('restores the initial filter when reset is clicked after changes', () => {
    const filter = reactive({ status: 'PUBLISHED', publish: null })
    const onUpdate = cy.spy((patch) => Object.assign(filter, patch)).as('update')

    cy.mount(AsideList, { props: { content, filter, 'onUpdate:filter': onUpdate } })
    cy.get('.v-list-group').first().find('.v-list-item').first().click({ force: true })
    cy.contains('.v-btn', 'Draft').click({ force: true })
    cy.get('@update').should('have.been.calledWith', { publish: 'DRAFT' })
    cy.wrap(filter).should('deep.equal', { status: 'PUBLISHED', publish: 'DRAFT' })
    cy.contains('.v-btn', 'Reset').click({ force: true })
    cy.get('@update').should('have.been.calledWith', { status: 'PUBLISHED', publish: null })
    cy.wrap(filter).should('deep.equal', { status: 'PUBLISHED', publish: null })
  })

  it('sends the defaults to the owner without changing the filter itself', () => {
    const filter = { status: 'PUBLISHED', publish: 'DRAFT' }
    const onUpdate = cy.spy().as('update')

    cy.mount(AsideList, {
      props: { content, filter, defaults: { status: null, publish: null }, 'onUpdate:filter': onUpdate },
    })
    cy.contains('.v-btn', 'Reset').click({ force: true })
    cy.get('@update').should('have.been.calledOnceWith', { status: null, publish: null })
    cy.wrap(filter).should('deep.equal', { status: 'PUBLISHED', publish: 'DRAFT' })
  })

  it('marks only groups whose filter differs from the defaults as active', () => {
    const filter = { status: 'PUBLISHED', trashed: 'WITHOUT' }

    cy.mount(AsideList, {
      props: { content, filter, defaults: { status: null, trashed: 'WITHOUT' } },
    }).then(({ wrapper }) => {
      const vm = wrapper.findComponent(AsideList).vm
      expect(vm.has('status')).to.equal(true)
      expect(vm.has('trashed')).to.equal(false)
      expect(vm.has('missing')).to.equal(false)
    })
    cy.get('.v-list-group').first().find('.v-list-item.active').should('exist')
    cy.get('.v-list-group').eq(1).find('.v-list-item.active').should('not.exist')
  })
})
