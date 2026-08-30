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
          provider: {
            defaultClient: {
              cache: { diff: () => ({ complete: true }), evict() {}, gc() {} },
              clearStore: () => Promise.resolve(),
            },
          },
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
  beforeEach(() => {
    document.querySelector('[data-cy-root]').id = 'app'
  })

  afterEach(() => {
    document.querySelector('#app')?.removeAttribute('data-reverb')
  })

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

  it('shows the translated active sort option', () => {
    mountList({}, { 'element:view': true })
    cy.get('.btn-sort button').click()
    cy.contains('.v-overlay .v-btn', 'Name').click()
    cy.get('.btn-sort button').should('contain', 'Name').and('not.contain', 'NAME')
  })

  it('shows title-case sort options', () => {
    mountList({}, { 'element:view': true })
    cy.get('.btn-sort button').click()
    cy.get('.v-overlay .v-btn').then(($buttons) => {
      expect([...$buttons].map((button) => button.textContent.trim())).to.deep.equal([
        'Latest', 'Oldest', 'Latest edit', 'Oldest edit', 'Name', 'Type', 'Editor'
      ])
    })
  })

  it('sorts by latest and oldest edit', () => {
    const query = cy.stub().resolves({
      data: { elements: { data: [], paginatorInfo: { lastPage: 1 } } }
    })

    mountList({}, { 'element:view': true }, { query })

    cy.get('.btn-sort button').click()
    cy.contains('.v-overlay .v-btn', 'Latest edit').click()
    cy.get('.btn-sort button').should('contain', 'Latest edit')
    cy.then(() => {
      expect(query.lastCall.args[0].variables.sort).to.deep.equal([
        { column: 'LATEST_ID', order: 'DESC' }
      ])
    })

    cy.get('.btn-sort button').click()
    cy.contains('.v-overlay .v-btn', 'Oldest edit').click()
    cy.get('.btn-sort button').should('contain', 'Oldest edit')
    cy.then(() => {
      expect(query.lastCall.args[0].variables.sort).to.deep.equal([
        { column: 'LATEST_ID', order: 'ASC' }
      ])
    })
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

  it('uses the Apollo cache for element queries', () => {
    document.querySelector('#app').dataset.reverb = '{}'
    const query = cy.stub().resolves({
      data: { elements: { data: [], paginatorInfo: { lastPage: 1 } } },
    })

    mountList({ embed: true }, { 'element:view': true }, { query }).then(({ wrapper }) => {
      return wrapper.findComponent(ElementListItems).vm.search().then(() => {
        expect(query.lastCall.args[0].fetchPolicy).to.equal('cache-first')
      })
    })
  })

  it('requeries an evicted element list when reactivated', () => {
    document.querySelector('#app').dataset.reverb = '{}'
    const query = cy.stub().resolves({
      data: { elements: { data: [], paginatorInfo: { lastPage: 1 } } },
    })
    const diff = cy.stub().returns({ complete: false })

    mountList({ embed: true }, { 'element:view': true }, {
      query,
      provider: {
        defaultClient: {
          cache: { diff, evict() {}, gc() {} },
          clearStore: () => Promise.resolve(),
        },
      },
    }).then(({ wrapper }) => {
      const vm = wrapper.findComponent(ElementListItems).vm
      const calls = query.callCount
      vm.loading = false

      return vm.revalidate().then(() => {
        expect(diff).to.have.been.calledOnce
        expect(query.callCount).to.equal(calls + 1)
      })
    })
  })

  it('clears the complete Apollo cache before a manual reload', () => {
    const calls = []
    const query = cy.stub().callsFake(() => {
      calls.push('query')
      return Promise.resolve({
        data: { elements: { data: [], paginatorInfo: { lastPage: 1 } } },
      })
    })
    const clearStore = cy.stub().callsFake(() => {
      calls.push('clearStore')
      return Promise.resolve()
    })

    mountList({}, { 'element:view': true }, {
      query,
      provider: {
        defaultClient: {
          cache: { diff: () => ({ complete: true }), evict() {}, gc() {} },
          clearStore,
        },
      },
    }).then(({ wrapper }) => {
      return wrapper.findComponent(ElementListItems).vm.reload().then(() => {
        expect(calls).to.deep.equal(['clearStore', 'query'])
      })
    })
  })

  it('shows loading state initially', () => {
    mountList({}, { 'element:view': true })
    cy.contains('Loading').should('exist')
  })

  it('loads latest files used by shared elements', () => {
    const query = cy.stub().resolves({
      data: {
        elements: {
          data: [{
            id: 'element-1',
            data: '{}',
            latest: {
              data: '{"name":"Shared"}',
              files: [{
                disk: 'private',
                id: 'file-1',
                path: 'published.jpg',
                previews: '{}',
                latest: {
                  data: '{"path":"draft.jpg","previews":{"500":"draft-500.webp"}}',
                  aux: '{}',
                },
              }],
            },
          }],
          paginatorInfo: { lastPage: 1 },
        },
      },
    })

    mountList({}, { 'element:view': true, 'file:view': true }, { query }).then(({ wrapper }) => {
      return wrapper.findComponent(ElementListItems).vm.search().then((items) => {
        expect(items[0].files[0].disk).to.equal('private')
        expect(items[0].files[0].path).to.equal('draft.jpg')
        expect(items[0].files[0].previews).to.deep.equal({ 500: 'draft-500.webp' })
      })
    })
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
