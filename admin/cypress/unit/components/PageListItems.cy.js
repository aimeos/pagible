import PageListItems from '../../../js/components/PageListItems.vue'
import { useUserStore } from '../../../js/stores'

const stubs = {
  Draggable: {
    template: '<div class="draggable-stub" />',
    props: ['modelValue'],
    data() { return { statsFlat: [] } },
    methods: { getSiblings() { return [] }, add() {}, remove() {}, move() {}, addMulti() {} },
  },
}

function mountList(props = {}, perms = {}, apollo = {}) {
  return cy.mount(PageListItems, {
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
            data: { pages: { data: [], paginatorInfo: { currentPage: 1, lastPage: 1 } } }
          }),
          mutate: () => Promise.resolve({ data: {} }),
          provider: {
            defaultClient: {
              cache: { evict() {}, gc() {} },
            },
          },
          ...apollo,
        },
      },
      plugins: [{
        install() {
          const user = useUserStore()
          user.me = { permission: perms }
        }
      }],
    },
  })
}

describe('PageListItems', () => {
  beforeEach(() => {
    document.querySelector('[data-cy-root]').id = 'app'
    cy.on('uncaught:exception', () => false)
  })

  afterEach(() => {
    document.querySelector('#app')?.removeAttribute('data-reverb')
  })

  it('renders the component', () => {
    mountList({}, { 'page:view': true })
    cy.get('.header').should('exist')
  })

  it('uses the surface color for the drag placeholder', () => {
    mountList({}, { 'page:view': true })
    cy.get('.draggable-stub').then(($tree) => {
      const wrapper = document.createElement('div')
      const inner = document.createElement('div')

      wrapper.className = 'drag-placeholder-wrapper'
      wrapper.style.setProperty('--v-theme-background', '30, 41, 59')
      wrapper.style.setProperty('--v-theme-surface', '255, 255, 255')
      inner.className = 'tree-node-inner'
      wrapper.append(inner)
      $tree[0].append(wrapper)
    })
    cy.get('.drag-placeholder-wrapper .tree-node-inner')
      .should('have.css', 'background-color', 'rgb(255, 255, 255)')
  })

  it('renders search field', () => {
    mountList({}, { 'page:view': true })
    cy.get('.v-text-field').should('exist')
  })

  it('renders checkbox for bulk selection', () => {
    mountList({}, { 'page:view': true })
    cy.get('.v-checkbox-btn').should('exist')
  })

  it('renders reload button', () => {
    mountList({}, { 'page:view': true })
    cy.get('button.btn-reload').should('exist')
  })

  it('shows add button with page:add permission and not embed', () => {
    mountList({ embed: false }, { 'page:view': true, 'page:add': true })
    cy.get('button.btn-add').should('exist')
  })

  it('hides add button when embed is true', () => {
    mountList({ embed: true }, { 'page:view': true, 'page:add': true })
    cy.get('button.btn-add').should('not.exist')
  })

  it('hides add button without page:add permission', () => {
    mountList({}, { 'page:view': true })
    cy.get('button.btn-add').should('not.exist')
  })

  it('shows no entries message when not loading and items are empty', () => {
    mountList({}, { 'page:view': true })
    cy.contains('No entries found').should('exist')
  })

  it('hides sort dropdown in tree view (default)', () => {
    mountList({ filter: { view: 'tree' } }, { 'page:view': true })
    cy.get('.btn-sort button').should('not.exist')
  })

  it('shows sort dropdown in list view', () => {
    mountList({ filter: { view: 'list' } }, { 'page:view': true })
    cy.get('.btn-sort button').should('exist')
  })

  it('shows title-case sort options in list view', () => {
    mountList({ filter: { view: 'list' } }, { 'page:view': true })
    cy.get('.btn-sort button').click()
    cy.get('.v-overlay .v-btn').then(($buttons) => {
      expect([...$buttons].map((button) => button.textContent.trim())).to.deep.equal([
        'Tree', 'Latest', 'Oldest', 'Latest edit', 'Oldest edit', 'Name', 'Editor'
      ])
    })
  })

  it('sorts the list by latest and oldest edit', () => {
    const query = cy.stub().resolves({
      data: { pages: { data: [], paginatorInfo: { currentPage: 1, lastPage: 1 } } }
    })

    mountList({ filter: { view: 'list' } }, { 'page:view': true }, { query })

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

  it('loads the saved list view with its active filters', () => {
    document.querySelector('#app').dataset.reverb = '{}'
    const query = cy.stub().resolves({
      data: { pages: { data: [], paginatorInfo: { currentPage: 1, lastPage: 1 } } }
    })

    mountList({ embed: true, filter: { view: 'list', status: 0 } }, { 'page:view': true }, { query }).then(() => {
      expect(query).to.have.been.calledOnce
      expect(query.firstCall.args[0].fetchPolicy).to.equal('cache-first')
      expect(query.firstCall.args[0].variables.filter).to.deep.equal({ status: 0 })
      expect(query.firstCall.args[0].variables.sort).to.deep.equal([{ column: 'LFT', order: 'ASC' }])
    })
  })

  it('uses the Apollo cache for page tree queries', () => {
    document.querySelector('#app').dataset.reverb = '{}'
    const query = cy.stub().resolves({
      data: { pages: { data: [], paginatorInfo: { currentPage: 1, lastPage: 1 } } }
    })

    mountList({ embed: true, filter: { view: 'tree' } }, { 'page:view': true }, { query }).then(() => {
      expect(query).to.have.been.calledOnce
      expect(query.firstCall.args[0].fetchPolicy).to.equal('cache-first')
    })
  })

  it('reloads from the network when reactivated without remote invalidation', () => {
    const query = cy.stub().resolves({
      data: { pages: { data: [], paginatorInfo: { currentPage: 1, lastPage: 1 } } }
    })

    mountList({ embed: true, filter: { view: 'list' } }, { 'page:view': true }, { query }).then(({ wrapper }) => {
      const vm = wrapper.findComponent(PageListItems).vm

      return vm.$options.activated.call(vm).then(() => {
        expect(query).to.have.been.calledTwice
        expect(query.lastCall.args[0].fetchPolicy).to.equal('network-only')
      })
    })
  })

  it('clears the complete Apollo cache before a manual reload', () => {
    const calls = []
    const query = cy.stub().callsFake(() => {
      calls.push('query')
      return Promise.resolve({
        data: { pages: { data: [], paginatorInfo: { currentPage: 1, lastPage: 1 } } }
      })
    })
    const evict = cy.stub()
    const gc = cy.stub()
    const clearStore = cy.stub().callsFake(() => {
      calls.push('clearStore')
      return Promise.resolve()
    })

    mountList({ filter: { view: 'list' } }, { 'page:view': true }, {
      query,
      provider: {
        defaultClient: {
          cache: { evict, gc },
          clearStore,
        },
      },
    })

    cy.get('button.btn-reload').click()
    cy.then(() => {
      expect(clearStore).to.have.been.calledOnce
      expect(evict).not.to.have.been.called
      expect(gc).not.to.have.been.called
      expect(query).to.have.been.calledTwice
      expect(calls).to.deep.equal(['query', 'clearStore', 'query'])
    })
  })

  it('removes every page list query', () => {
    const evict = cy.stub()
    const gc = cy.stub()

    mountList({}, { 'page:view': true }, {
      provider: {
        defaultClient: {
          cache: { evict, gc },
        },
      },
    }).then(({ wrapper }) => {
      wrapper.findComponent(PageListItems).vm.invalidate()

      expect(evict).to.have.been.calledWith({ id: 'ROOT_QUERY', fieldName: 'pages' })
      expect(gc).to.have.been.calledOnce
    })
  })

  it('keeps the latest reload result when an older request finishes afterwards', () => {
    const response = (id, name) => ({
      data: {
        pages: {
          data: [{
            id,
            parent_id: null,
            created_at: '2026-01-01 00:00:00',
            deleted_at: null,
            editor: 'test@test.com',
            has: 0,
            restricted: false,
            latest: {
              id: `${id}-latest`,
              published: true,
              publish_at: null,
              data: JSON.stringify({ name }),
              editor: 'test@test.com',
              created_at: '2026-01-01 00:00:00'
            }
          }],
          paginatorInfo: { currentPage: 1, lastPage: 1 }
        }
      }
    })
    let finishInitial
    const query = cy.stub()
    query.onFirstCall().returns(new Promise((resolve) => { finishInitial = resolve }))
    query.onSecondCall().resolves(response('new', 'New page'))

    mountList({ filter: { view: 'list' } }, { 'page:view': true }, { query }).then(({ wrapper }) => {
      const vm = wrapper.findComponent(PageListItems).vm

      return vm.reload(false).then(() => {
        finishInitial(response('old', 'Old page'))

        return Cypress.Promise.resolve().then(() => {
          expect(vm.items.map((item) => item.id)).to.deep.equal(['new'])
          expect(vm.loading).to.equal(false)
        })
      })
    })
  })

  it('refetches expanded branches with the active tree filters', () => {
    const response = (data) => ({
      data: {
        pages: {
          data,
          paginatorInfo: { currentPage: 1, lastPage: 1 }
        }
      }
    })
    const page = (id, parentId, name, has = 0) => ({
      id,
      parent_id: parentId,
      created_at: '2026-01-01 00:00:00',
      deleted_at: null,
      editor: 'test@test.com',
      has,
      restricted: false,
      latest: {
        id: `${id}-latest`,
        published: true,
        publish_at: null,
        data: JSON.stringify({ name }),
        editor: 'test@test.com',
        created_at: '2026-01-01 00:00:00'
      }
    })
    const query = cy.stub().callsFake(({ variables }) => {
      const data = variables.filter.parent_id === 'parent'
        ? [page('child', 'parent', 'Fresh child')]
        : [page('parent', null, 'Parent', 1)]

      return Promise.resolve(response(data))
    })

    mountList({ filter: { view: 'tree', status: 0 } }, { 'page:view': true }, { query }).then(({ wrapper }) => {
      const vm = wrapper.findComponent(PageListItems).vm
      const parent = { data: { id: 'parent' }, open: true }
      vm.$refs.tree.statsFlat = [parent]

      return vm.reload(false).then(() => {
        expect(query).to.have.been.calledThrice
        expect(query.secondCall.args[0].variables.filter).to.deep.equal({ status: 0, parent_id: null })
        expect(query.thirdCall.args[0].variables.filter).to.deep.equal({ status: 0, parent_id: 'parent' })
        expect(vm.items[0].children.map((item) => item.id)).to.deep.equal(['child'])
        expect(parent.open).to.equal(true)
      })
    })
  })

  it('opens access editing for selected pages', () => {
    mountList({}, { 'page:access': true, 'page:view': true }).then(({ wrapper }) => {
      const vm = wrapper.findComponent(PageListItems).vm
      vm.$refs.tree.statsFlat = [{ _checked: true, data: { id: 'page-1', has: 2 } }]
      vm.editAccess()

      expect(vm.accessIds).to.deep.equal(['page-1'])
      expect(vm.accessDescendants).to.equal(2)
      expect(vm.accessDialog).to.equal(true)
    })
  })

  it('opens bulk editing and access control for one page node', () => {
    mountList({}, { 'page:access': true, 'page:save': true, 'page:view': true }).then(({ wrapper }) => {
      const vm = wrapper.findComponent(PageListItems).vm
      const node = { _checked: false, data: { id: 'page-1', has: 2 } }
      const selected = { _checked: true, data: { id: 'page-2', has: 0 } }
      vm.$refs.tree.statsFlat = [node, selected]

      vm.editProps(node)
      expect(vm.propsCount).to.equal(1)
      expect(vm.propsDescendants).to.equal(2)
      expect(vm.propsIds).to.deep.equal(['page-1'])
      expect(vm.propsDialog).to.equal(true)

      vm.editAccess(node)
      expect(vm.accessIds).to.deep.equal(['page-1'])
      expect(vm.accessDescendants).to.equal(2)
      expect(vm.accessDialog).to.equal(true)
      expect(selected._checked).to.equal(true)
    })
  })

  it('updates access indicators for one page node and its descendants', () => {
    mountList({}, { 'page:access': true, 'page:view': true }).then(({ wrapper }) => {
      const vm = wrapper.findComponent(PageListItems).vm
      const root = { _checked: false, data: { id: 'page-1', access: null, restricted: false } }
      const child = { _checked: false, data: { id: 'page-2', access: null, restricted: false }, parent: root }
      const other = { _checked: true, data: { id: 'page-3', access: null, restricted: false } }
      vm.$refs.tree.statsFlat = [root, child, other]

      vm.editAccess(root)
      vm.accessApplied([], true)

      expect(root.data.access).to.deep.equal([])
      expect(root.data.restricted).to.equal(true)
      expect(child.data.access).to.deep.equal([])
      expect(child.data.restricted).to.equal(true)
      expect(other.data.access).to.equal(null)
      expect(other.data.restricted).to.equal(false)
      expect(other._checked).to.equal(true)
    })
  })

  it('describes page access in lock titles', () => {
    mountList({}, { 'page:view': true }).then(({ wrapper }) => {
      const vm = wrapper.findComponent(PageListItems).vm

      expect(vm.accessTitle([])).to.equal('Authenticated users')
      expect(vm.accessTitle(['member', 'staff'])).to.equal('Access: member, staff')
      expect(vm.accessTitle(undefined)).to.equal('Restricted')
    })
  })

  it('updates missing tree fields after a page is saved in the detail view', () => {
    mountList({}, { 'page:view': true }).then(({ wrapper }) => {
      const vm = wrapper.findComponent(PageListItems).vm
      const stat = { data: { id: 'page-1' } }
      const data = {
        cache: 15,
        domain: 'example.com',
        lang: 'de',
        name: 'Updated name',
        path: 'updated-path',
        status: 2,
        tag: 'updated-tag',
        theme: 'corporate',
        title: 'Updated title',
        to: '/target',
        type: 'landing',
      }
      vm.$refs.tree.statsFlat = [stat]

      vm.changes.notify('page', { id: 'page-1', ...data, content: [{ type: 'text' }] })

      return vm.$nextTick().then(() => {
        expect(stat.data).to.include(data)
        expect(stat.data).not.to.have.property('content')
        expect(vm.changes.get('page')).to.be.empty
      })
    })
  })

  it('inherits the parent theme and type when inserting a child page', () => {
    const mutate = cy.stub().resolves({ data: { addPage: { id: 'page-new' } } })

    mountList({}, { 'page:add': true, 'page:view': true }, { mutate }).then(({ wrapper }) => {
      const vm = wrapper.findComponent(PageListItems).vm
      const parent = {
        children: [],
        data: { id: 'page-parent', has: 0, theme: 'corporate', type: 'landing' },
        open: true,
      }
      vm.$refs.tree.getSiblings = () => [parent]

      return vm.insert(parent).then(() => {
        expect(mutate).to.have.been.calledOnce
        expect(mutate.firstCall.args[0].variables).to.deep.include({
          parent: 'page-parent',
          ref: null,
        })
        expect(mutate.firstCall.args[0].variables.input).to.include({
          theme: 'corporate',
          type: 'landing',
        })
      })
    })
  })

  it('inherits the containing parent theme and type when inserting beside a page', () => {
    const mutate = cy.stub().resolves({ data: { addPage: { id: 'page-new' } } })

    mountList({}, { 'page:add': true, 'page:view': true }, { mutate }).then(({ wrapper }) => {
      const vm = wrapper.findComponent(PageListItems).vm
      const parent = {
        children: [],
        data: { id: 'page-parent', has: 1, theme: 'corporate', type: 'landing' },
      }
      const sibling = {
        data: { id: 'page-sibling', theme: 'editorial', type: 'article' },
        parent,
      }
      vm.$refs.tree.getSiblings = () => [sibling]

      return vm.insert(sibling, 0).then(() => {
        expect(mutate).to.have.been.calledOnce
        expect(mutate.firstCall.args[0].variables).to.deep.include({
          parent: 'page-parent',
          ref: 'page-sibling',
        })
        expect(mutate.firstCall.args[0].variables.input).to.include({
          theme: 'corporate',
          type: 'landing',
        })
      })
    })
  })

  it('copies the latest page data when the tree node only contains its ID', () => {
    const data = {
      cache: 15,
      domain: 'example.com',
      lang: 'de',
      name: 'Source page',
      path: 'source-page',
      status: 1,
      tag: 'source',
      theme: 'corporate',
      title: 'Source title',
      to: '/target',
      type: 'landing',
    }
    const aux = {
      content: [{ id: 'content-1', type: 'text', group: 'main', data: { text: 'Copied text' } }],
      config: { styles: { type: 'styles', data: { text: 'body {}' }, files: [] } },
      meta: { canonical: { type: 'canonical', data: { url: '/source-page' }, files: [] } },
    }
    const query = cy.stub()
    query.onFirstCall().resolves({
      data: { pages: { data: [], paginatorInfo: { currentPage: 1, lastPage: 1 } } }
    })
    query.onSecondCall().resolves({
      data: { page: { id: 'page-source', latest: { id: 'version-source', data: JSON.stringify(data), aux: JSON.stringify(aux) } } }
    })
    const mutate = cy.stub().resolves({
      data: {
        addPage: {
          id: 'page-copy',
          parent_id: null,
          created_at: '2026-01-01 00:00:00',
          deleted_at: null,
          editor: 'test@test.com',
          has: 0,
          restricted: false,
          latest: {
            id: 'version-copy',
            published: false,
            publish_at: null,
            data: JSON.stringify({ ...data, status: 0, path: 'source-page_1234' }),
            editor: 'test@test.com',
            created_at: '2026-01-01 00:00:00',
          },
        },
      },
    })

    mountList({}, { 'page:add': true, 'page:view': true }, { query, mutate }).then(({ wrapper }) => {
      const vm = wrapper.findComponent(PageListItems).vm
      const target = { data: { id: 'page-target' } }
      vm.$refs.tree.getSiblings = () => [target]
      vm.clip = { type: 'copy', node: { id: 'page-source' } }

      return vm.paste(target, 1).then(() => {
        expect(query.secondCall.args[0].fetchPolicy).to.equal('no-cache')
        expect(query.secondCall.args[0].variables).to.deep.equal({ id: 'page-source' })
        expect(mutate).to.have.been.calledOnce

        const input = mutate.firstCall.args[0].variables.input
        expect(input).to.include({
          cache: 15,
          domain: 'example.com',
          lang: 'de',
          name: 'Source page',
          related_id: 'page-source',
          status: 0,
          tag: 'source',
          theme: 'corporate',
          title: 'Source title',
          to: '/target',
          type: 'landing',
        })
        expect(input.path).to.match(/^source-page_\d+$/)
        expect(JSON.parse(input.content)).to.deep.equal(aux.content)
        expect(JSON.parse(input.config)).to.deep.equal(aux.config)
        expect(JSON.parse(input.meta)).to.deep.equal(aux.meta)
      })
    })
  })

  it('clears the cut state after the page is pasted successfully', () => {
    const mutate = cy.stub().resolves({ data: { movePage: { id: 'page-cut' } } })

    mountList({}, { 'page:move': true, 'page:view': true }, { mutate }).then(({ wrapper }) => {
      const vm = wrapper.findComponent(PageListItems).vm
      const source = { data: { id: 'page-cut', has: 0 }, parent: null }
      const target = { data: { id: 'page-target' }, parent: null }
      const move = cy.stub()
      vm.$refs.tree.statsFlat = [source, target]
      const [sourceStat, targetStat] = vm.$refs.tree.statsFlat
      vm.$refs.tree.getSiblings = () => vm.$refs.tree.statsFlat
      vm.$refs.tree.move = move

      vm.cut(sourceStat, sourceStat.data)

      return vm.move(targetStat, 1).then((success) => {
        expect(success).to.equal(true)
        expect(sourceStat).not.to.have.property('cut')
        expect(vm.clip).to.equal(null)
        expect(move).to.have.been.calledOnceWith(sourceStat, null, 1)
        expect(mutate.firstCall.args[0].variables).to.deep.equal({
          id: 'page-cut',
          parent: null,
          ref: null,
        })
      })
    })
  })

  it('keeps the cut state when pasting the page fails', () => {
    const mutate = cy.stub().rejects(new Error('Move failed'))

    mountList({}, { 'page:move': true, 'page:view': true }, { mutate }).then(({ wrapper }) => {
      const vm = wrapper.findComponent(PageListItems).vm
      const source = { data: { id: 'page-cut', has: 0 }, parent: null }
      const target = { data: { id: 'page-target' }, parent: null }
      const move = cy.stub()
      vm.$refs.tree.statsFlat = [source, target]
      const [sourceStat, targetStat] = vm.$refs.tree.statsFlat
      vm.$refs.tree.getSiblings = () => vm.$refs.tree.statsFlat
      vm.$refs.tree.move = move

      vm.cut(sourceStat, sourceStat.data)

      return vm.move(targetStat, 1).then((success) => {
        expect(success).to.equal(false)
        expect(sourceStat.cut).to.equal(true)
        expect(vm.clip?.stat).to.equal(sourceStat)
        expect(move).not.to.have.been.called
      })
    })
  })

  it('clears the selected page subtree with cache:clear permission', () => {
    const mutate = cy.stub().resolves({ data: { clearCache: 3 } })

    mountList({}, { 'cache:clear': true, 'page:view': true }, { mutate }).then(({ wrapper }) => {
      const vm = wrapper.findComponent(PageListItems).vm

      return vm.clear({ data: { id: 'page-1' } }).then(() => {
        expect(mutate).to.have.been.calledOnce
        expect(mutate.firstCall.args[0].variables).to.deep.equal({ ids: ['page-1'] })
      })
    })
  })

  it('does not clear page caches without cache:clear permission', () => {
    const mutate = cy.stub().resolves({ data: { clearCache: 1 } })

    mountList({}, { 'page:view': true }, { mutate }).then(({ wrapper }) => {
      const vm = wrapper.findComponent(PageListItems).vm

      vm.clear({ data: { id: 'page-1' } })
      expect(mutate).not.to.have.been.called
    })
  })

  it('saves properties for one page node without changing the selection', () => {
    const mutate = cy.stub().resolves({
      data: {
        bulkPage: {
          ids: ['page-1'],
          latest: '{}',
          data: '{"status":0}',
          failed: 0,
        },
      },
    })

    mountList({}, { 'page:save': true, 'page:view': true }, { mutate }).then(({ wrapper }) => {
      const vm = wrapper.findComponent(PageListItems).vm
      const node = { _checked: false, data: { id: 'page-1', status: 1, has: 2 } }
      const selected = { _checked: true, data: { id: 'page-2', status: 1, has: 0 } }
      vm.$refs.tree.statsFlat = [node, selected]
      vm.editProps(node)

      return vm.saveProps({ input: { status: 0 }, descendants: true }).then(() => {
        expect(mutate).to.have.been.calledOnce
        expect(mutate.firstCall.args[0].variables).to.deep.equal({
          id: ['page-1'],
          input: { status: 0 },
          descendants: true,
        })
        expect(node.data.status).to.equal(0)
        expect(selected._checked).to.equal(true)
      })
    })
  })

  it('saves selected page status through one bulk mutation', () => {
    const mutate = cy.stub().resolves({ data: { bulkPage: { ids: ['page-1', 'page-2'] } } })

    mountList({}, { 'page:save': true, 'page:view': true }, { mutate }).then(({ wrapper }) => {
      const vm = wrapper.findComponent(PageListItems).vm
      const stats = [
        { _checked: true, data: { id: 'page-1', status: 1 } },
        { _checked: true, data: { id: 'page-2', status: 1 } },
      ]
      vm.$refs.tree.statsFlat = stats

      return vm.status(null, 0).then(() => {
        expect(mutate).to.have.been.calledOnce
        expect(mutate.firstCall.args[0].variables).to.deep.equal({
          id: ['page-1', 'page-2'],
          input: { status: 0 },
        })
        expect(stats.map((stat) => stat.data.status)).to.deep.equal([0, 0])
      })
    })
  })
})
