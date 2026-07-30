import ElementListItems from '../../../js/components/ElementListItems.vue'
import { useUserStore } from '../../../js/stores'

const stubs = {
  SchemaItems: { template: '<div class="schema-items-stub" />' },
}

function mountList(props = {}, perms = {}, apollo = {}) {
  return cy.mount(ElementListItems, {
    props: {
      ...props,
    },
    global: {
      stubs,
      provide: {
        debounce: (fn) => fn,
      },
      mocks: {
        $apollo: {
          query: () => Promise.resolve({
            data: { elements: { data: [], paginatorInfo: { lastPage: 1 } } },
          }),
          mutate: () => Promise.resolve({ data: {} }),
          provider: { defaultClient: { cache: { evict() {}, gc() {} } } },
          ...apollo,
        },
      },
    },
  }).then(({ wrapper }) => {
    const user = useUserStore()
    user.me = { permission: perms }

    return { wrapper }
  })
}

describe('ElementListItems', () => {
  it('renders the component', () => {
    mountList({}, { 'element:view': true })
    cy.get('.header').should('exist')
  })

  it('renders search field', () => {
    mountList({}, { 'element:view': true })
    cy.get('.v-text-field').should('exist')
  })

  it('renders sort menu button', () => {
    mountList({}, { 'element:view': true })
    cy.get('.btn-sort button').should('exist')
  })

  it('renders checkbox for bulk selection', () => {
    mountList({}, { 'element:view': true })
    cy.get('.v-checkbox-btn').should('exist')
  })

  it('shows add button with element:add permission and not embed', () => {
    mountList({ embed: false }, { 'element:view': true, 'element:add': true })
    cy.get('button.btn-add').should('exist')
  })

  it('hides add button when embed is true', () => {
    mountList({ embed: true }, { 'element:view': true, 'element:add': true })
    cy.get('button.btn-add').should('not.exist')
  })

  it('hides add button without element:add permission', () => {
    mountList({}, { 'element:view': true })
    cy.get('button.btn-add').should('not.exist')
  })

  it('renders reload button', () => {
    mountList({}, { 'element:view': true })
    cy.get('button.btn-reload').should('exist')
  })

  it('shows loading state initially', () => {
    mountList({}, { 'element:view': true })
    cy.contains('Loading').should('exist')
  })

  it('edits one item without changing the bulk selection', () => {
    const mutate = cy.stub().resolves({ data: { bulkElement: { ids: ['element-1'] } } })

    mountList({}, { 'element:save': true, 'element:view': true }, { mutate }).then(({ wrapper }) => {
      const vm = wrapper.findComponent(ElementListItems).vm
      const item = { id: 'element-1' }
      vm.items = [item, { id: 'element-2' }]
      vm.checked = new Set(['element-2'])

      vm.edit(item)
      expect(vm.editIds).to.deep.equal(['element-1'])
      expect(vm.editDialog).to.equal(true)

      return vm.save('de').then(() => {
        expect(mutate).to.have.been.calledOnce
        expect(mutate.firstCall.args[0].variables).to.deep.equal({
          id: ['element-1'],
          input: { lang: 'de' },
        })
        expect([...vm.checked]).to.deep.equal(['element-2'])
      })
    })
  })
})
