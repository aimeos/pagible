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

function mountList(props = {}, perms = {}, apollo = {}) {
  return cy.mount(PageDetailContentList, {
    props: {
      item: { id: '1', lang: 'en' },
      assets: {},
      content: [...content.map(c => ({ ...c }))],
      elements: {},
      ...props,
    },
    global: globalOptions(apollo),
  }).then(({ wrapper }) => {
    const user = useUserStore()
    user.me = { permission: perms }

    return { wrapper }
  })
}

function mountScrollable(content) {
  const Host = {
    data: () => ({ content }),

    render() {
      return h('div', { class: 'scroll', style: { height: '320px', overflowY: 'auto' } }, [
        h(PageDetailContentList, {
          item: { id: '1', lang: 'en' },
          assets: {},
          content: this.content,
          elements: {},
        }),
      ])
    },
  }

  return cy.mount(Host, { global: globalOptions() })
}

describe('PageDetailContentList', () => {
  beforeEach(() => {
    cy.on('uncaught:exception', () => false)
  })

  it('renders the component', () => {
    mountList()
    cy.get('.v-expansion-panels').should('exist')
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
        { ...content[0], id: null, _checked: false },
      ])
      vm.menu = vm.content[1].id
    })

    cy.get('.bulk > .v-btn').should('not.be.disabled')
    cy.contains('.v-overlay .v-btn', 'Paste before').should('exist')
    cy.contains('.v-overlay .v-btn', 'Paste after').should('exist')
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

    mountList({ onError }).then(({ wrapper }) => {
      const vm = wrapper.findComponent(PageDetailContentList).vm

      vm.panel = [vm.content[0].id]
      vm.error(vm.content[0], true)
      vm.remove(0)
      vm.error(vm.content[0], true)

      expect(onError.args).to.deep.equal([[true], [false], [true]])
      expect(vm.panel).to.deep.equal([])
    })
  })

  it('adds files from a selected shared element to the page assets', () => {
    const assets = {}
    const elements = {}

    mountList({ assets, content: [], elements }).then(() => {
      const vm = Cypress.vueWrapper.findComponent(PageDetailContentList).vm
      const file = { disk: 'private', id: 'file-1', path: 'draft.jpg', previews: {} }

      vm.add({ id: 'element-1', name: 'Shared', files: [file] }, null)

      expect(assets['file-1']).to.equal(file)
      expect(elements['element-1'].files).to.deep.equal([file])
    })
  })

  it('keeps normalized files when making content shared', () => {
    const assets = {}
    const elements = {}
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

    mountList({ assets, content: [item], elements }, { 'element:add': true }, { mutate }).then(({ wrapper }) => {
      wrapper.findComponent(PageDetailContentList).vm.share(0)
    })

    cy.wrap(elements).should((value) => {
      expect(value['element-1'].files[0].path).to.equal('draft.jpg')
      expect(value['element-1'].files[0].previews).to.deep.equal({ 500: 'draft-500.webp' })
      expect(assets['file-1']).to.equal(value['element-1'].files[0])
    })
  })
})
