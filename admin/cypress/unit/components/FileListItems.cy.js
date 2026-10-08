import { reactive } from 'vue'
import FileListItems from '../../../js/components/FileListItems.vue'
import { useLanguageStore, useMessageStore, useUserStore } from '../../../js/stores'

const stubs = {
}

function mountList(props = {}, perms = {}, apollo = {}) {
  return cy.mount(FileListItems, {
    props: {
      ...props,
    },
    global: {
      stubs,
      provide: {
        debounce: (fn) => fn,
        url: (path) => path,
        srcset: () => '',
      },
      mocks: {
        $apollo: {
          query: () => Promise.resolve({
            data: { files: { data: [], paginatorInfo: { lastPage: 1 } } },
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

describe('FileListItems', () => {
  beforeEach(() => {
    document.querySelector('[data-cy-root]').id = 'app'
  })

  afterEach(() => {
    document.querySelector('#app')?.removeAttribute('data-reverb')
  })

  it('renders the component', () => {
    mountList({}, { 'file:view': true })
    cy.get('.header').should('exist')
  })

  it('renders search field', () => {
    mountList({}, { 'file:view': true })
    cy.get('.v-text-field').should('exist')
  })

  it('renders checkbox for bulk selection', () => {
    mountList({}, { 'file:view': true })
    cy.get('.v-checkbox-btn').should('exist')
  })

  it('renders sort menu button', () => {
    mountList({}, { 'file:view': true })
    cy.get('.btn-sort button').should('exist')
  })

  it('shows title-case sort options', () => {
    mountList({}, { 'file:view': true })
    cy.get('.btn-sort button').click()
    cy.get('.v-overlay .v-list .v-btn').then(($buttons) => {
      expect([...$buttons].map((button) => button.textContent.trim())).to.deep.equal([
        'Latest', 'Oldest', 'Latest edit', 'Oldest edit', 'Name', 'MIME', 'Language', 'Editor', 'Usage'
      ])
    })
  })

  it('sorts by latest and oldest edit', () => {
    const query = cy.stub().resolves({
      data: { files: { data: [], paginatorInfo: { lastPage: 1 } } }
    })

    mountList({}, { 'file:view': true }, { query })

    cy.get('.btn-sort button').click()
    cy.contains('.v-overlay .v-list .v-btn', 'Latest edit').scrollIntoView().click()
    cy.get('.btn-sort button').should('contain', 'Latest edit')
    cy.then(() => {
      expect(query.lastCall.args[0].variables.sort).to.deep.equal([
        { column: 'LATEST_ID', order: 'DESC' }
      ])
    })

    cy.get('.btn-sort button').click()
    cy.contains('.v-overlay .v-list .v-btn', 'Oldest edit').scrollIntoView().click()
    cy.get('.btn-sort button').should('contain', 'Oldest edit')
    cy.then(() => {
      expect(query.lastCall.args[0].variables.sort).to.deep.equal([
        { column: 'LATEST_ID', order: 'ASC' }
      ])
    })
  })

  it('renders grid/list toggle button', () => {
    mountList({}, { 'file:view': true })
    cy.get('button.btn-grid, button.btn-list').should('exist')
  })

  it('shows add button with file:add permission and not embed', () => {
    mountList({ embed: false }, { 'file:view': true, 'file:add': true })
    cy.get('button.btn-add').should('exist')
  })

  it('offers separate public and protected upload buttons', () => {
    mountList(
      { embed: false },
      { 'file:view': true, 'file:add': true, 'file:relocate': true },
    )
    cy.get('button.btn-add').should('exist')
    cy.get('button.btn-add-private')
      .should('exist')
      .and('have.attr', 'title', 'Add files: Protect access')
    cy.get('.v-switch').should('not.exist')
  })

  it('hides protected uploads without file:relocate permission', () => {
    mountList({ embed: false }, { 'file:view': true, 'file:add': true })

    cy.get('button.btn-add').should('exist')
    cy.get('button.btn-add-private').should('not.exist')
  })

  it('uploads protected files directly to the private disk', () => {
    const mutate = cy.stub().resolves({
      data: {
        addFile: {
          disk: 'private',
          id: 'file-1',
          mime: 'application/pdf',
          name: 'private.pdf',
          path: 'cms/file-1/private.pdf',
          previews: '{}',
        },
      },
    })

    mountList(
      { embed: false },
      { 'file:view': true, 'file:add': true, 'file:relocate': true },
      { mutate },
    ).then(({ wrapper }) => {
      const vm = wrapper.findComponent(FileListItems).vm
      const file = new File(['private'], 'private.pdf', { type: 'application/pdf' })

      return vm.add({ target: { files: [file] } }, 'private').then(() => {
        expect(mutate).to.have.been.calledOnce
        expect(mutate.firstCall.args[0].variables).to.deep.equal({
          disk: 'private',
          file,
        })
        expect(mutate.firstCall.args[0].context).to.deep.equal({ hasUpload: true })
      })
    })
  })

  it('keeps the disk when loading versioned files', () => {
    const query = cy.stub().resolves({
      data: {
        files: {
          data: [{
            disk: 'private',
            id: 'file-1',
            name: 'published.pdf',
            path: 'cms/file-1/published.pdf',
            previews: '{}',
            latest: {
              data: '{"name":"private.pdf","path":"cms/file-1/private.pdf","previews":{}}',
              aux: '{"description":{"en":"Private draft"}}',
            },
          }],
          paginatorInfo: { lastPage: 1 },
        },
      },
    })

    mountList({}, { 'file:view': true }, { query }).then(({ wrapper }) => {
      return wrapper.findComponent(FileListItems).vm.search().then((items) => {
        expect(items[0].disk).to.equal('private')
        expect(items[0].path).to.equal('cms/file-1/private.pdf')
        expect(items[0].description).to.deep.equal({ en: 'Private draft' })
      })
    })
  })

  it('shows the page access lock only for private files', () => {
    mountList({}, { 'file:view': true }).then(({ wrapper }) => {
      const vm = wrapper.findComponent(FileListItems).vm
      const file = {
        id: 'file-1',
        mime: 'application/pdf',
        name: 'file.pdf',
        path: 'cms/file-1/file.pdf',
        previews: {},
        updated_at: '2026-01-01T00:00:00Z',
      }

      vm.items = [
        { ...file, disk: 'public' },
        { ...file, id: 'file-2', disk: 'private' },
      ]
      vm.loading = false

      cy.get('.item-access')
        .should('have.length', 1)
        .and('have.attr', 'title', 'Protect access')
    })
  })

  it('hides add button when embed is true', () => {
    mountList({ embed: true }, { 'file:view': true, 'file:add': true })
    cy.get('button.btn-add').should('not.exist')
  })

  it('hides add button without file:add permission', () => {
    mountList({}, { 'file:view': true })
    cy.get('button.btn-add').should('not.exist')
  })

  it('renders reload button', () => {
    mountList({}, { 'file:view': true })
    cy.get('button.btn-reload').should('exist')
  })

  it('uses the Apollo cache for file queries', () => {
    document.querySelector('#app').dataset.reverb = '{}'
    const query = cy.stub().resolves({
      data: { files: { data: [], paginatorInfo: { lastPage: 1 } } },
    })

    mountList({ embed: true }, { 'file:view': true }, { query }).then(({ wrapper }) => {
      return wrapper.findComponent(FileListItems).vm.search().then(() => {
        expect(query.lastCall.args[0].fetchPolicy).to.equal('cache-first')
      })
    })
  })

  it('requeries an evicted file list when reactivated', () => {
    document.querySelector('#app').dataset.reverb = '{}'
    const query = cy.stub().resolves({
      data: { files: { data: [], paginatorInfo: { lastPage: 1 } } },
    })
    const diff = cy.stub().returns({ complete: false })

    mountList({ embed: true }, { 'file:view': true }, {
      query,
      provider: {
        defaultClient: {
          cache: { diff, evict() {}, gc() {} },
          clearStore: () => Promise.resolve(),
        },
      },
    }).then(({ wrapper }) => {
      const vm = wrapper.findComponent(FileListItems).vm
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
        data: { files: { data: [], paginatorInfo: { lastPage: 1 } } },
      })
    })
    const clearStore = cy.stub().callsFake(() => {
      calls.push('clearStore')
      return Promise.resolve()
    })

    mountList({}, { 'file:view': true }, {
      query,
      provider: {
        defaultClient: {
          cache: { diff: () => ({ complete: true }), evict() {}, gc() {} },
          clearStore,
        },
      },
    }).then(({ wrapper }) => {
      return wrapper.findComponent(FileListItems).vm.reload().then(() => {
        expect(calls).to.deep.equal(['clearStore', 'query'])
      })
    })
  })

  it('shows loading state initially', () => {
    mountList({}, { 'file:view': true })
    cy.contains('Loading').should('exist')
  })

  it('starts in list view by default', () => {
    mountList({}, { 'file:view': true })
    cy.get('button.btn-grid').should('exist')
  })

  it('starts in grid view when grid prop is true', () => {
    mountList({ grid: true }, { 'file:view': true })
    cy.get('button.btn-list').should('exist')
  })

  it('edits one item without changing the bulk selection', () => {
    const mutate = cy.stub().resolves({ data: { bulkFile: { ids: ['file-1'] } } })

    mountList({}, { 'file:save': true, 'file:view': true }, { mutate }).then(({ wrapper }) => {
      const vm = wrapper.findComponent(FileListItems).vm
      const item = { id: 'file-1' }
      vm.items = [item, { id: 'file-2' }]
      vm.checked = new Set(['file-2'])

      vm.edit(item)
      expect(vm.editIds).to.deep.equal(['file-1'])
      expect(vm.editDialog).to.equal(true)

      return vm.save('de').then(() => {
        expect(mutate).to.have.been.calledOnce
        expect(mutate.firstCall.args[0].variables).to.deep.equal({
          id: ['file-1'],
          input: { lang: 'de' },
        })
        expect([...vm.checked]).to.deep.equal(['file-2'])
      })
    })
  })

  it('toggles the focused file with Space and drops it with Del', () => {
    const mutate = cy.stub().resolves({ data: { dropFile: [] } })
    const file = (id, attr = {}) => ({ id, name: id, mime: 'text/plain', path: id + '.txt', previews: {}, ...attr })
    const press = (selector, key) => cy.get(selector).then(($el) => {
      const ev = new KeyboardEvent('keydown', { key, bubbles: true, cancelable: true })
      $el[0].dispatchEvent(ev)
      return ev.defaultPrevented
    })

    mountList({}, { 'file:view': true, 'file:drop': true }, { mutate }).then(({ wrapper }) => {
      wrapper.findComponent(FileListItems).vm.items = [file('file-1'), file('file-2', { deleted_at: '2026-01-01 00:00:00' })]
    })

    press('.items [data-id="file-1"] .item-content', ' ').should('equal', true)
    cy.get('.items [data-id="file-1"] .item-check input').should('be.checked')

    press('.items [data-id="file-1"] .item-check input', ' ').should('equal', false) // the checkbox toggles itself
    cy.get('.items [data-id="file-1"] .item-check input').should('be.checked')

    press('.items [data-id="file-2"] .item-content', 'Delete') // already in the trash
    press('.items [data-id="file-1"] .item-content', 'Delete')
    cy.wrap(mutate).should('have.been.calledOnce').then(() => {
      expect(mutate.firstCall.args[0].variables).to.deep.equal({ id: ['file-1'] })
    })
  })

  it('offers undo after trashing files', () => {
    const mutate = cy.stub().resolves({ data: {} })

    mountList({}, { 'file:view': true, 'file:drop': true, 'file:keep': true }, { mutate }).then(({ wrapper }) => {
      const messages = useMessageStore()
      messages.queue = []

      wrapper.findComponent(FileListItems).vm.drop({ id: 'file-1' })

      cy.wrap(messages).its('queue.length').should('equal', 1).then(() => {
        const item = messages.queue[0]
        expect(item.text).to.equal('Moved to trash')
        expect(messages.action(item['data-action']).label).to.equal('Undo')

        messages.run(item['data-action'])
        expect(messages.action(item['data-action'])).to.equal(null)
      })

      cy.wrap(mutate).should('have.been.calledTwice').then(() => {
        expect(mutate.secondCall.args[0].variables).to.deep.equal({ id: ['file-1'] })
      })
    })
  })

  it('shows reset button for filtered empty lists', () => {
    const defaults = { trashed: 'WITHOUT', publish: null, editor: null, lang: null }
    const filter = reactive({ ...defaults, publish: 'DRAFT' })

    mountList({ defaults, filter }, { 'file:view': true }).then(({ wrapper }) => wrapper.findComponent(FileListItems).vm.search())
    cy.get('.notfound').should('contain', 'No entries found')
    cy.get('.notfound .btn-reset-filter').click()
    cy.get('.notfound').should('contain', 'No entries yet').then(() => {
      expect(filter.publish).to.equal(null)
    })
  })

  it('shows no entries yet for unfiltered empty lists', () => {
    const defaults = { trashed: 'WITHOUT', publish: null, editor: null, lang: null }

    mountList({ defaults, filter: { ...defaults } }, { 'file:view': true }).then(({ wrapper }) => wrapper.findComponent(FileListItems).vm.search())
    cy.get('.notfound').should('contain', 'No entries yet')
    cy.get('.notfound .btn-reset-filter').should('not.exist')
  })

  describe('translate descriptions', () => {
    const perms = { 'file:view': true, 'file:save': true, 'text:translate': true }

    function file(id, extra = {}) {
      return { id, name: id, mime: 'image/png', path: id + '.png', previews: {}, description: { en: 'A cat' }, published: true, ...extra }
    }

    function translated(id) {
      return {
        id, disk: 'public', lang: null, mime: 'image/png', name: id, path: id + '.png', previews: '{}',
        description: '{}', transcription: '{}', editor: 'test@test.com', created_at: '2026-01-01 00:00:00',
        updated_at: '2026-01-01 00:00:00', deleted_at: null, byversions_count: 0,
        latest: { id: 'v-' + id, published: false, publish_at: null, editor: 'test@test.com', created_at: '2026-01-01 00:00:00',
          data: '{}', aux: JSON.stringify({ description: { en: 'A cat', de: 'Eine Katze' } }) },
      }
    }

    it('translates the descriptions of the selected files into all languages', () => {
      const mutate = cy.stub().resolves({ data: { translateFiles: [translated('file-1')] } })

      mountList({}, perms, { mutate }).then(({ wrapper }) => {
        useLanguageStore().available = ['en', 'de']
        const vm = wrapper.findComponent(FileListItems).vm
        const add = cy.spy(vm.messages, 'add')
        vm.items = [file('file-1'), file('file-2'), file('file-3', { deleted_at: '2026-01-01 00:00:00' })]
        vm.checked = new Set(['file-1', 'file-2', 'file-3'])

        return vm.translate().then(() => {
          expect(mutate).to.have.been.calledOnce
          expect(mutate.firstCall.args[0].variables).to.deep.equal({ id: ['file-1', 'file-2'], lang: ['en', 'de'] })
          expect(vm.items[0].description).to.deep.equal({ en: 'A cat', de: 'Eine Katze' })
          expect(vm.items[0].published).to.equal(false)
          expect(add).to.have.been.calledWithMatch(/1 file translated/, 'success')
        })
      })
    })

    it('sends at most 10 files per request and tracks the progress without messages per chunk', () => {
      const progress = []
      let vm = null
      const mutate = cy.stub().callsFake(() => {
        progress.push({ ...vm.translating })
        return Promise.resolve({ data: { translateFiles: [] } })
      })

      mountList({}, perms, { mutate }).then(({ wrapper }) => {
        useLanguageStore().available = ['en', 'de']
        vm = wrapper.findComponent(FileListItems).vm
        const add = cy.spy(vm.messages, 'add')
        vm.items = Array.from({ length: 25 }, (_, i) => file('file-' + i))
        vm.checked = new Set(vm.items.map((item) => item.id))

        return vm.translate().then(() => {
          expect(mutate).to.have.been.calledThrice
          expect(mutate.firstCall.args[0].variables.id).to.have.length(10)
          expect(mutate.secondCall.args[0].variables.id).to.have.length(10)
          expect(mutate.thirdCall.args[0].variables.id).to.have.length(5)
          expect(progress).to.deep.equal([
            { done: 0, total: 25 },
            { done: 10, total: 25 },
            { done: 20, total: 25 }
          ])
          expect(add).to.have.been.calledOnce
          expect(add).to.have.been.calledWithMatch(/No missing descriptions/, 'info')
          expect(vm.translating).to.equal(null)
        })
      })
    })

    it('sends fewer files per request with many languages', () => {
      const mutate = cy.stub().resolves({ data: { translateFiles: [] } })

      mountList({}, perms, { mutate }).then(({ wrapper }) => {
        useLanguageStore().available = Array.from({ length: 30 }, (_, i) => 'l' + i)
        const vm = wrapper.findComponent(FileListItems).vm
        vm.items = Array.from({ length: 7 }, (_, i) => file('file-' + i))
        vm.checked = new Set(vm.items.map((item) => item.id))

        return vm.translate().then(() => {
          expect(mutate).to.have.been.calledThrice
          expect(mutate.firstCall.args[0].variables.id).to.have.length(3)
          expect(mutate.secondCall.args[0].variables.id).to.have.length(3)
          expect(mutate.thirdCall.args[0].variables.id).to.have.length(1)
        })
      })
    })

    it('keeps the translated chunks when a later chunk fails', () => {
      const mutate = cy.stub()
      mutate.onFirstCall().resolves({ data: { translateFiles: [translated('file-0')] } })
      mutate.onSecondCall().rejects(new Error('Timeout'))

      mountList({}, perms, { mutate }).then(({ wrapper }) => {
        useLanguageStore().available = ['en', 'de']
        const vm = wrapper.findComponent(FileListItems).vm
        const add = cy.spy(vm.messages, 'add')
        const error = cy.stub(vm.messages, 'error')
        vm.items = Array.from({ length: 25 }, (_, i) => file('file-' + i))
        vm.checked = new Set(vm.items.map((item) => item.id))

        return vm.translate().then(() => {
          expect(mutate).to.have.been.calledTwice
          expect(vm.items[0].description).to.deep.equal({ en: 'A cat', de: 'Eine Katze' })
          expect(add).to.have.been.calledOnce
          expect(add).to.have.been.calledWithMatch(/1 file translated/, 'success')
          expect(error).to.have.been.calledWithMatch(/after 10 of 25 files/)
          expect(error.firstCall.args[2]).to.have.length(15)
          expect(vm.translating).to.equal(null)
        })
      })
    })

    it('does not translate descriptions with one language or without text:translate', () => {
      const mutate = cy.stub()

      mountList({}, { 'file:view': true, 'file:save': true }, { mutate }).then(({ wrapper }) => {
        useLanguageStore().available = ['en', 'de']
        const vm = wrapper.findComponent(FileListItems).vm
        vm.items = [file('file-1')]

        expect(vm.canTranslate()).to.equal(false)
        useUserStore().me.permission['text:translate'] = true
        expect(vm.canTranslate()).to.equal(true)
        useLanguageStore().available = ['en']
        expect(vm.canTranslate()).to.equal(false)

        return vm.translate(vm.items[0]).then(() => {
          expect(mutate).not.to.have.been.called
        })
      })
    })
  })
})
