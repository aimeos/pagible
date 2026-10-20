import { h, reactive } from 'vue'
import VirtualList from 'vue-virtual-sortable'
import PageDetailContentList from '../../../js/components/PageDetailContentList.vue'
import { useUserStore, useSchemaStore } from '../../../js/stores'

const stubs = {
  Fields: { render: () => h('div', { class: 'fields-stub', style: { height: '300px' } }) },
  SchemaDialog: { template: '<div class="schema-dialog-stub" />' },
}

const schemas = {
  content: {
    heading: { fields: { title: { type: 'string', label: 'Title' } } },
    text: { fields: { text: { type: 'text', label: 'Text' } } },
  },
}

const content = [
  { id: 'c1', type: 'heading', group: 'main', data: { title: 'Hello' } },
  { id: 'c2', type: 'text', group: 'main', data: { text: 'World' } },
]

function contentItems(length) {
  return Array.from({ length }, (_, idx) => ({
    id: `c${idx}`,
    type: 'heading',
    group: 'main',
    data: { title: `Item ${idx}` },
  }))
}

function setupSchemaPlugin() {
  return {
    install() {
      const store = useSchemaStore()
      Object.assign(store, schemas)
    },
  }
}

function globalOptions(apollo = {}) {
  return {
    stubs,
    plugins: [setupSchemaPlugin()],
    provide: {
      transcribe: () => Promise.resolve({ asText: () => '' }),
    },
    mocks: {
      $apollo: {
        mutate: () => Promise.resolve({ data: {} }),
        provider: { defaultClient: { cache: { evict() {}, gc() {} } } },
        ...apollo,
      },
    },
  }
}

// owns the content like the page detail view and applies the changes sent by the list
function host(props = {}, scroll = false) {
  const { content: list, assets, elements, ...rest } = props

  return {
    data: () => ({
      assets: assets ?? {},
      content: list ?? content.map((c) => ({ ...c, data: { ...c.data } })),
      elements: elements ?? {},
    }),

    render() {
      const node = h(PageDetailContentList, {
        item: { id: '1', lang: 'en' },
        ...rest,
        assets: this.assets,
        content: this.content,
        elements: this.elements,
        'onUpdate:content': (value) => {
          this.content = value
          rest['onUpdate:content']?.(value)
        },
        onPatchEntry: (entry, values) => Object.assign(entry, values),
        onAddAssets: (map) => (this.assets = { ...this.assets, ...map }),
        onAddElements: (map) => (this.elements = { ...this.elements, ...map }),
      })

      return scroll ? h('div', { class: 'scroll', style: { height: '320px', overflowY: 'auto' } }, [node]) : node
    },
  }
}

function mountList(props = {}, perms = {}, apollo = {}) {
  return cy.mount(host(props), { global: globalOptions(apollo) }).then(({ wrapper }) => {
    const user = useUserStore()
    user.me = { permission: perms }

    const vm = wrapper.findComponent(PageDetailContentList).vm
    return { wrapper, owner: vm.$parent, vm }
  })
}

function mountScrollable(content) {
  return cy.mount(host({ content }, true), { global: globalOptions() })
}

function select(vm, ...indexes) {
  indexes.forEach((idx) => vm.select(vm.content[idx]))
}

describe('PageDetailContentList', () => {
  beforeEach(() => {
    cy.on('uncaught:exception', () => false)
  })

  it('renders the component', () => {
    mountList()
    cy.get('.v-expansion-panels').should('exist')
  })

  it('opens the first invalid element', () => {
    mountList({}, { 'page:view': true, 'page:save': true }).then(({ wrapper }) => {
      const vm = wrapper.findComponent(PageDetailContentList).vm
      vm.$parent.content[1]._error = true

      return vm.showError().then(() => {
        expect(vm.panel).to.include('c2')
      })
    })
  })

  it('sends field changes to the owner without changing the content itself', () => {
    const onChange = cy.spy()
    const onUpdate = cy.spy()

    mountList({ onChange, 'onUpdate:content': onUpdate }).then(({ owner, vm }) => {
      const entry = owner.content[0]

      vm.update(entry, { data: { title: 'Changed' } })

      expect(owner.content[0]).to.equal(entry)
      expect(entry).to.deep.include({ data: { title: 'Changed' }, _changed: true, group: 'main' })
      expect(onChange).to.have.been.calledOnce
      expect(onUpdate).not.to.have.been.called
    })
  })

  it('renders expansion panels for each content element', () => {
    mountList()
    cy.get('.v-expansion-panel').should('have.length', 2)
  })

  it('virtualizes large content lists', () => {
    const items = contentItems(100)

    mountList({ content: items })
    cy.get('.v-expansion-panel').its('length').should('be.lessThan', items.length)
    cy.contains('.element-title', 'Item 0').should('exist')
    cy.contains('.element-title', 'Item 99').should('not.exist')
  })

  it('keeps the scroll height stable with an expanded virtual item', () => {
    const items = contentItems(100)

    mountScrollable(items)
    cy.get('.v-expansion-panel-title').first().click()
    cy.get('.v-expansion-panel--active').should(($panel) => {
      expect($panel.height()).to.be.greaterThan(350)
    })
    cy.get('.scroll').then(($scroll) => {
      const height = $scroll[0].scrollHeight

      cy.wrap($scroll).scrollTo('bottom')
      cy.contains('.element-title', 'Item 99').should('be.visible')
      cy.wrap($scroll).should(($element) => {
        expect($element[0].scrollHeight).to.be.closeTo(height, 1)
      })
    })
  })

  it('shows filtered matches outside the virtual window and restores the list when cleared', () => {
    const items = contentItems(100)
    let field

    mountScrollable(items).then(({ wrapper }) => {
      field = wrapper.findComponent(PageDetailContentList)
      field.vm.search('Item 99')
    })

    cy.contains('.element-title', 'Item 99').should('be.visible')
    cy.contains('.element-title', 'Item 0').should('not.be.visible')
    cy.then(() => field.vm.search(''))
    cy.contains('.element-title', 'Item 0').should('be.visible')
    cy.contains('.element-title', 'Item 99').should('not.exist')
  })

  it('uses the surrounding scroller and reveals newly added virtual items', () => {
    const items = contentItems(30)

    mountScrollable(items).then(({ wrapper }) => {
      const field = wrapper.findComponent(PageDetailContentList)
      const list = wrapper.findComponent(VirtualList)

      expect(list.props('scroller')).to.equal(wrapper.find('.scroll').element)
      expect(list.element.style.overflow).to.equal('')

      cy.wrap(wrapper.find('.scroll').element)
        .scrollTo('bottom')
        .then(() => {
          field.vm.add({ type: 'heading' }, null)
        })
        .then(() => {
          field.vm.content.at(-1).data.title = 'New item'
        })
    })

    cy.contains('.element-title', 'New item').should('be.visible')
  })

  it('locks the horizontal axis when sorting content vertically', () => {
    const onUpdate = cy.spy().as('update')

    mountList({ 'onUpdate:content': onUpdate }, { 'page:save': true }).then(({ wrapper }) => {
      const list = wrapper.findComponent(VirtualList)

      expect(list.props('lockAxis')).to.equal('x')
      list.vm.$emit('update:modelValue', [content[1], content[0]])
    })

    cy.get('@update').should((spy) => {
      expect(spy.lastCall.args[0].map((item) => item.id)).to.deep.equal(['c2', 'c1'])
    })
  })

  it('displays element type in panel title', () => {
    mountList()
    cy.get('.element-type').first().should('contain', 'heading')
  })

  it('displays element title in panel', () => {
    mountList()
    cy.get('.element-title').first().should('contain', 'Hello')
  })

  it('renders search field', () => {
    mountList()
    cy.get('.v-text-field').should('exist')
  })

  it('shows bulk actions menu with page:save permission', () => {
    mountList({}, { 'page:save': true })
    cy.get('.bulk').should('exist')
    cy.contains('Actions').should('exist')
  })

  it('makes copied reactive content available in the action menus', () => {
    const items = reactive(content.map((item) => ({ ...item, data: { ...item.data } })))

    mountList({ content: items }, { 'page:save': true }).then(({ wrapper }) => {
      const vm = wrapper.findComponent(PageDetailContentList).vm

      vm.copy(0)
      expect(vm.clipboard.get('page-content')).to.deep.equal([
        { ...content[0], id: null },
      ])
    })

    cy.get('.bulk > .v-btn').should('not.be.disabled')
    cy.get('.v-expansion-panel-title .btn-actions button').eq(1).click()
    cy.contains('.v-overlay .v-btn', 'Paste before').should('exist')
    cy.contains('.v-overlay .v-btn', 'Paste after').should('exist')
  })

  it('copies all selected elements from the context menu of a selected element', () => {
    mountList({ content: contentItems(3) }, { 'page:save': true }).then(({ vm }) => {
      select(vm, 0, 2)
      vm.copy(2)
      expect(vm.clipboard.get('page-content').map((el) => el.data.title)).to.deep.equal([
        'Item 0',
        'Item 2',
      ])

      vm.copy(1)
      expect(vm.clipboard.get('page-content').map((el) => el.data.title)).to.deep.equal(['Item 1'])
    })

    cy.get('.v-expansion-panel-title .btn-actions button').eq(0).click()
    cy.contains('.v-overlay .v-btn', 'Copy (2)').should('exist')
    cy.contains('.v-overlay .v-btn', 'Cut (2)').should('exist')
  })

  it('merges the selected elements from the bulk menu', () => {
    mountList({ content: contentItems(3) }, { 'page:save': true }).then(({ vm }) => select(vm, 0, 2))

    cy.get('.bulk .v-btn').first().click()
    cy.contains('.v-overlay .v-btn', 'Merge').click()

    cy.get('.v-expansion-panel').should('have.length', 2)
  })

  it('shows merge in the context menu of a selected element only', () => {
    mountList({ content: contentItems(3) }, { 'page:save': true }).then(({ vm }) => select(vm, 0, 2))

    cy.get('.v-expansion-panel-title .btn-actions button').eq(0).click()
    cy.contains('.v-overlay .v-btn', 'Merge').should('exist')
    cy.get('body').type('{esc}')

    cy.get('.v-expansion-panel-title .btn-actions button').eq(1).click()
    cy.contains('.v-overlay--active .v-btn', 'Copy').should('exist')
    cy.contains('.v-overlay--active .v-btn', 'Merge').should('not.exist')
  })

  it('selects all shown elements if some are unselected and clears them if all are selected', () => {
    mountList({ content: contentItems(5) }, { 'page:save': true }).then(({ vm }) => {
      select(vm, 0, 2)
      expect(vm.allSelected).to.equal(false)

      vm.toggle()
      expect(vm.checkedCount).to.equal(5)
      expect(vm.allSelected).to.equal(true)

      vm.toggle()
      expect(vm.checkedCount).to.equal(0)
    })
  })

  it('hides merge with less than two selected elements', () => {
    mountList({ content: contentItems(3) }, { 'page:save': true }).then(({ vm }) => select(vm, 0))

    cy.get('.bulk .v-btn').first().click()
    cy.contains('.v-overlay .v-btn', 'Copy (1)').should('exist')
    cy.contains('.v-overlay .v-btn', 'Merge').should('not.exist')
  })

  it('cuts only the current element from the context menu of an unselected element', () => {
    mountList({ content: contentItems(3) }, { 'page:save': true }).then(({ owner, vm }) => {
      select(vm, 0, 2)
      vm.cut(1)
      expect(vm.clipboard.get('page-content').map((el) => el.data.title)).to.deep.equal(['Item 1'])
      expect(owner.content.map((el) => el.data.title)).to.deep.equal(['Item 0', 'Item 2'])
      expect(vm.checkedCount).to.equal(2)
    })
  })

  it('copies cut elements to the clipboard without their IDs', () => {
    mountList({}, { 'page:save': true }).then(({ owner, vm }) => {
      vm.cut(0)
      expect(vm.clipboard.get('page-content')).to.deep.equal([{ ...content[0], data: { ...content[0].data }, id: null }])
      expect(owner.content.map((el) => el.id)).to.deep.equal(['c2'])
    })
  })

  it('marks an element as changed after changing its type', () => {
    mountList({}, { 'page:save': true }).then(({ owner, vm }) => {
      vm.changeTo({ type: 'text' }, 0)
      expect(owner.content[0]).to.include({ id: 'c1', type: 'text', _changed: true })
      expect(vm.vchange).to.equal(false)
    })
  })

  it('replaces the split element where it is after the content changed meanwhile', () => {
    const list = [{ id: 'c1', type: 'text', group: 'main', data: { text: '# Title\n\nParagraph' } }]

    mountList({ content: list }, { 'page:save': true }).then(({ owner, vm }) => {
      const done = vm.split(0)
      owner.content = [{ id: 'c0', type: 'heading', group: 'main', data: { title: 'First' } }, ...owner.content]

      return done.then(() => {
        expect(owner.content.map((el) => el.type)).to.deep.equal(['heading', 'heading', 'text'])
        expect(owner.content[0].id).to.equal('c0')
        expect(owner.content[1].data.title).to.equal('Title')
        expect(owner.content[2].data.text).to.equal('Paragraph')
      })
    })
  })

  it('shows no count in the context menu of an unselected element', () => {
    mountList({ content: contentItems(3) }, { 'page:save': true }).then(({ vm }) => select(vm, 0, 2))
    cy.get('.v-expansion-panel-title .btn-actions button').eq(1).click()
    cy.get('.v-overlay .v-btn').contains(/^\s*Copy\s*$/).should('exist')
    cy.get('.v-overlay .v-btn').contains(/^\s*Cut\s*$/).should('exist')
  })

  it('hides bulk actions without page:save permission', () => {
    mountList()
    cy.get('.bulk').should('not.exist')
  })

  it('shows add element button with page:save permission', () => {
    mountList({}, { 'page:save': true })
    cy.get('button.btn-add').should('exist')
  })

  it('hides add element button without page:save permission', () => {
    mountList()
    cy.get('button.btn-add').should('not.exist')
  })

  it('shows AI refine textarea with page:refine permission', () => {
    mountList({}, { 'page:refine': true })
    cy.get('textarea').should('exist')
  })

  it('refines the content and shows the success state for a while', () => {
    const refined = JSON.stringify([{ id: 'c1', type: 'heading', data: { title: 'Better' } }])
    const mutate = cy.stub().resolves({ data: { refine: refined } })

    cy.clock(Date.now(), ['setTimeout', 'clearTimeout'])
    mountList({}, { 'page:refine': true }, { mutate }).then(({ vm, owner }) => {
      vm.chat = 'Improve'

      return vm.refine().then(() => {
        expect(mutate).to.have.been.calledOnce
        expect(owner.content[0].data.title).to.equal('Better')
        expect(owner.content[0]._changed).to.equal(true)
        expect(vm.refining).to.equal(false)
        expect(vm.refined).to.equal(true)
        expect(vm.chat).to.equal('')
      }).then(() => vm)
    }).then((vm) => {
      cy.tick(3000).then(() => expect(vm.refined).to.equal(false))
    })
  })

  it('ignores refine requests while refining or without prompt', () => {
    const mutate = cy.stub().returns(new Promise(() => {}))

    mountList({}, { 'page:refine': true }, { mutate }).then(({ vm }) => {
      vm.refine()
      expect(mutate).not.to.have.been.called

      vm.chat = 'Improve'
      vm.refine()
      vm.refine()
      expect(mutate).to.have.been.calledOnce
      expect(vm.refining).to.equal(true)
    })
  })

  it('hides AI refine textarea without page:refine permission', () => {
    mountList()
    cy.get('.prompt').should('not.exist')
  })

  it('shows checkbox in panel title with page:save permission', () => {
    mountList({}, { 'page:save': true })
    cy.get('.v-expansion-panel-title .v-checkbox-btn').should('exist')
  })

  it('clears the error state when deleting an invalid content element', () => {
    const onError = cy.spy()
    let vm

    mountList({ onError }).then((mounted) => {
      vm = mounted.vm
      vm.panel = [vm.content[0].id]
      vm.error(vm.content[0], true)
      vm.remove(0)
    })

    cy.wrap(null).should(() => {
      expect(vm.content.map((el) => el.id)).to.deep.equal(['c2'])
      expect(onError.args).to.deep.equal([[true], [false]])
    })

    cy.then(() => {
      vm.error(vm.content[0], true)

      expect(onError.args).to.deep.equal([[true], [false], [true]])
      expect(vm.panel).to.deep.equal([])
    })
  })

  it('adds files from a selected shared element to the page assets', () => {
    mountList({ content: [] }).then(({ owner, vm }) => {
      const file = { disk: 'private', id: 'file-1', path: 'draft.jpg', previews: {} }

      vm.add({ id: 'element-1', name: 'Shared', files: [file] }, null)

      expect(owner.assets['file-1']).to.deep.equal(file)
      expect(owner.elements['element-1'].files).to.deep.equal([file])
      expect(owner.content[0].refid).to.equal('element-1')
    })
  })

  it('keeps normalized files when making content shared', () => {
    let owner
    const mutate = cy.stub().resolves({
      data: {
        addElement: {
          id: 'element-1',
          data: '{}',
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
      },
    })
    const item = { id: 'c1', type: 'heading', group: 'main', data: {} }

    mountList({ content: [item] }, { 'element:add': true }, { mutate }).then((mounted) => {
      owner = mounted.owner
      mounted.vm.share(0)
    })

    cy.wrap(null).should(() => {
      const value = owner.elements

      expect(value['element-1'].files[0].path).to.equal('draft.jpg')
      expect(value['element-1'].files[0].previews).to.deep.equal({ 500: 'draft-500.webp' })
      expect(owner.assets['file-1']).to.deep.equal(value['element-1'].files[0])
      expect(owner.content[0]).to.deep.include({ id: 'c1', type: 'reference', refid: 'element-1' })
    })
  })

  it('creates only one shared element if sharing is requested twice', () => {
    let resolve
    const mutate = cy.stub().returns(new Promise((r) => { resolve = r }))
    const item = { id: 'c1', type: 'heading', group: 'main', data: {} }

    mountList({ content: [item] }, { 'element:add': true }, { mutate }).then((mounted) => {
      const pending = mounted.vm.share(0)

      expect(mounted.vm.share(0)).to.be.undefined
      expect(mutate).to.have.been.calledOnce

      resolve({ data: { addElement: { id: 'element-1', data: '{}', files: [] } } })

      return pending.then(() => {
        expect(mounted.vm.sharing.size).to.equal(0)
      })
    })
  })

  it('moves the focused element with Alt+ArrowUp/ArrowDown', () => {
    const onUpdate = cy.spy().as('update')
    const alt = (key) => new KeyboardEvent('keydown', { key, altKey: true, bubbles: true, cancelable: true })
    const order = (keys) => cy.get('.v-expansion-panel.content').should(($els) => {
      expect([...$els].map((el) => el.dataset.key)).to.deep.equal(keys)
    })

    mountList({ 'onUpdate:content': onUpdate, content: reactive(contentItems(3)) }, { 'page:save': true })

    cy.get('[data-key="c0"] .item-handle').focus().then(($btn) => {
      $btn[0].dispatchEvent(alt('ArrowDown'))
    })
    order(['c1', 'c0', 'c2'])
    cy.focused().closest('.content').should('have.attr', 'data-key', 'c0')
    cy.get('@update').should('have.been.calledOnce')

    cy.focused().then(($btn) => {
      $btn[0].dispatchEvent(alt('ArrowUp'))
    })
    order(['c0', 'c1', 'c2'])
    cy.focused().then(($btn) => {
      $btn[0].dispatchEvent(alt('ArrowUp')) // already the first element
    })
    cy.get('@update').should('have.been.calledTwice')
  })

  it('skips hidden elements when moving with Alt+Arrow', () => {
    mountList({ content: contentItems(3) }, { 'page:save': true }).then(({ vm }) => {
      vm.hidden = new Set([vm.content[1]])
    })

    cy.get('[data-key="c0"] .item-handle').focus().then(($btn) => {
      $btn[0].dispatchEvent(new KeyboardEvent('keydown', { key: 'ArrowDown', altKey: true, bubbles: true, cancelable: true }))
    })
    cy.get('.v-expansion-panel.content').should(($els) => {
      expect([...$els].map((el) => el.dataset.key)).to.deep.equal(['c1', 'c2', 'c0'])
    })
  })

  it('does not move elements with Alt+Arrow without page:save permission', () => {
    mountList({ content: contentItems(2) })

    cy.get('[data-key="c0"] .item-handle').then(($btn) => {
      const e = new KeyboardEvent('keydown', { key: 'ArrowDown', altKey: true, bubbles: true, cancelable: true })
      $btn[0].dispatchEvent(e)
      expect(e.defaultPrevented).to.equal(false)
    })
    cy.get('.v-expansion-panel.content').first().should('have.attr', 'data-key', 'c0')
  })
})
